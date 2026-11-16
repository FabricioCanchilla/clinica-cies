<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * RF039 Caja: apertura, cobro del saldo de citas, descuentos, anulación y cierre.
 *  RN26: todo movimiento exige caja abierta del cajero.
 *  RN28: un comprobante solo se anula el mismo día de emisión y con motivo.
 *  RN29: descuento > 20 % requiere autorización de un administrador.
 * Todo se audita en DOM04 (Financiero). El comprobante se pide a Quarkus después del commit.
 */
class CajaService
{
    public function __construct(private ComprobanteService $comprobantes)
    {
    }

    // ---------- Consultas ----------

    public function cajaAbierta(string $codEmp): ?object
    {
        return DB::table('CAJA')->where('COD_EMP', $codEmp)->where('EST_CAJ', 'Abierta')->first();
    }

    /** Totales de la caja: cobrado confirmado, efectivo y monto esperado en gaveta. */
    public function resumen(object $caja): array
    {
        $base = DB::table('PAGO')->where('COD_CAJ', $caja->COD_CAJ)->where('EST_PAG', 'Confirmado');

        $total    = (float) (clone $base)->sum('MON_PAG');
        $efectivo = (float) (clone $base)->where('COD_MET', config('cies.metodo_efectivo'))->sum('MON_PAG');
        $porMetodo = DB::table('PAGO as p')
            ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'p.COD_MET')
            ->where('p.COD_CAJ', $caja->COD_CAJ)->where('p.EST_PAG', 'Confirmado')
            ->groupBy('m.DES_MET')
            ->orderBy('m.DES_MET')
            ->get(['m.DES_MET', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(p.MON_PAG) as total')]);

        return [
            'total'     => $total,
            'efectivo'  => $efectivo,
            'esperado'  => round((float) $caja->MONAPE_CAJ + $efectivo, 2),
            'porMetodo' => $porMetodo,
            'anulados'  => DB::table('PAGO')->where('COD_CAJ', $caja->COD_CAJ)->where('EST_PAG', 'Anulado')->count(),
        ];
    }

    /** Citas Confirmadas o Atendidas con saldo pendiente (precio del servicio - pagos confirmados). */
    public function citasPorCobrar(string $buscar = '')
    {
        $pagado = DB::table('PAGO')->selectRaw('COALESCE(SUM(MON_PAG), 0)')
            ->whereColumn('COD_CIT', 'c.COD_CIT')->where('EST_PAG', 'Confirmado');

        $q = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->whereIn('c.EST_CIT', ['Confirmada', 'Atendida'])
            ->select(['c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC',
                      's.NOM_SRV', 's.PRE_SRV', 'e.TIT_EMP', 'e.APE_EMP'])
            ->selectSub($pagado, 'PAGADO')
            ->whereRaw('s.PRE_SRV > (' . $pagado->toSql() . ')', $pagado->getBindings());

        $buscar = trim($buscar);
        if ($buscar !== '') {
            $q->where(function ($w) use ($buscar) {
                $w->where('c.COD_CIT', $buscar)
                  ->orWhere('p.CI_PAC', 'like', "{$buscar}%")
                  ->orWhere('p.APE_PAC', 'like', "%{$buscar}%")
                  ->orWhere('p.NOM_PAC', 'like', "%{$buscar}%");
            });
        }

        return $q->orderBy('c.FEC_CIT')->orderBy('c.HOR_CIT')->limit(50)->get()
            ->each(fn ($c) => $c->SALDO = round((float) $c->PRE_SRV - (float) $c->PAGADO, 2));
    }

    public function citaParaCobro(string $codCit): object
    {
        $c = $this->citasPorCobrar($codCit)->firstWhere('COD_CIT', $codCit);
        if (!$c) {
            throw new DomainException('La cita no existe, no está Confirmada/Atendida o no tiene saldo pendiente.');
        }
        return $c;
    }

    /** Cobros de esta caja sin comprobante Emitido (Quarkus falló): muestran "Reintentar comprobante". */
    public function cobrosSinComprobante(string $codCaj)
    {
        return DB::table('PAGO as p')
            ->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')
            ->join('PACIENTE as pa', 'pa.COD_PAC', '=', 'c.COD_PAC')
            ->where('p.COD_CAJ', $codCaj)->where('p.EST_PAG', 'Confirmado')
            ->whereNotExists(function ($q) {
                $q->from('COMPROBANTE as k')->whereColumn('k.COD_CIT', 'p.COD_CIT')
                  ->where('k.EST_COM', 'Emitido')->whereNotNull('k.COD_CAJ');
            })
            ->orderBy('p.id')
            ->get(['p.COD_PAG', 'p.FEC_PAG', 'p.MON_PAG', 'p.COD_CIT', 'pa.NOM_PAC', 'pa.APE_PAC']);
    }

    /** Comprobantes emitidos por esta caja, con su anulación si la tienen. */
    public function comprobantesDeCaja(string $codCaj)
    {
        return DB::table('COMPROBANTE as k')
            ->join('PACIENTE as pa', 'pa.COD_PAC', '=', 'k.COD_PAC')
            ->leftJoin('ANULACION as a', 'a.NRO_COM', '=', 'k.NRO_COM')
            ->where('k.COD_CAJ', $codCaj)
            ->orderByDesc('k.id')
            ->get(['k.NRO_COM', 'k.FEC_COM', 'k.MONTO_COM', 'k.EST_COM', 'k.COD_CIT',
                   'pa.NOM_PAC', 'pa.APE_PAC', 'a.MOT_ANU', 'a.FEC_ANU']);
    }

    public function metodosPago()
    {
        return DB::table('METODO_PAGO')->orderBy('DES_MET')->get();
    }

    // ---------- Apertura ----------

    public function abrir(string $codEmp, float $monto): object
    {
        if ($monto < 0 || $monto > 100000) {
            throw new DomainException('El monto de apertura debe estar entre Bs 0 y Bs 100 000.');
        }

        return DB::transaction(function () use ($codEmp, $monto) {
            // Serializa por cajero: no puede tener dos cajas abiertas
            DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->lockForUpdate()->first();
            if ($this->cajaAbierta($codEmp)) {
                throw new DomainException('Ya tienes una caja abierta. Ciérrala antes de abrir otra.');
            }

            $codCaj = sprintf('CAJ%07d', (int) DB::table('CAJA')->max('id') + 1);
            DB::table('CAJA')->insert([
                'COD_CAJ'    => $codCaj,
                'FECAPE_CAJ' => Carbon::now()->toDateTimeString(),
                'MONAPE_CAJ' => round($monto, 2),
                'FECCIE_CAJ' => null,
                'MONCIE_CAJ' => null,
                'EST_CAJ'    => 'Abierta',
                'COD_EMP'    => $codEmp,
            ]);

            Auditoria::registrar('Apertura de caja', 'Media', 'DOM04', 'CAJA', $codCaj,
                'Caja abierta con Bs ' . number_format($monto, 2, '.', ''));

            return DB::table('CAJA')->where('COD_CAJ', $codCaj)->first();
        });
    }

    // ---------- Cobro ----------

    /**
     * Cobra el saldo de una cita. $autorizador = ['usuario' => ..., 'clave' => ...] solo si el descuento > 20 %.
     * Devuelve el pago y el NRO_COM (null si Quarkus falló: queda el botón de reintento).
     */
    public function cobrar(string $codEmp, string $codCit, string $codMet, int $descuento, ?array $autorizador = null): object
    {
        if ($descuento < 0 || $descuento > 99) {
            throw new DomainException('El descuento debe estar entre 0 % y 99 %.');
        }
        if (!DB::table('METODO_PAGO')->where('COD_MET', $codMet)->exists()) {
            throw new DomainException('Selecciona un método de pago válido.');
        }

        // RN29: se valida fuera de la transacción para que el rechazo quede auditado
        $admin = null;
        $limite = (int) config('cies.descuento_sin_autorizacion');
        if ($descuento > $limite) {
            $admin = $this->validarAutorizador($autorizador, $codCit, $descuento);
        }

        $pago = DB::transaction(function () use ($codEmp, $codCit, $codMet, $descuento, $admin) {
            $caja = DB::table('CAJA')->where('COD_EMP', $codEmp)->where('EST_CAJ', 'Abierta')
                ->lockForUpdate()->first();
            if (!$caja) {
                throw new DomainException('Abre tu caja antes de cobrar (RN26).');
            }

            $cita = DB::table('CITA as c')->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
                ->where('c.COD_CIT', $codCit)->lockForUpdate()
                ->first(['c.COD_CIT', 'c.EST_CIT', 'c.COD_PAC', 's.PRE_SRV', 's.NOM_SRV']);
            if (!$cita || !in_array($cita->EST_CIT, ['Confirmada', 'Atendida'], true)) {
                throw new DomainException('Solo se cobran citas Confirmadas o Atendidas.');
            }

            $pagado = (float) DB::table('PAGO')->where('COD_CIT', $codCit)->where('EST_PAG', 'Confirmado')->sum('MON_PAG');
            $saldo  = round((float) $cita->PRE_SRV - $pagado, 2);
            if ($saldo <= 0) {
                throw new DomainException('La cita ya no tiene saldo pendiente.');
            }
            $monto = round($saldo * (100 - $descuento) / 100, 2);
            if ($monto <= 0) {
                throw new DomainException('El monto a cobrar quedó en cero; revisa el descuento.');
            }

            $codPag = sprintf('PAG%07d', (int) DB::table('PAGO')->max('id') + 1);
            DB::table('PAGO')->insert([
                'COD_PAG' => $codPag,
                'FEC_PAG' => Carbon::now()->toDateTimeString(),
                'MON_PAG' => $monto,
                'TIP_PAG' => 'Total',
                'EST_PAG' => 'Confirmado',
                'COD_CIT' => $codCit,
                'COD_MET' => $codMet,
                'COD_OTP' => null,
                'COD_CAJ' => $caja->COD_CAJ,
            ]);

            $detalle = "Saldo Bs {$saldo} de la cita {$codCit} ({$cita->NOM_SRV}), cobrado Bs {$monto} en caja {$caja->COD_CAJ}";
            if ($descuento > 0) {
                $detalle .= ", descuento {$descuento} %";
            }
            if ($admin) {
                $detalle .= ", autorizado por {$admin->USR_USR}";
            }
            Auditoria::registrar('Cobro en caja', $admin ? 'Alta' : 'Media', 'DOM04', 'PAGO', $codPag, $detalle,
                codPac: $cita->COD_PAC);

            return (object) ['COD_PAG' => $codPag, 'monto' => $monto, 'saldo' => $saldo, 'COD_CIT' => $codCit];
        });

        // Después del commit
        try {
            $pago->NRO_COM = $this->comprobantes->emitirCobro($pago->COD_PAG);
        } catch (DomainException $e) {
            $pago->NRO_COM = null;
        }

        return $pago;
    }

    private function validarAutorizador(?array $aut, string $codCit, int $descuento): object
    {
        $usuario = trim($aut['usuario'] ?? '');
        $clave   = (string) ($aut['clave'] ?? '');

        $admin = $usuario === '' ? null : DB::table('USUARIO')
            ->where('USR_USR', $usuario)->where('COD_ROL', 'ROL001')->where('EST_USR', 'Activo')
            ->first(['COD_USR', 'USR_USR', 'CON_USR']);

        if (!$admin || $clave === '' || !Hash::check($clave, $admin->CON_USR)) {
            Auditoria::registrar('Descuento rechazado', 'Alta', 'DOM04', 'CITA', $codCit,
                "Descuento de {$descuento} % sin autorización válida de administrador (usuario indicado: "
                . ($usuario === '' ? 'ninguno' : $usuario) . ')');
            throw new DomainException('Un descuento mayor a ' . config('cies.descuento_sin_autorizacion')
                . ' % requiere usuario y contraseña de un administrador activo (RN29).');
        }

        return $admin;
    }

    public function reintentarComprobante(string $codEmp, string $codPag): ?string
    {
        $caja = $this->cajaAbierta($codEmp);
        if (!$caja || !DB::table('PAGO')->where('COD_PAG', $codPag)->where('COD_CAJ', $caja->COD_CAJ)->exists()) {
            throw new DomainException('Ese cobro no pertenece a tu caja abierta.');
        }
        return $this->comprobantes->emitirCobro($codPag);
    }

    // ---------- Anulación (RN28) ----------

    public function anular(string $codEmp, string $nroCom, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 200) {
            throw new DomainException('Escribe el motivo de la anulación (entre 10 y 200 caracteres).');
        }

        $comp = DB::table('COMPROBANTE')->where('NRO_COM', $nroCom)->first();
        if (!$comp) {
            throw new DomainException('El comprobante no existe.');
        }
        if (!Carbon::parse($comp->FEC_COM)->isToday()) {
            Auditoria::registrar('Anulación rechazada', 'Alta', 'DOM04', 'COMPROBANTE', $nroCom,
                "Intento de anular un comprobante del {$comp->FEC_COM} fuera del día de emisión. Motivo: {$motivo}",
                codPac: $comp->COD_PAC);
            throw new DomainException('Solo se puede anular un comprobante el mismo día en que se emitió (RN28).');
        }

        DB::transaction(function () use ($codEmp, $nroCom, $motivo) {
            $caja = DB::table('CAJA')->where('COD_EMP', $codEmp)->where('EST_CAJ', 'Abierta')
                ->lockForUpdate()->first();
            if (!$caja) {
                throw new DomainException('Necesitas tu caja abierta para anular (RN26).');
            }

            $comp = DB::table('COMPROBANTE')->where('NRO_COM', $nroCom)->lockForUpdate()->first();
            if (!$comp || $comp->COD_CAJ !== $caja->COD_CAJ) {
                throw new DomainException('Solo puedes anular comprobantes emitidos por tu caja abierta.');
            }
            if ($comp->EST_COM !== 'Emitido') {
                throw new DomainException('El comprobante ya está anulado.');
            }

            $codAnu = sprintf('ANU%07d', (int) DB::table('ANULACION')->max('id') + 1);
            DB::table('ANULACION')->insert([
                'COD_ANU' => $codAnu,
                'FEC_ANU' => Carbon::now()->toDateTimeString(),
                'MOT_ANU' => $motivo,
                'NRO_COM' => $nroCom,
                'COD_EMP' => $codEmp,
            ]);
            DB::table('COMPROBANTE')->where('NRO_COM', $nroCom)->update(['EST_COM' => 'Anulado']);

            // El cobro de esa cita en esta caja también se anula: el saldo vuelve a quedar pendiente
            $pagos = DB::table('PAGO')->where('COD_CIT', $comp->COD_CIT)->where('COD_CAJ', $caja->COD_CAJ)
                ->where('EST_PAG', 'Confirmado')->pluck('COD_PAG');
            DB::table('PAGO')->whereIn('COD_PAG', $pagos)->update(['EST_PAG' => 'Anulado']);

            Auditoria::registrar('Anular comprobante', 'Alta', 'DOM04', 'COMPROBANTE', $nroCom,
                "Anulación {$codAnu} (Bs {$comp->MONTO_COM}, cita {$comp->COD_CIT}, pagos " . $pagos->implode(', ')
                . "). Motivo: {$motivo}", codPac: $comp->COD_PAC);
        });
    }

