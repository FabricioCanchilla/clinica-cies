<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\CitaService;
use App\Services\InsumoService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private CitaService $citas, private InsumoService $insumos)
    {
    }

    public function index(): View
    {
        $usuario = auth()->user();

        $paciente = null;

        if ($usuario->COD_PAC) {
            $paciente = DB::table('PACIENTE')
                ->where('COD_PAC', $usuario->COD_PAC)
                ->first();
        }

        // Las citas salen de CitaService: traen médico, servicio y el vencimiento del bloqueo
        $citas = collect();

        if ($paciente) {
            $citas = $this->citas->citasDelPaciente($paciente->COD_PAC);
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

        $historial = collect();

        if ($paciente) {
            $historial = DB::table('CONSULTA as co')
                ->join('HISTORIA_CLINICA as h', 'co.COD_HIS', '=', 'h.COD_HIS')
                ->where('h.COD_PAC', $paciente->COD_PAC)
                ->select(
                    'co.FEC_CNS',
                    'co.DIA_CNS',
                    'co.PLA_CNS'
                )
                ->orderBy('co.FEC_CNS', 'desc')
                ->get();
        }

        // RF016: comprobantes de insumos entregados por familiares
        $insumos = $paciente ? $this->insumos->comprobantesDelPaciente($paciente->COD_PAC) : collect();

        return view('paciente.dashboard', [
            'insumos'   => $insumos,
            'paciente'  => $paciente,
            'citas'     => $citas,
            'recetas'   => $recetas,
            'historial' => $historial,
        ]);
    }
}
