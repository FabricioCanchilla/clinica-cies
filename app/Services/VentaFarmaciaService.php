<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * RF041: venta de medicamentos en mostrador a pacientes ambulantes, o cargada a la cuenta de un
 * paciente hospitalizado. El stock sale por PEPS (RN34: primero el lote que vence antes, nunca uno
 * vencido) y cada venta emite un recibo con numeración correlativa. La venta no se edita: se anula
 * el mismo día, con motivo, y el stock vuelve a sus lotes.
 */
class VentaFarmaciaService
{
    public function ventasDelDia()
    {
        return DB::table('VENTA as v')->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'v.COD_EMP')
            ->whereDate('v.FEC_VEN', now()->toDateString())->orderByDesc('v.id')
            ->get(['v.*', 'e.NOM_EMP', 'e.APE_EMP']);
    }

    public function catalogo()
    {
        $hoy = now()->toDateString();
        return DB::table('MEDICAMENTO as m')
            ->leftJoin('LOTE_MEDICAMENTO as l', function ($j) use ($hoy) {
                $j->on('l.COD_MED', '=', 'm.COD_MED')->where('l.FECVEN_LOT', '>=', $hoy)->where('l.CANDIS_LOT', '>', 0);
            })
            ->groupBy('m.COD_MED', 'm.DES_MED', 'm.PRE_MED')
            ->orderBy('m.DES_MED')
            ->get(['m.COD_MED', 'm.DES_MED', 'm.PRE_MED', DB::raw('COALESCE(SUM(l.CANDIS_LOT), 0) as STOCK')]);
    }

    public function internados()
    {
        return DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')
            ->where('i.EST_INT', 'Internado')->orderBy('p.APE_PAC')
            ->get(['i.COD_INT', 'i.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'c.NRO_CAM']);
    }

    /**
     * $items: [['med' => COD_MED, 'cantidad' => n], ...]
     * $datos: tipo (Ambulante|Hospitalizado), cliente, ci (opcional), internacion, metodo
     */
    public function vender(array $items, array $datos, object $actor): string
    {
        $items = collect($items)->filter(fn ($i) => !empty($i['med']) && (int) ($i['cantidad'] ?? 0) > 0)
            ->groupBy('med')->map(fn ($g, $med) => ['med' => $med, 'cantidad' => $g->sum(fn ($i) => (int) $i['cantidad'])])
            ->values();
        if ($items->isEmpty()) {
            throw new DomainException('Agrega al menos un medicamento con su cantidad.');
        }

        return DB::transaction(function () use ($items, $datos, $actor) {
            $hoy = now()->toDateString();
            $codPac = null; $codInt = null; $cliente = trim($datos['cliente'] ?? '');

            if ($datos['tipo'] === 'Hospitalizado') {
                $int = DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
                    ->where('i.COD_INT', $datos['internacion'] ?? '')->where('i.EST_INT', 'Internado')
                    ->first(['i.COD_INT', 'i.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC']);
                if (!$int) {
                    throw new DomainException('Elige un paciente internado para cargar el consumo a su cuenta.');
                }
                $codPac = $int->COD_PAC; $codInt = $int->COD_INT; $cliente = "{$int->NOM_PAC} {$int->APE_PAC}";
            } else {
                if (!empty($datos['ci'])) {
                    $p = DB::table('PACIENTE')->where('CI_PAC', trim($datos['ci']))->first(['COD_PAC', 'NOM_PAC', 'APE_PAC']);
                    if ($p) {
                        $codPac = $p->COD_PAC;
                        $cliente = $cliente ?: "{$p->NOM_PAC} {$p->APE_PAC}";
                    }
                }
                if ($cliente === '') {
                    $cliente = 'Cliente de mostrador';
                }
            }

            $ultimo = DB::table('VENTA')->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(NRO_VEN, 3) AS UNSIGNED)'));
            $nro = sprintf('V-%08d', (int) $ultimo + 1);
            DB::table('VENTA')->insert([
                'NRO_VEN' => $nro, 'FEC_VEN' => now(), 'CLI_VEN' => mb_substr($cliente, 0, 100), 'TOT_VEN' => 0,
                'EST_VEN' => 'Emitida', 'TIP_VEN' => $datos['tipo'], 'MOTANU_VEN' => null,
                'COD_PAC' => $codPac, 'COD_INT' => $codInt,
                'COD_MET' => $datos['tipo'] === 'Ambulante' ? ($datos['metodo'] ?? null) : null,
                'COD_EMP' => $actor->COD_EMP,
            ]);

            $total = 0; $n = (int) DB::table('DETALLE_VENTA')->lockForUpdate()->max('id'); $resumen = [];
            foreach ($items as $it) {
                $med = DB::table('MEDICAMENTO')->where('COD_MED', $it['med'])->first();
                if (!$med) {
                    throw new DomainException('Medicamento desconocido.');
                }
                // RN34: PEPS, nunca un lote vencido
                $lotes = DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $med->COD_MED)
                    ->where('CANDIS_LOT', '>', 0)->where('FECVEN_LOT', '>=', $hoy)
                    ->orderBy('FECVEN_LOT')->orderBy('FECING_LOT')->orderBy('id')->lockForUpdate()->get();
                if ((int) $lotes->sum('CANDIS_LOT') < $it['cantidad']) {
                    throw new DomainException("Stock vigente insuficiente de {$med->DES_MED}: hay {$lotes->sum('CANDIS_LOT')}.");
                }
                $resto = $it['cantidad'];
                foreach ($lotes as $l) {
                    if ($resto <= 0) {
                        break;
                    }
                    $toma = min($resto, (int) $l->CANDIS_LOT);
                    DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $l->COD_LOT)->decrement('CANDIS_LOT', $toma);
                    DB::table('DETALLE_VENTA')->insert([
                        'COD_DVE' => sprintf('DVE%07d', ++$n), 'NRO_VEN' => $nro, 'COD_MED' => $med->COD_MED,
                        'COD_LOT' => $l->COD_LOT, 'CAN_DVE' => $toma, 'PRE_DVE' => $med->PRE_MED,
                    ]);
                    $resto -= $toma;
                }
                $total += $it['cantidad'] * (float) $med->PRE_MED;
                $resumen[] = "{$med->DES_MED} x{$it['cantidad']}";
            }
            DB::table('VENTA')->where('NRO_VEN', $nro)->update(['TOT_VEN' => round($total, 2)]);

            Auditoria::registrar('Venta farmacia', 'Media', 'DOM04', 'VENTA', $nro,
                ($datos['tipo'] === 'Hospitalizado' ? "Cargo a la internación {$codInt}: " : 'Venta en mostrador: ')
                . implode(', ', $resumen) . ' · Bs ' . number_format($total, 2), $actor, $codPac);
            return $nro;
        });
    }

    public function anular(string $nro, string $motivo, object $actor): void
    {
        DB::transaction(function () use ($nro, $motivo, $actor) {
            $v = DB::table('VENTA')->where('NRO_VEN', $nro)->lockForUpdate()->first();
            if (!$v) {
                throw new DomainException('La venta no existe.');
            }
            if ($v->EST_VEN !== 'Emitida') {
                throw new DomainException('La venta ya está anulada.');
            }
            if (!str_starts_with((string) $v->FEC_VEN, now()->toDateString())) {
                throw new DomainException('Solo se anulan ventas del mismo día.');
            }
            foreach (DB::table('DETALLE_VENTA')->where('NRO_VEN', $nro)->get() as $d) {
                DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $d->COD_LOT)->increment('CANDIS_LOT', (int) $d->CAN_DVE);
            }
            DB::table('VENTA')->where('NRO_VEN', $nro)->update(['EST_VEN' => 'Anulada', 'MOTANU_VEN' => $motivo]);
            Auditoria::registrar('Anular venta', 'Alta', 'DOM04', 'VENTA', $nro,
                "Venta anulada (stock devuelto a sus lotes): {$motivo}", $actor, $v->COD_PAC);
        });
    }

    public function venta(string $nro): ?object
    {
        $v = DB::table('VENTA as v')->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'v.COD_EMP')
            ->leftJoin('METODO_PAGO as m', 'm.COD_MET', '=', 'v.COD_MET')
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'v.COD_PAC')
            ->where('v.NRO_VEN', $nro)->first(['v.*', 'e.NOM_EMP', 'e.APE_EMP', 'm.DES_MET', 'p.CI_PAC']);
        if ($v) {
            $v->ITEMS = DB::table('DETALLE_VENTA as d')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
                ->join('LOTE_MEDICAMENTO as l', 'l.COD_LOT', '=', 'd.COD_LOT')
                ->where('d.NRO_VEN', $nro)->orderBy('d.id')
                ->get(['d.*', 'm.DES_MED', 'l.NRO_LOT', 'l.FECVEN_LOT']);
        }
        return $v;
    }

    /** Consumos de farmacia cargados a una internación (para la cuenta del hospitalizado). */
    public function consumosInternacion(string $codInt)
    {
        return DB::table('VENTA')->where('COD_INT', $codInt)->where('EST_VEN', 'Emitida')->orderBy('FEC_VEN')->get();
    }
}
