<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CajaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Secciones de supervisión del administrador (solo lectura): Citas, Caja e Internación.
 * Las operaciones las hacen los roles responsables (recepción, caja, enfermería); aquí se consultan.
 */
class SupervisionController extends Controller
{
    public function citas(Request $request)
    {
        $desde  = $request->query('desde', Carbon::today()->subDays(7)->toDateString());
        $hasta  = $request->query('hasta', Carbon::today()->addDays(30)->toDateString());
        $estado = $request->query('estado', '');
        $medico = $request->query('medico', '');

        $q = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->whereBetween('c.FEC_CIT', [$desde, $hasta]);
        if ($estado !== '') {
            $q->where('c.EST_CIT', $estado);
        }
        if ($medico !== '') {
            $q->where('c.COD_EMP', $medico);
        }

        $citas = (clone $q)->orderByDesc('c.FEC_CIT')->orderBy('c.HOR_CIT')->limit(300)
            ->get(['c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT',
                   'p.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV']);
        $porEstado = (clone $q)->groupBy('c.EST_CIT')->selectRaw('c.EST_CIT AS est, COUNT(*) AS total')->pluck('total', 'est');

        return view('admin.supervision.citas', [
            'citas'     => $citas,
            'porEstado' => $porEstado,
            'medicos'   => DB::table('EMPLEADO')->whereNotNull('COD_ESP')->orderBy('APE_EMP')->get(['COD_EMP', 'TIT_EMP', 'NOM_EMP', 'APE_EMP']),
            'estados'   => ['Pendiente de pago', 'Confirmada', 'Atendida', 'Cancelada', 'Expirada', 'No asistio'],
            'filtro'    => compact('desde', 'hasta', 'estado', 'medico'),
        ]);
    }

    public function caja(CajaService $cajaService)
    {
        $cajas = DB::table('CAJA as k')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'k.COD_EMP')
            ->orderByDesc('k.FECAPE_CAJ')->limit(50)
            ->get(['k.*', 'e.NOM_EMP', 'e.APE_EMP'])
            ->map(function ($k) use ($cajaService) {
                $r = $cajaService->resumen($k);
                $k->TOTAL = $r['total'];
                $k->ESPERADO = $r['esperado'];
                $k->ANULADOS = $r['anulados'];
                $k->DIFERENCIA = $k->MONCIE_CAJ !== null ? round((float) $k->MONCIE_CAJ - $r['esperado'], 2) : null;
                return $k;
            });

        $hoy = Carbon::today()->toDateString();
        $comprobantes = DB::table('COMPROBANTE as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->whereDate('c.FEC_COM', $hoy)
            ->orderByDesc('c.FEC_COM')
            ->get(['c.NRO_COM', 'c.FEC_COM', 'c.MONTO_COM', 'c.EST_COM', 'c.COD_CIT', 'c.COD_CAJ', 'p.NOM_PAC', 'p.APE_PAC']);

        return view('admin.supervision.caja', compact('cajas', 'comprobantes'));
    }

    public function internacion()
    {
        $camas = DB::table('CAMA as c')
            ->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->leftJoin('INTERNACION as i', function ($j) {
                $j->on('i.COD_CAM', '=', 'c.COD_CAM')->where('i.EST_INT', 'Internado');
            })
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')
            ->get(['c.COD_CAM', 'c.NRO_CAM', 'c.EST_CAM', 's.NOM_SAL', 's.UBI_SAL',
                   'i.COD_INT', 'i.FECING_INT', 'i.MOT_INT', 'i.AIS_INT', 'p.NOM_PAC', 'p.APE_PAC']);

        // AJ08: limpieza pendiente o en proceso de cada cama
        $limpiezas = DB::table('LIMPIEZA_CAMA')->where('EST_LIM', '<>', 'Completada')->get()->keyBy('COD_CAM');
        $camas->each(fn ($c) => $c->LIMPIEZA = $limpiezas[$c->COD_CAM] ?? null);

        $internaciones = DB::table('INTERNACION as i')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->orderByDesc('i.FECING_INT')->limit(50)
            ->get(['i.*', 'p.NOM_PAC', 'p.APE_PAC', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);

        return view('admin.supervision.internacion', [
            'camas'         => $camas,
            'internaciones' => $internaciones,
            'porEstado'     => $camas->countBy('EST_CAM'),
        ]);
    }
}
