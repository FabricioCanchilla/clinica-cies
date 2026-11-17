<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

class CobroExamenService
{
    public function pendientes(string $buscar = '')
    {
        return DB::table('ORDEN_EXAMEN as o')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')->where('o.EST_ORD', '!=', 'Cancelado')
            ->whereNotExists(fn ($q) => $q->from('CAJA_EXAMEN as x')->whereColumn('x.COD_ORD', 'o.COD_ORD')->where('x.ESTADO', 'Confirmado'))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('p.CI_PAC', 'like', '%'.$buscar.'%')->orWhere('p.NOM_PAC', 'like', '%'.$buscar.'%')->orWhere('p.APE_PAC', 'like', '%'.$buscar.'%')->orWhere('o.COD_ORD', 'like', '%'.$buscar.'%')))
            ->orderByDesc('o.FEC_ORD')->limit(100)->get(['o.COD_ORD', 'o.FEC_ORD', 'o.EST_ORD', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 's.NOM_SRV', 's.PRE_SRV']);
    }

    public function cobros(string $caja)
    {
        return DB::table('CAJA_EXAMEN as x')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'x.COD_ORD')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
            ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'x.COD_MET')->where('x.COD_CAJ', $caja)->orderByDesc('x.id')
            ->get(['x.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 's.NOM_SRV', 'm.DES_MET']);
    }

    public function cobrar(string $orden, string $metodo, string $empleado, string $precioEsperado): string
    {
        return DB::transaction(function () use ($orden, $metodo, $empleado, $precioEsperado) {
            $caja = DB::table('CAJA')->where('COD_EMP', $empleado)->where('EST_CAJ', 'Abierta')->lockForUpdate()->first();
            if (!$caja || !\Carbon\Carbon::parse($caja->FECAPE_CAJ)->isToday()) {
                throw new DomainException('Necesitas una caja abierta de hoy.');
            }
            $o = DB::table('ORDEN_EXAMEN as o')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
                ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
                ->where('o.COD_ORD', $orden)->lockForUpdate()->first(['o.COD_ORD', 'o.EST_ORD', 's.PRE_SRV', 'h.COD_PAC']);
            if (!$o || $o->EST_ORD === 'Cancelado' || (float) $o->PRE_SRV <= 0 || !DB::table('METODO_PAGO')->where('COD_MET', $metodo)->exists()) {
                throw new DomainException('La orden, el precio o el método de pago no son válidos.');
            }
            if ((int) round((float) $precioEsperado * 100) !== (int) round((float) $o->PRE_SRV * 100)) { throw new DomainException('La tarifa cambió. Actualiza la pantalla y confirma el nuevo importe.'); }
            if (DB::table('CAJA_EXAMEN')->where('COD_ORD', $orden)->where('ESTADO', 'Confirmado')->exists()) {
                throw new DomainException('Esta orden ya tiene un cobro confirmado.');
            }
            do { $codigo = 'CEX'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('CAJA_EXAMEN')->where('COD_CEX', $codigo)->exists());
            DB::table('CAJA_EXAMEN')->insert(['COD_CEX' => $codigo, 'COD_ORD' => $orden, 'COD_CAJ' => $caja->COD_CAJ, 'COD_MET' => $metodo, 'FECHA' => now(), 'MONTO' => $o->PRE_SRV, 'ESTADO' => 'Confirmado']);
            Auditoria::registrar('Cobrar examen', 'Media', 'DOM04', 'CAJA_EXAMEN', $codigo, "Orden {$orden}, Bs {$o->PRE_SRV}", codPac: $o->COD_PAC);
            return $codigo;
        });
    }

    public function anular(string $codigo, string $motivo, string $empleado): void
    {
        DB::transaction(function () use ($codigo, $motivo, $empleado) {
            $caja = DB::table('CAJA')->where('COD_EMP', $empleado)->where('EST_CAJ', 'Abierta')->lockForUpdate()->first();
            $cobro = DB::table('CAJA_EXAMEN')->where('COD_CEX', $codigo)->lockForUpdate()->first();
            if (!$caja || !$cobro || $cobro->COD_CAJ !== $caja->COD_CAJ || $cobro->ESTADO !== 'Confirmado' || !\Carbon\Carbon::parse($cobro->FECHA)->isToday()) {
                throw new DomainException('Solo puedes anular cobros de hoy de tu caja abierta.');
            }
            DB::table('CAJA_EXAMEN')->where('COD_CEX', $codigo)->update(['ESTADO' => 'Anulado', 'MOTIVO' => $motivo]);
            $paciente = DB::table('ORDEN_EXAMEN as o')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('o.COD_ORD', $cobro->COD_ORD)->value('h.COD_PAC');
            Auditoria::registrar('Anular examen', 'Alta', 'DOM04', 'CAJA_EXAMEN', $codigo, 'Motivo: '.$motivo, codPac: $paciente);
        });
    }
}
