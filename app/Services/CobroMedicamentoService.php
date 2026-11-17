<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

class CobroMedicamentoService
{
    public function precios()
    {
        return DB::table('MEDICAMENTO as m')->select('m.COD_MED', 'm.DES_MED')
            ->selectSub(DB::table('FARMACIA_PRECIO as p')->select('p.PRECIO')->whereColumn('p.COD_MED', 'm.COD_MED')->orderByDesc('p.id')->limit(1), 'PRECIO')
            ->orderBy('m.DES_MED')->get();
    }

    public function fijarPrecio(string $medicamento, string $precio, string $empleado): void
    {
        DB::transaction(function () use ($medicamento, $precio, $empleado) {
            $med = DB::table('MEDICAMENTO')->where('COD_MED', $medicamento)->lockForUpdate()->first();
            if (!$med || !$empleado || (float) $precio <= 0) { throw new DomainException('El medicamento o el precio no son válidos.'); }
            DB::table('FARMACIA_PRECIO')->insert(['COD_MED' => $medicamento, 'PRECIO' => $precio, 'COD_EMP' => $empleado, 'FECHA' => now()]);
            Auditoria::registrar('Precio medicamento', 'Alta', 'DOM04', 'MEDICAMENTO', $medicamento, 'Precio actualizado: Bs '.$precio);
        });
    }

    public function pendientes(string $buscar = '')
    {
        return DB::table('DETALLE_RECETA as d')->join('FARMACIA_CANTIDAD as fc', 'fc.COD_DRE', '=', 'd.COD_DRE')
            ->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')->where('r.EST_REC', 'Activa')->whereColumn('d.CANENT_DRE', '<', 'fc.CANTIDAD')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('p.CI_PAC', 'like', '%'.$buscar.'%')->orWhere('p.NOM_PAC', 'like', '%'.$buscar.'%')->orWhere('p.APE_PAC', 'like', '%'.$buscar.'%')->orWhere('r.COD_REC', 'like', '%'.$buscar.'%')->orWhere('m.DES_MED', 'like', '%'.$buscar.'%')))
            ->select('d.COD_DRE', 'd.COD_REC', 'd.CANENT_DRE', 'fc.CANTIDAD', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'm.DES_MED')
            ->selectSub(DB::table('FARMACIA_PRECIO as f')->select('f.PRECIO')->whereColumn('f.COD_MED', 'd.COD_MED')->orderByDesc('f.id')->limit(1), 'PRECIO')
            ->orderByDesc('r.id')->limit(100)->get();
    }

