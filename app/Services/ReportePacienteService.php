<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporte integral de un paciente: todo lo que hizo en la clínica (citas, consultas, recetas,
 * exámenes, pagos, internaciones e insumos entregados), con un resumen de su actividad.
 */
class ReportePacienteService
{
    public function datos(string $codPac): ?array
    {
        $p = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();
        if (!$p) {
            return null;
        }
        $p->EDAD = Carbon::parse($p->FECNAC_PAC)->age;

        $historia = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $codPac)->first();
        $alergias = $historia ? DB::table('ALERGIA')->where('COD_HIS', $historia->COD_HIS)->get() : collect();
        $antecedentes = $historia ? DB::table('ANTECEDENTE')->where('COD_HIS', $historia->COD_HIS)->get() : collect();

        $citas = DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->leftJoin('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->leftJoin('CONSULTA as co', 'co.COD_CIT', '=', 'c.COD_CIT')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('c.FEC_CIT')->orderByDesc('c.HOR_CIT')
            ->get(['c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT', 'e.COD_EMP', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP',
                   'es.DES_ESP', 's.NOM_SRV', 's.PRE_SRV', 'co.COD_CNS', 'co.DIA_CNS', 'co.PLA_CNS']);

        $recetas = DB::table('RECETA as r')
            ->join('CONSULTA as co', 'co.COD_CNS', '=', 'r.COD_CNS')
            ->join('CITA as c', 'c.COD_CIT', '=', 'co.COD_CIT')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('r.FEC_REC')
            ->get(['r.COD_REC', 'r.FEC_REC', 'r.EST_REC', 'r.OBS_REC', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);
        $detalles = DB::table('DETALLE_RECETA as d')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->whereIn('d.COD_REC', $recetas->pluck('COD_REC'))
            ->get(['d.COD_REC', 'm.DES_MED', 'd.DOS_DRE', 'd.FRE_DRE', 'd.DUR_DRE'])
            ->groupBy('COD_REC');
        $recetas->each(fn ($r) => $r->ITEMS = $detalles[$r->COD_REC] ?? collect());

        $examenes = DB::table('ORDEN_EXAMEN as o')
            ->join('CONSULTA as co', 'co.COD_CNS', '=', 'o.COD_CNS')
            ->join('CITA as c', 'c.COD_CIT', '=', 'co.COD_CIT')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
            ->leftJoin('RESULTADO_EXAMEN as re', 're.COD_ORD', '=', 'o.COD_ORD')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('o.FEC_ORD')
            ->get(['o.COD_ORD', 'o.FEC_ORD', 'o.EST_ORD', 'o.PRI_ORD', 'o.IND_ORD', 's.NOM_SRV', 're.EST_RES', 're.DES_RES', 're.CRI_RES']);

        $pagos = DB::table('PAGO as pg')
            ->join('CITA as c', 'c.COD_CIT', '=', 'pg.COD_CIT')
            ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'pg.COD_MET')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('pg.FEC_PAG')
            ->get(['pg.COD_PAG', 'pg.FEC_PAG', 'pg.MON_PAG', 'pg.TIP_PAG', 'pg.EST_PAG', 'm.DES_MET', 'c.COD_CIT', 's.NOM_SRV']);

        $internaciones = DB::table('INTERNACION as i')
            ->join('CAMA as k', 'k.COD_CAM', '=', 'i.COD_CAM')
            ->join('SALA as s', 's.COD_SAL', '=', 'k.COD_SAL')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->where('i.COD_PAC', $codPac)
            ->orderByDesc('i.FECING_INT')
            ->get(['i.*', 'k.NRO_CAM', 's.NOM_SAL', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);

        $insumos = DB::table('INSUMO_TERCERO as it')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'it.COD_MED')
            ->join('ENTREGANTE as en', 'en.COD_ENT', '=', 'it.COD_ENT')
            ->where('it.COD_PAC', $codPac)
            ->orderByDesc('it.FEC_INS')
            ->get(['it.COD_INS', 'it.FEC_INS', 'it.CAN_INS', 'it.EST_INS', 'm.DES_MED', 'en.NOM_ENT', 'en.APE_ENT']);

        $atendidas = $citas->where('EST_CIT', 'Atendida');
        $porEspecialidad = $atendidas->groupBy(fn ($c) => $c->DES_ESP ?? 'Sin especialidad')
            ->map->count()->sortDesc();
        $medicos = $atendidas->groupBy('COD_EMP')->map(fn ($g) => [
            'nombre' => trim($g->first()->TIT_EMP . ' ' . $g->first()->NOM_EMP . ' ' . $g->first()->APE_EMP),
            'especialidad' => $g->first()->DES_ESP,
            'visitas' => $g->count(),
        ])->sortByDesc('visitas')->values();

        // Visitas atendidas por mes en los últimos 12 meses
        $porMes = [];
        for ($d = Carbon::today()->startOfMonth()->subMonths(11); $d->lte(Carbon::today()); $d->addMonth()) {
            $clave = $d->format('Y-m');
            $porMes[ReporteService::MESES[$d->month] . ' ' . $d->format('y')] =
                $atendidas->filter(fn ($c) => str_starts_with($c->FEC_CIT, $clave))->count();
        }

        $proxima = $citas->filter(fn ($c) => in_array($c->EST_CIT, ['Confirmada', 'Pendiente de pago'], true)
            && $c->FEC_CIT >= Carbon::today()->toDateString())->sortBy(fn ($c) => $c->FEC_CIT . $c->HOR_CIT)->first();

        $resumen = [
            'citas'          => $citas->count(),
            'atendidas'      => $atendidas->count(),
            'canceladas'     => $citas->where('EST_CIT', 'Cancelada')->count(),
            'inasistencias'  => $citas->where('EST_CIT', 'No asistio')->count(),
            'primera'        => $atendidas->min('FEC_CIT'),
            'ultima'         => $atendidas->max('FEC_CIT'),
            'proxima'        => $proxima,
            'pagado'         => round((float) $pagos->where('EST_PAG', 'Confirmado')->sum('MON_PAG'), 2),
            'recetas'        => $recetas->count(),
            'examenes'       => $examenes->count(),
            'internaciones'  => $internaciones->count(),
            'especialidad'   => $porEspecialidad->keys()->first(),
        ];

        return compact('p', 'historia', 'alergias', 'antecedentes', 'citas', 'recetas', 'examenes', 'pagos',
            'internaciones', 'insumos', 'resumen', 'porEspecialidad', 'medicos', 'porMes');
    }
}
