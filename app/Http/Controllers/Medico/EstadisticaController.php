<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Estadísticas del médico: sus pacientes y atenciones, y qué especialidades se frecuentan en la clínica. */
class EstadisticaController extends Controller
{
    public function __construct(private ReporteService $reportes)
    {
    }

    public function index(Request $request)
    {
        $codEmp = auth()->user()->COD_EMP;
        abort_if(!$codEmp, 403, 'Tu usuario no está vinculado a un empleado.');

        $medico = DB::table('EMPLEADO as e')->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->where('e.COD_EMP', $codEmp)->first(['e.*', 'es.DES_ESP']);

        $f = $this->reportes->filtro($request, $codEmp);
        // Mismo periodo para toda la clínica y para su especialidad
        $clinica = array_merge($f, ['medico' => '', 'especialidad' => '']);
        $suEspecialidad = array_merge($f, ['medico' => '', 'especialidad' => $medico->COD_ESP ?? '']);

        $kpi = $this->reportes->indicadores($f);
        $kpiEsp = $this->reportes->indicadores($suEspecialidad);

        return view('medico.estadisticas', [
            'medico'      => $medico,
            'f'           => $f,
            'catalogos'   => $this->reportes->catalogos(),
            'kpi'         => $kpi,
            'participacion' => $kpiEsp['atendidas'] > 0 ? round($kpi['atendidas'] * 100 / $kpiEsp['atendidas'], 1) : null,
            'serie'       => $this->reportes->serie($f),
            'topPacientes' => $this->reportes->topPacientes($f, 10),
            'perfil'      => $this->reportes->perfilPacientes($f),
            'horas'       => $this->reportes->horasPico($f),
            'espPorMes'   => $this->reportes->especialidadPorMes($clinica),
            'porEspecialidad' => $this->reportes->porEspecialidad($clinica),
            'colegas'     => $this->reportes->porMedico($suEspecialidad),
            'diagnosticos' => DB::table('CONSULTA as co')->join('CITA as c', 'c.COD_CIT', '=', 'co.COD_CIT')
                ->where('co.COD_EMP', $codEmp)->whereBetween('c.FEC_CIT', [$f['desde'], $f['hasta']])
                ->groupBy('co.DIA_CNS')->selectRaw('co.DIA_CNS AS dia, COUNT(*) AS n')
                ->orderByDesc('n')->limit(8)->pluck('n', 'dia'),
        ]);
    }
}
