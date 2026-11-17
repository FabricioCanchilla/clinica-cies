<?php

namespace App\Services;

use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Emisión de comprobantes vía Quarkus. Regla: se llama SIEMPRE después del commit,
 * nunca dentro de un DB::transaction (no se mantienen bloqueos durante la llamada HTTP
 * y un fallo de Quarkus no revierte el pago).
 *
 * - Anticipo en línea: COMPROBANTE con COD_EMP y COD_CAJ en NULL.
 * - Cobro en caja:     COMPROBANTE con COD_EMP y COD_CAJ de la caja.
 *
 * Antes de emitir se revisa que no exista ya un comprobante Emitido del mismo tipo para la cita.
 */
class ComprobanteService
{
    public function __construct(private ComprobanteClient $cliente)
    {
    }

    // ---------- Consultas ----------

    public function anticipoEmitido(string $codCit): ?object
    {
        return DB::table('COMPROBANTE')
            ->where('COD_CIT', $codCit)->where('EST_COM', 'Emitido')->whereNull('COD_CAJ')
            ->first();
    }

    public function cobroEmitido(string $codCit): ?object
    {
        return DB::table('COMPROBANTE')
            ->where('COD_CIT', $codCit)->where('EST_COM', 'Emitido')->whereNotNull('COD_CAJ')
            ->first();
    }

    // ---------- Anticipo pagado en línea (RF008) ----------

    /**
     * Emite el comprobante del anticipo de una cita. Devuelve el NRO_COM, o null si Quarkus
     * falló (queda auditado en DOM04 y el paciente ve el botón "Reintentar comprobante").
     */
    public function emitirAnticipo(string $codCit): ?string
    {
        $this->fueraDeTransaccion();

        return $this->conCandado("comprobante-anticipo:{$codCit}", function () use ($codCit) {
            if ($ya = $this->anticipoEmitido($codCit)) {
                return $ya->NRO_COM;
            }

            $pago = DB::table('PAGO as p')
                ->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')
                ->where('p.COD_CIT', $codCit)
                ->where('p.TIP_PAG', 'Adelanto')->where('p.EST_PAG', 'Confirmado')->whereNull('p.COD_CAJ')
                ->first(['p.COD_PAG', 'p.MON_PAG', 'c.COD_SRV', 'c.COD_PAC']);
            if (!$pago) {
                throw new DomainException('La cita no tiene un anticipo confirmado.');
            }

            $items = [['codSrv' => $pago->COD_SRV, 'cantidad' => 1, 'precio' => (float) $pago->MON_PAG, 'descuento' => 0]];

            try {
                $r = $this->cliente->emitir($codCit, $items);
            } catch (DomainException $e) {
                $this->auditarFallo($pago->COD_PAG, "Anticipo de la cita {$codCit}", $e, $pago->COD_PAC);
                return null;
            }

            Auditoria::registrar('Emitir comprobante', 'Baja', 'DOM04', 'COMPROBANTE', $r['nroCom'],
                "Anticipo Bs {$r['monto']} de la cita {$codCit} (pago {$pago->COD_PAG}), pago en línea",
                codPac: $pago->COD_PAC);

            return $r['nroCom'];
        });
    }

    // ---------- Cobro en caja (RF039) ----------

