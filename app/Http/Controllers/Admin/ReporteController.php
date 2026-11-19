<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Dashboard del administrador y módulo de Reportes (con gráficos y filtros por periodo). */
class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes)
    {
    }

    /** Dashboard: totales del sistema y, sobre todo, cómo se está usando la clínica. */
    public function dashboard(Request $request)
    {
        $mes = $this->reportes->filtro(new Request(['periodo' => 'mes']));
        // Mismo tramo del mes anterior (del 1 al mismo día) para comparar de forma justa
        $mesAnterior = $this->reportes->filtro(new Request([
            'periodo' => 'rango',
            'desde'   => Carbon::today()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'hasta'   => Carbon::today()->subMonthNoOverflow()->toDateString(),
        ]));
        // Este mes, hasta hoy (las citas futuras confirmadas no son atenciones todavía)
        $mesHastaHoy = $this->reportes->filtro(new Request([
            'periodo' => 'rango',
            'desde'   => Carbon::today()->startOfMonth()->toDateString(),
            'hasta'   => Carbon::today()->toDateString(),
        ]));
        $ultimos30 = $this->reportes->filtro(new Request([
            'periodo' => 'rango',
            'desde'   => Carbon::today()->subDays(29)->toDateString(),
            'hasta'   => Carbon::today()->toDateString(),
        ]));
        $anio = $this->reportes->filtro(new Request([
            'periodo' => 'rango',
            'desde'   => Carbon::today()->startOfMonth()->subMonths(11)->toDateString(),
            'hasta'   => Carbon::today()->endOfMonth()->toDateString(),
        ]));

        $kpi = $this->reportes->indicadores($mes);
        $kpiHoy = $this->reportes->indicadores($mesHastaHoy);
        $kpi['vsAtendidas'] = $kpiHoy['atendidas'];
        $kpi['vsIngresos'] = $kpiHoy['ingresos'];
        $kpiAnterior = $this->reportes->indicadores($mesAnterior);

        $totales = [
            'Usuarios activos' => DB::table('USUARIO')->where('EST_USR', 'Activo')->count(),
            'Empleados'        => DB::table('EMPLEADO')->count(),
            'Médicos'          => DB::table('EMPLEADO')->whereNotNull('COD_ESP')->count(),
            'Pacientes'        => DB::table('PACIENTE')->count(),
            'Especialidades'   => DB::table('ESPECIALIDAD')->count(),
            'Citas registradas' => DB::table('CITA')->count(),
        ];

        $ultimos = DB::table('USUARIO')
            ->join('ROL', 'USUARIO.COD_ROL', '=', 'ROL.COD_ROL')
            ->select('USUARIO.USR_USR', 'USUARIO.EST_USR', 'USUARIO.FECULT_USR', 'ROL.DES_ROL')
            ->orderByDesc('USUARIO.id')->limit(5)->get();

        return view('admin.dashboard', [
            'kpi'           => $kpi,
            'kpiAnterior'   => $kpiAnterior,
            'mes'           => $mes,
            'totales'       => $totales,
            'serie30'       => $this->reportes->serie($ultimos30),
            'porEspecialidad' => $this->reportes->porEspecialidad($mes),
            'porMedico'     => $this->reportes->porMedico($mes),
            'topPacientes'  => $this->reportes->topPacientes($anio, 10),
            'espPorMes'     => $this->reportes->especialidadPorMes($mes),
            'ingresos'      => $this->reportes->ingresosMensuales(),
            'perfil'        => $this->reportes->perfilPacientes($anio),
            'camas'         => $this->reportes->camas(),
            'ultimos'       => $ultimos,
        ]);
    }

    /** Módulo Reportes: todo filtrable por día, semana, mes, año o rango, especialidad y médico. */
    public function index(Request $request)
    {
        $f = $this->reportes->filtro($request);

        return view('admin.reportes.index', $this->datos($f) + [
            'f'         => $f,
            'catalogos' => $this->reportes->catalogos(),
        ]);
    }

    private function datos(array $f): array
    {
        return [
            'kpi'             => $this->reportes->indicadores($f),
            'serie'           => $this->reportes->serie($f),
            'porEspecialidad' => $this->reportes->porEspecialidad($f),
            'porMedico'       => $this->reportes->porMedico($f),
            'topPacientes'    => $this->reportes->topPacientes($f, 15),
            'espPorMes'       => $this->reportes->especialidadPorMes($f),
            'perfil'          => $this->reportes->perfilPacientes($f),
            'horas'           => $this->reportes->horasPico($f),
        ];
    }

    /** Exporta el reporte filtrado a Excel (una hoja por tabla). Queda auditado (RN47). */
    public function excel(Request $request)
    {
        $f = $this->reportes->filtro($request);
        $d = $this->datos($f);

        Auditoria::registrar('Exportar reporte', 'Baja', 'DOM01', 'REPORTE', 'Atenciones',
            'Reporte de atenciones en Excel: ' . $f['etiqueta']
            . ($f['especialidad'] !== '' ? ", especialidad {$f['especialidad']}" : '')
            . ($f['medico'] !== '' ? ", médico {$f['medico']}" : ''));

        $ruta = tempnam(sys_get_temp_dir(), 'rep_');
        $w = new Writer();
        $w->openToFile($ruta);

        $hoja = $w->getCurrentSheet();
        $hoja->setName('Resumen');
        $w->addRow(Row::fromValues(['Reporte de atenciones · Clínica CIES La Paz']));
        $w->addRow(Row::fromValues(['Periodo', $f['etiqueta']]));
        $w->addRow(Row::fromValues(['Generado', now()->format('d/m/Y H:i') . ' por ' . (auth()->user()->USR_USR ?? '—')]));
        $w->addRow(Row::fromValues([]));
        foreach ([
            'Citas agendadas'      => $d['kpi']['agendadas'],
            'Atenciones (visitas)' => $d['kpi']['atendidas'],
            'Pacientes atendidos'  => $d['kpi']['pacientes'],
            'Pacientes nuevos'     => $d['kpi']['nuevos'],
            'Inasistencias'        => $d['kpi']['noAsistio'],
            'Cancelaciones'        => $d['kpi']['canceladas'],
            'Asistencia (%)'       => $d['kpi']['asistencia'],
            'Ingresos (Bs)'        => $d['kpi']['ingresos'],
        ] as $k => $v) {
            $w->addRow(Row::fromValues([$k, $v]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Por especialidad');
        $w->addRow(Row::fromValues(['Especialidad', 'Atenciones', 'Pacientes distintos', 'Inasistencias', 'Citas agendadas', 'Ingresos (Bs)']));
        foreach ($d['porEspecialidad'] as $r) {
            $w->addRow(Row::fromValues([$r->DES_ESP, $r->ATENDIDAS, $r->PACIENTES, $r->INASISTENCIAS, (int) $r->AGENDADAS, $r->INGRESOS]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Por médico');
        $w->addRow(Row::fromValues(['Médico', 'Especialidad', 'Atenciones', 'Pacientes distintos', 'Inasistencias', 'Cancelaciones', 'Citas agendadas']));
        foreach ($d['porMedico'] as $r) {
            $w->addRow(Row::fromValues([$r->NOMBRE, $r->DES_ESP, $r->ATENDIDAS, $r->PACIENTES, $r->INASISTENCIAS, $r->CANCELADAS, $r->AGENDADAS]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Especialidad por mes');
        $w->addRow(Row::fromValues(array_merge(['Especialidad'], $d['espPorMes']['etiquetas'])));
        foreach ($d['espPorMes']['series'] as $esp => $valores) {
            $w->addRow(Row::fromValues(array_merge([$esp], $valores)));
        }
        $w->addRow(Row::fromValues([]));
        $w->addRow(Row::fromValues(['Mes', 'Especialidad más frecuentada', 'Atenciones', '% del mes', 'Segunda']));
        foreach ($d['espPorMes']['ganadoras'] as $g) {
            $w->addRow(Row::fromValues([$g['mes'], $g['especialidad'], $g['atenciones'], $g['porcentaje'], $g['segunda']]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Pacientes frecuentes');
        $w->addRow(Row::fromValues(['Código', 'Paciente', 'CI', 'Edad', 'Visitas', 'Especialidades', 'Más visita', 'Última visita']));
        foreach ($d['topPacientes'] as $p) {
            $w->addRow(Row::fromValues([$p->COD_PAC, "{$p->NOM_PAC} {$p->APE_PAC}", $p->CI_PAC, $p->EDAD, $p->VISITAS,
                (int) $p->ESPECIALIDADES, $p->FAVORITA, Carbon::parse($p->ULTIMA)->format('d/m/Y')]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Evolución');
        $w->addRow(Row::fromValues(['Periodo', 'Atenciones', 'Citas agendadas']));
        foreach ($d['serie']['etiquetas'] as $i => $e) {
            $w->addRow(Row::fromValues([$e, $d['serie']['atendidas'][$i], $d['serie']['agendadas'][$i]]));
        }

        $w->close();

        return response()->download($ruta, 'reporte_atenciones_' . now()->format('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