    public function cobrar(string $receta, string $detalle, int $cantidad, string $metodo, object $actor, string $precioEsperado, string $token): string
    {
        return DB::transaction(function () use ($receta, $detalle, $cantidad, $metodo, $actor, $precioEsperado, $token) {
            $caja = DB::table('CAJA')->where('COD_EMP', $actor->COD_EMP)->where('EST_CAJ', 'Abierta')->lockForUpdate()->first();
            if (!$caja || !\Carbon\Carbon::parse($caja->FECAPE_CAJ)->isToday() || !DB::table('METODO_PAGO')->where('COD_MET', $metodo)->exists()) {
                throw new DomainException('Necesitas una caja abierta de hoy y un método de pago válido.');
            }
            $anterior = DB::table('CAJA_MEDICAMENTO_SOLICITUD as s')->join('CAJA_MEDICAMENTO as m', 'm.COD_CME', '=', 's.COD_CME')->where('s.TOKEN', $token)->first(['m.COD_CME', 'm.COD_CAJ', 'm.COD_DRE', 'm.ESTADO']);
            if ($anterior) {
                if ($anterior->COD_CAJ !== $caja->COD_CAJ || $anterior->COD_DRE !== $detalle || $anterior->ESTADO !== 'Confirmado') { throw new DomainException('La solicitud ya fue utilizada. Actualiza la pantalla.'); }
                return $anterior->COD_CME;
            }
            DB::table('RECETA')->where('COD_REC', $receta)->lockForUpdate()->first();
            $d = DB::table('DETALLE_RECETA as d')->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
                ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('d.COD_DRE', $detalle)->where('d.COD_REC', $receta)->first(['d.COD_MED', 'h.COD_PAC']);
            if (!$d) { throw new DomainException('No se encontró el medicamento de la receta.'); }
            DB::table('MEDICAMENTO')->where('COD_MED', $d->COD_MED)->lockForUpdate()->first();
            $precio = DB::table('FARMACIA_PRECIO')->where('COD_MED', $d->COD_MED)->orderByDesc('id')->value('PRECIO');
            if (!$precio || (float) $precio <= 0) { throw new DomainException('El medicamento no tiene un precio registrado.'); }
            if ((int) round((float) $precioEsperado * 100) !== (int) round((float) $precio * 100)) { throw new DomainException('El precio cambió. Actualiza la pantalla y confirma el nuevo importe.'); }
            $stock = DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $d->COD_MED)->where('FECVEN_LOT', '>=', today()->toDateString())
                ->where('CANDIS_LOT', '>', 0)->lockForUpdate()->get(['CANDIS_LOT'])->sum('CANDIS_LOT');
            if ($stock < $cantidad) { throw new DomainException('No hay stock vigente suficiente para la cantidad solicitada.'); }
            $despacho = app(FarmaciaService::class)->despacharItem($receta, $detalle, $cantidad, $actor);
            $entregado = (int) $despacho['entregado'];
            $monto = round($entregado * (float) $precio, 2);
            do { $codigo = 'CME'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('CAJA_MEDICAMENTO')->where('COD_CME', $codigo)->exists());
            DB::table('CAJA_MEDICAMENTO')->insert(['COD_CME' => $codigo, 'COD_PAC' => $d->COD_PAC, 'COD_MED' => $d->COD_MED, 'COD_DRE' => $detalle, 'COD_CAJ' => $caja->COD_CAJ, 'COD_MET' => $metodo, 'CANTIDAD' => $entregado, 'PRECIO' => $precio, 'MONTO' => $monto, 'FECHA' => now(), 'ESTADO' => 'Confirmado']);
            DB::table('CAJA_MEDICAMENTO_SOLICITUD')->insert(['TOKEN' => $token, 'COD_CME' => $codigo]);
            foreach ($despacho['lotes'] as $lote) {
                if (!preg_match('/^(.{1,10}) x(\d+)$/', $lote, $partes)) { throw new DomainException('No se pudo registrar el detalle de lotes.'); }
                DB::table('CAJA_MEDICAMENTO_LOTE')->insert(['COD_CME' => $codigo, 'COD_LOT' => $partes[1], 'CANTIDAD' => (int) $partes[2]]);
            }
            Auditoria::registrar('Cobrar medicamento', 'Alta', 'DOM04', 'CAJA_MEDICAMENTO', $codigo, "Receta {$receta}, {$entregado} unidades, Bs {$monto}", codPac: $d->COD_PAC);
            return $codigo;
        });
    }

    public function cobros(string $caja)
    {
        return DB::table('CAJA_MEDICAMENTO as x')->join('PACIENTE as p', 'p.COD_PAC', '=', 'x.COD_PAC')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'x.COD_MED')
            ->join('METODO_PAGO as t', 't.COD_MET', '=', 'x.COD_MET')->leftJoin('CAJA_MEDICAMENTO_ANULACION as a', 'a.COD_CME', '=', 'x.COD_CME')->where('x.COD_CAJ', $caja)->orderByDesc('x.id')
            ->get(['x.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'm.DES_MED', 't.DES_MET', 'a.MOTIVO']);
    }

    public function anular(string $codigo, string $motivo, object $actor): void
    {
        DB::transaction(function () use ($codigo, $motivo, $actor) {
            $caja = DB::table('CAJA')->where('COD_EMP', $actor->COD_EMP)->where('EST_CAJ', 'Abierta')->lockForUpdate()->first();
            $cobro = DB::table('CAJA_MEDICAMENTO')->where('COD_CME', $codigo)->lockForUpdate()->first();
            if (!$caja || !$cobro || $cobro->COD_CAJ !== $caja->COD_CAJ || $cobro->ESTADO !== 'Confirmado' || !\Carbon\Carbon::parse($cobro->FECHA)->isToday()) {
                throw new DomainException('Solo puedes anular cobros de hoy de tu caja abierta.');
            }
            $detalle = DB::table('DETALLE_RECETA')->where('COD_DRE', $cobro->COD_DRE)->first();
            if (!$detalle) { throw new DomainException('No se encontró la receta asociada.'); }
            $receta = DB::table('RECETA')->where('COD_REC', $detalle->COD_REC)->lockForUpdate()->first();
            $detalle = DB::table('DETALLE_RECETA')->where('COD_DRE', $cobro->COD_DRE)->lockForUpdate()->first();
            $lotes = DB::table('CAJA_MEDICAMENTO_LOTE')->where('COD_CME', $codigo)->get();
            if ((int) $lotes->sum('CANTIDAD') !== (int) $cobro->CANTIDAD || (int) $detalle->CANENT_DRE < (int) $cobro->CANTIDAD) {
                throw new DomainException('No se pudo verificar el despacho original. Solicita revisión administrativa.');
            }
            foreach ($lotes as $l) {
                $stock = DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $l->COD_LOT)->lockForUpdate()->first();
                if (!$stock || (int) $stock->CANDIS_LOT + (int) $l->CANTIDAD > (int) $stock->CANING_LOT) {
                    throw new DomainException('No se puede devolver el lote automáticamente. Solicita revisión administrativa.');
                }
                DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $l->COD_LOT)->update(['CANDIS_LOT' => (int) $stock->CANDIS_LOT + (int) $l->CANTIDAD]);
            }
            DB::table('DETALLE_RECETA')->where('COD_DRE', $cobro->COD_DRE)->update(['CANENT_DRE' => (int) $detalle->CANENT_DRE - (int) $cobro->CANTIDAD]);
            if ($receta && $receta->EST_REC === 'Completada') { DB::table('RECETA')->where('COD_REC', $detalle->COD_REC)->update(['EST_REC' => 'Activa']); }
            DB::table('CAJA_MEDICAMENTO')->where('COD_CME', $codigo)->update(['ESTADO' => 'Anulado']);
            DB::table('CAJA_MEDICAMENTO_ANULACION')->insert(['COD_CME' => $codigo, 'COD_EMP' => $actor->COD_EMP, 'FECHA' => now(), 'MOTIVO' => $motivo]);
            Auditoria::registrar('Anular medicamento', 'Alta', 'DOM04', 'CAJA_MEDICAMENTO', $codigo, 'Anulación y devolución a lotes. Motivo: '.$motivo, codPac: $cobro->COD_PAC);
        });
    }
}
