<?php
namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $usuario = auth()->user();

        $paciente = null;

        if ($usuario->COD_PAC) {
            $paciente = DB::table('PACIENTE')
                ->where('COD_PAC', $usuario->COD_PAC)
                ->first();
        }


$citas = collect();

if ($paciente) {
    $citas = DB::table('CITA')
        ->where('COD_PAC', $paciente->COD_PAC)
        ->orderBy('FEC_CIT', 'desc')
        ->orderBy('HOR_CIT', 'desc')
        ->get();
}

$recetas = collect();

if ($paciente) {
    $recetas = DB::table('RECETA as r')
        ->join('CONSULTA as co', 'r.COD_CNS', '=', 'co.COD_CNS')
        ->join('CITA as ci', 'co.COD_CIT', '=', 'ci.COD_CIT')
        ->where('ci.COD_PAC', $paciente->COD_PAC)
        ->select(
            'r.COD_REC',
            'r.FEC_REC',
            'r.EST_REC',
            'r.OBS_REC'
        )
        ->orderBy('r.FEC_REC', 'desc')
        ->get();
}

      return view('paciente.dashboard', [
    'paciente' => $paciente,
    'citas' => $citas,
    'recetas' => $recetas
]);
}
}