    /**
     * Emite el comprobante de un cobro hecho en caja (PAGO con COD_CAJ).
     * El descuento no se guarda en PAGO, así que se reconstruye desde el monto:
     * base = precio del servicio - otros pagos confirmados; desc = 100 * (1 - monto / base).
     */
    public function emitirCobro(string $codPag): ?string
    {
        $this->fueraDeTransaccion();

        $pago = DB::table('PAGO as p')
            ->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CAJA as k', 'k.COD_CAJ', '=', 'p.COD_CAJ')
            ->where('p.COD_PAG', $codPag)
            ->first(['p.COD_PAG', 'p.MON_PAG', 'p.EST_PAG', 'p.COD_CIT', 'p.COD_CAJ', 'p.id',
                     'c.COD_SRV', 'c.COD_PAC', 's.PRE_SRV', 'k.COD_EMP']);
        if (!$pago) {
            throw new DomainException('El cobro no existe o no se hizo en caja.');
        }
        if ($pago->EST_PAG !== 'Confirmado') {
            throw new DomainException('El cobro fue anulado; no corresponde emitir comprobante.');
        }

        return $this->conCandado("comprobante-cobro:{$pago->COD_CIT}", function () use ($pago) {
            if ($ya = $this->cobroEmitido($pago->COD_CIT)) {
                return $ya->NRO_COM;
            }

            $detalle = DB::table('CAJA_DETALLE_PAGO')->where('COD_PAG', $pago->COD_PAG)->first();
            $otros = (float) DB::table('PAGO')
                ->where('COD_CIT', $pago->COD_CIT)->where('EST_PAG', 'Confirmado')
                ->where('COD_PAG', '!=', $pago->COD_PAG)
                ->sum('MON_PAG');
            $otros += (float) DB::table('SALDO_APLICACION')->where('COD_CIT', $pago->COD_CIT)->sum('MONTO');
            $base  = round((float) $pago->PRE_SRV - $otros, 2);
            $desc  = $base > 0 ? (int) round(100 * (1 - (float) $pago->MON_PAG / $base)) : 0;
            $desc  = max(0, min(100, $desc));
            if ($detalle) {
                $base = (float) $detalle->BASE;
                $desc = (int) $detalle->PORCENTAJE;
            }
            if ($base <= 0) {
                $base = (float) $pago->MON_PAG;
            }

            $items = [['codSrv' => $pago->COD_SRV, 'cantidad' => 1, 'precio' => $base, 'descuento' => $desc]];

            try {
                $r = $this->cliente->emitir($pago->COD_CIT, $items, $pago->COD_EMP, $pago->COD_CAJ);
            } catch (DomainException $e) {
                $this->auditarFallo($pago->COD_PAG, "Cobro en caja {$pago->COD_CAJ} de la cita {$pago->COD_CIT}", $e, $pago->COD_PAC);
                return null;
            }

            if (abs((float) $r['monto'] - (float) $pago->MON_PAG) > 0.01) {
                Log::channel('cies')->warning("Comprobante {$r['nroCom']}: Quarkus calculó Bs {$r['monto']} y el pago {$pago->COD_PAG} es Bs {$pago->MON_PAG}");
            }

            Auditoria::registrar('Emitir comprobante', 'Baja', 'DOM04', 'COMPROBANTE', $r['nroCom'],
                "Cobro Bs {$r['monto']} de la cita {$pago->COD_CIT} (pago {$pago->COD_PAG}, caja {$pago->COD_CAJ}, descuento {$desc} %)",
                codPac: $pago->COD_PAC);

            return $r['nroCom'];
        });
    }

    public function pdf(string $nro): string
    {
        return $this->cliente->pdf($nro);
    }

    // ---------- Apoyo ----------

    private function fueraDeTransaccion(): void
    {
        if (DB::transactionLevel() > 0) {
            throw new LogicException('El comprobante se pide después del commit, nunca dentro de DB::transaction.');
        }
    }

    /** Evita que dos clics seguidos (o dos pestañas) emitan dos comprobantes para la misma cita. */
    private function conCandado(string $clave, callable $fn): ?string
    {
        $candado = Cache::lock($clave, 20);
        try {
            $candado->block(10);
        } catch (LockTimeoutException) {
            throw new DomainException('Ya se está generando el comprobante. Espera unos segundos y revisa de nuevo.');
        }
        try {
            return $fn();
        } finally {
            $candado->release();
        }
    }

    private function auditarFallo(string $codPag, string $contexto, DomainException $e, ?string $codPac): void
    {
        Log::channel('cies')->warning("Comprobante no emitido para el pago {$codPag}: {$e->getMessage()}");
        Auditoria::registrar('Comprobante fallido', 'Alta', 'DOM04', 'PAGO', $codPag,
            "{$contexto}: {$e->getMessage()} El pago sigue confirmado; queda pendiente de reintento.",
            codPac: $codPac);
    }
}
