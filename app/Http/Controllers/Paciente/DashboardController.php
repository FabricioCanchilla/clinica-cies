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


      return view('paciente.dashboard', [
    'paciente' => $paciente,
    'citas' => $citas
]);
}
}