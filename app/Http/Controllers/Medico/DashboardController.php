<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** RF021: agenda del médico con sus citas reales (estados de RNF011). */
class DashboardController extends Controller
{
    public function index()
    {
        $codEmp = auth()->user()->COD_EMP;
        abort_if(!$codEmp, 403, 'Tu usuario no está vinculado a un empleado.');

        // Rango navegable en el calendario: 3 meses hacia atrás y 3 hacia adelante
        $desde = Carbon::today()->subMonths(3)->startOfMonth()->toDateString();
        $hasta = Carbon::today()->addMonths(3)->endOfMonth()->toDateString();

        $citas = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')
            ->where('c.COD_EMP', $codEmp)
            ->whereBetween('c.FEC_CIT', [$desde, $hasta])
            ->orderBy('c.FEC_CIT')->orderBy('c.HOR_CIT')
            ->get(['c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT',
                   'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 's.NOM_SRV', 'k.NOM_CON'])
            ->map(fn ($c) => [
                'codigo'      => $c->COD_CIT,
                'fecha'       => $c->FEC_CIT,
                'hora'        => substr($c->HOR_CIT, 0, 5),
                'paciente'    => "{$c->NOM_PAC} {$c->APE_PAC}",
                'ci'          => $c->CI_PAC,
                'motivo'      => $c->MOT_CIT,
                'servicio'    => $c->NOM_SRV,
                'consultorio' => $c->NOM_CON,
                'estado'      => $c->EST_CIT,
            ])
            ->values();

        $hoy = $citas->where('fecha', Carbon::today()->toDateString())
            ->whereNotIn('estado', ['Expirada'])->values();

        return view('medico.dashboard', [
            'citas'   => $citas,
            'hoy'     => $hoy,
            'estados' => ['Pendiente de pago', 'Confirmada', 'Atendida', 'Cancelada', 'Expirada', 'No asistio'],
        ]);
    }
}