    // ---------- Cierre ----------

    public function cerrar(string $codEmp, float $contado): array
    {
        if ($contado < 0 || $contado > 1000000) {
            throw new DomainException('El monto contado no es válido.');
        }

        return DB::transaction(function () use ($codEmp, $contado) {
            $caja = DB::table('CAJA')->where('COD_EMP', $codEmp)->where('EST_CAJ', 'Abierta')
                ->lockForUpdate()->first();
            if (!$caja) {
                throw new DomainException('No tienes una caja abierta.');
            }

            $pendientes = $this->cobrosSinComprobante($caja->COD_CAJ)->count();
            if ($pendientes > 0) {
                throw new DomainException("Hay {$pendientes} cobro(s) sin comprobante. Usa \"Reintentar comprobante\" antes de cerrar.");
            }

            $r          = $this->resumen($caja);
            $contado    = round($contado, 2);
            $diferencia = round($contado - $r['esperado'], 2);

            DB::table('CAJA')->where('COD_CAJ', $caja->COD_CAJ)->update([
                'FECCIE_CAJ' => Carbon::now()->toDateTimeString(),
                'MONCIE_CAJ' => $contado,
                'EST_CAJ'    => 'Cerrada',
            ]);

            Auditoria::registrar('Cierre de caja', $diferencia == 0.0 ? 'Media' : 'Alta', 'DOM04', 'CAJA', $caja->COD_CAJ,
                "Apertura Bs {$caja->MONAPE_CAJ}, cobrado Bs {$r['total']} (efectivo Bs {$r['efectivo']}), "
                . "esperado en efectivo Bs {$r['esperado']}, contado Bs {$contado}, diferencia Bs {$diferencia}");

            return ['COD_CAJ' => $caja->COD_CAJ, 'esperado' => $r['esperado'], 'contado' => $contado, 'diferencia' => $diferencia];
        });
    }
}
