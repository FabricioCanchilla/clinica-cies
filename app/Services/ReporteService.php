<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reportes y estadísticas de atención (dashboard del administrador, módulo Reportes y panel del médico).
 *
 * Una «visita» es una cita Atendida: el paciente vino y fue atendido por el médico.
 * Todos los reportes aceptan el mismo filtro: periodo (día, semana, mes, año o rango), especialidad y médico.
 */
class ReporteService
{
    public const MESES = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    public const MESES_LARGOS = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                                 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    public const ESTADOS = ['Atendida', 'Confirmada', 'Pendiente de pago', 'No asistio', 'Cancelada', 'Expirada'];

    // ------------------------------------------------------------------
    // Filtro común
    // ------------------------------------------------------------------

    /**
     * Lee el filtro de la petición y devuelve el rango de fechas y cómo agrupar la serie temporal.
     * periodo: dia | semana | mes | anio | rango
     */
    public function filtro(Request $request, ?string $medicoFijo = null): array
    {
        $periodo = in_array($request->query('periodo'), ['dia', 'semana', 'mes', 'anio', 'rango'], true)
            ? $request->query('periodo') : 'mes';

        $ref = Carbon::today();
        if (is_string($request->query('fecha')) && Carbon::hasFormat($request->query('fecha'), 'Y-m-d')) {
            $ref = Carbon::createFromFormat('Y-m-d', $request->query('fecha'))->startOfDay();
        }

        switch ($periodo) {
            case 'dia':
                $desde = $ref->copy();
                $hasta = $ref->copy();
                $etiqueta = 'el ' . $ref->format('d/m/Y');
                break;
            case 'semana':
                $desde = $ref->copy()->startOfWeek(Carbon::MONDAY);
                $hasta = $desde->copy()->addDays(6);
                $etiqueta = 'la semana del ' . $desde->format('d/m') . ' al ' . $hasta->format('d/m/Y');
                break;
            case 'anio':
                $desde = $ref->copy()->startOfYear();
                $hasta = $ref->copy()->endOfYear()->startOfDay();
                $etiqueta = 'el año ' . $ref->year;
                break;
            case 'rango':
                $desde = $this->fechaO($request->query('desde'), $ref->copy()->subDays(29));
                $hasta = $this->fechaO($request->query('hasta'), $ref->copy());
                if ($hasta->lt($desde)) {
                    [$desde, $hasta] = [$hasta, $desde];
                }
                if ($desde->diffInDays($hasta) > 731) {
                    $desde = $hasta->copy()->subDays(731);
                }
                $etiqueta = 'del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
                break;
            default: // mes
                $desde = $ref->copy()->startOfMonth();
                $hasta = $ref->copy()->endOfMonth()->startOfDay();
                $etiqueta = self::MESES_LARGOS[$ref->month] . ' de ' . $ref->year;
        }

        $dias = (int) $desde->diffInDays($hasta) + 1;
        $agrupar = match (true) {
            $periodo === 'dia' => 'hora',
            $dias <= 62        => 'dia',
            default            => 'mes',
        };

        return [
            'periodo'      => $periodo,
            'fecha'        => $ref->toDateString(),
            'desde'        => $desde->toDateString(),
            'hasta'        => $hasta->toDateString(),
            'etiqueta'     => $etiqueta,
            'agrupar'      => $agrupar,
            'especialidad' => (string) $request->query('especialidad', ''),
            'medico'       => $medicoFijo ?? (string) $request->query('medico', ''),
        ];
    }

    private function fechaO($valor, Carbon $defecto): Carbon
    {
        return is_string($valor) && Carbon::hasFormat($valor, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $valor)->startOfDay()
            : $defecto;
    }

    /** Citas del periodo con médico y especialidad, filtradas. */
    public function base(array $f, bool $conRango = true): Builder
    {
        $q = DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP');
        if ($conRango) {
            $q->whereBetween('c.FEC_CIT', [$f['desde'], $f['hasta']]);
        }
        if ($f['especialidad'] !== '') {
            $q->where('e.COD_ESP', $f['especialidad']);
        }
        if ($f['medico'] !== '') {
            $q->where('c.COD_EMP', $f['medico']);
        }
        return $q;
    }

    // ------------------------------------------------------------------
    // Indicadores
    // ------------------------------------------------------------------

    public function indicadores(array $f): array
    {
        $estados = $this->base($f)->groupBy('c.EST_CIT')
            ->selectRaw('c.EST_CIT AS est, COUNT(*) AS total')->pluck('total', 'est');

        $atendidas = (int) ($estados['Atendida'] ?? 0);
        $agendadas = (int) $estados->sum();
        $noAsistio = (int) ($estados['No asistio'] ?? 0);
        $pasadas   = $atendidas + $noAsistio;

        $pacientes = (int) $this->base($f)->where('c.EST_CIT', 'Atendida')->distinct()->count('c.COD_PAC');

        $nuevos = (int) $this->base($f)->where('c.EST_CIT', 'Atendida')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('CITA as c2')
                  ->whereColumn('c2.COD_PAC', 'c.COD_PAC')
                  ->where('c2.EST_CIT', 'Atendida')
                  ->whereColumn('c2.FEC_CIT', '<', 'c.FEC_CIT');
            })->distinct()->count('c.COD_PAC');

        $ingresos = (float) $this->base($f, false)
            ->join('PAGO as pg', 'pg.COD_CIT', '=', 'c.COD_CIT')
            ->where('pg.EST_PAG', 'Confirmado')
            ->whereBetween(DB::raw('DATE(pg.FEC_PAG)'), [$f['desde'], $f['hasta']])
            ->sum('pg.MON_PAG');

        return [
            'agendadas'   => $agendadas,
            'atendidas'   => $atendidas,
            'pacientes'   => $pacientes,
            'nuevos'      => $nuevos,
            'noAsistio'   => $noAsistio,
            'canceladas'  => (int) ($estados['Cancelada'] ?? 0),
            'asistencia'  => $pasadas > 0 ? round($atendidas * 100 / $pasadas, 1) : null,
            'ingresos'    => round($ingresos, 2),
            'porEstado'   => collect(self::ESTADOS)->mapWithKeys(fn ($e) => [$e => (int) ($estados[$e] ?? 0)])->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Por especialidad y por médico
    // ------------------------------------------------------------------

    public function porEspecialidad(array $f): Collection
    {
        $ingresos = DB::table('PAGO as pg')
            ->join('CITA as c', 'c.COD_CIT', '=', 'pg.COD_CIT')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->where('pg.EST_PAG', 'Confirmado')
            ->whereBetween(DB::raw('DATE(pg.FEC_PAG)'), [$f['desde'], $f['hasta']])
            ->when($f['medico'] !== '', fn ($q) => $q->where('c.COD_EMP', $f['medico']))
            ->groupBy('e.COD_ESP')
            ->selectRaw('e.COD_ESP AS esp, SUM(pg.MON_PAG) AS total')
            ->pluck('total', 'esp');

        return $this->base($f)
            ->groupBy('es.COD_ESP', 'es.DES_ESP')
            ->selectRaw("es.COD_ESP, es.DES_ESP,
                SUM(c.EST_CIT = 'Atendida') AS ATENDIDAS,
                COUNT(DISTINCT CASE WHEN c.EST_CIT = 'Atendida' THEN c.COD_PAC END) AS PACIENTES,
                SUM(c.EST_CIT = 'No asistio') AS INASISTENCIAS,
                COUNT(*) AS AGENDADAS")
            ->orderByDesc('ATENDIDAS')
            ->get()
            ->each(function ($r) use ($ingresos) {
                $r->ATENDIDAS = (int) $r->ATENDIDAS;
                $r->PACIENTES = (int) $r->PACIENTES;
                $r->INASISTENCIAS = (int) $r->INASISTENCIAS;
                $r->INGRESOS = round((float) ($ingresos[$r->COD_ESP] ?? 0), 2);
            });
    }

    public function porMedico(array $f): Collection
    {
        return $this->base($f)
            ->groupBy('e.COD_EMP', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'es.DES_ESP')
            ->selectRaw("e.COD_EMP, e.TIT_EMP, e.NOM_EMP, e.APE_EMP, es.DES_ESP,
                SUM(c.EST_CIT = 'Atendida') AS ATENDIDAS,
                COUNT(DISTINCT CASE WHEN c.EST_CIT = 'Atendida' THEN c.COD_PAC END) AS PACIENTES,
                SUM(c.EST_CIT = 'No asistio') AS INASISTENCIAS,
                SUM(c.EST_CIT = 'Cancelada') AS CANCELADAS,
                COUNT(*) AS AGENDADAS")
            ->orderByDesc('ATENDIDAS')
            ->get()
            ->each(function ($r) {
                foreach (['ATENDIDAS', 'PACIENTES', 'INASISTENCIAS', 'CANCELADAS', 'AGENDADAS'] as $k) {
                    $r->$k = (int) $r->$k;
                }
                $r->NOMBRE = trim("{$r->TIT_EMP} {$r->NOM_EMP} {$r->APE_EMP}");
            });
    }

    // ------------------------------------------------------------------
    // Serie temporal (por hora, día o mes según el periodo)
    // ------------------------------------------------------------------

    public function serie(array $f): array
    {
        $expr = match ($f['agrupar']) {
            'hora'  => "LPAD(HOUR(c.HOR_CIT), 2, '0')",
            'dia'   => 'c.FEC_CIT',
            default => "DATE_FORMAT(c.FEC_CIT, '%Y-%m')",
        };

        $filas = $this->base($f)
            ->groupBy(DB::raw($expr))
            ->selectRaw("{$expr} AS k, SUM(c.EST_CIT = 'Atendida') AS atendidas, COUNT(*) AS agendadas")
            ->get()->keyBy('k');

        $claves = [];
        $etiquetas = [];
        if ($f['agrupar'] === 'hora') {
            for ($h = 7; $h <= 19; $h++) {
                $claves[] = sprintf('%02d', $h);
                $etiquetas[] = sprintf('%02d:00', $h);
            }
        } elseif ($f['agrupar'] === 'dia') {
            for ($d = Carbon::parse($f['desde']); $d->lte(Carbon::parse($f['hasta'])); $d->addDay()) {
                $claves[] = $d->toDateString();
                $etiquetas[] = $d->format('d/m');
            }
        } else {
            for ($d = Carbon::parse($f['desde'])->startOfMonth(); $d->lte(Carbon::parse($f['hasta'])); $d->addMonth()) {
                $claves[] = $d->format('Y-m');
                $etiquetas[] = self::MESES[$d->month] . ' ' . $d->format('y');
            }
        }

        return [
            'etiquetas' => $etiquetas,
            'atendidas' => array_map(fn ($k) => (int) ($filas[$k]->atendidas ?? 0), $claves),
            'agendadas' => array_map(fn ($k) => (int) ($filas[$k]->agendadas ?? 0), $claves),
        ];
    }

    // ------------------------------------------------------------------
    // Especialidad más frecuentada cada mes (12 meses hasta el fin del periodo)
    // ------------------------------------------------------------------

    public function especialidadPorMes(array $f, int $meses = 12): array
    {
        $fin = Carbon::parse($f['hasta'])->endOfMonth();
        $ini = $fin->copy()->startOfMonth()->subMonths($meses - 1);

        $sub = array_merge($f, ['desde' => $ini->toDateString(), 'hasta' => $fin->toDateString()]);
        $filas = $this->base($sub)
            ->where('c.EST_CIT', 'Atendida')
            ->groupBy(DB::raw("DATE_FORMAT(c.FEC_CIT, '%Y-%m')"), 'es.COD_ESP', 'es.DES_ESP')
            ->selectRaw("DATE_FORMAT(c.FEC_CIT, '%Y-%m') AS mes, es.COD_ESP, es.DES_ESP, COUNT(*) AS total")
            ->get();

        $claves = [];
        $etiquetas = [];
        for ($d = $ini->copy(); $d->lte($fin); $d->addMonth()) {
            $claves[] = $d->format('Y-m');
            $etiquetas[] = self::MESES[$d->month] . ' ' . $d->format('y');
        }

        // Especialidades ordenadas por total del año; las 6 primeras van por separado y el resto en «Otras»
        $totales = $filas->groupBy('DES_ESP')->map(fn ($g) => $g->sum('total'))->sortDesc();
        $principales = $totales->keys()->take(6)->all();

        $series = [];
        foreach ($principales as $esp) {
            $series[$esp] = array_fill(0, count($claves), 0);
        }
        if ($totales->count() > 6) {
            $series['Otras'] = array_fill(0, count($claves), 0);
        }

        $ganadoras = [];
        foreach ($claves as $i => $mes) {
            $delMes = $filas->where('mes', $mes)->sortByDesc('total')->values();
            foreach ($delMes as $r) {
                $nombre = in_array($r->DES_ESP, $principales, true) ? $r->DES_ESP : 'Otras';
                $series[$nombre][$i] += (int) $r->total;
            }
            $top = $delMes->first();
            $total = (int) $delMes->sum('total');
            $ganadoras[] = [
                'mes'          => $etiquetas[$i],
                'clave'        => $mes,
                'especialidad' => $top->DES_ESP ?? '—',
                'atenciones'   => (int) ($top->total ?? 0),
                'total'        => $total,
                'porcentaje'   => $total > 0 ? round(($top->total ?? 0) * 100 / $total, 1) : 0,
                'segunda'      => $delMes[1]->DES_ESP ?? '—',
            ];
        }

        return ['etiquetas' => $etiquetas, 'series' => $series, 'ganadoras' => $ganadoras];
    }

    // ------------------------------------------------------------------
    // Pacientes que más visitan la clínica
    // ------------------------------------------------------------------

    public function topPacientes(array $f, int $limite = 10): Collection
    {
        $filas = $this->base($f)
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->where('c.EST_CIT', 'Atendida')
            ->groupBy('p.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.SEX_PAC', 'p.FECNAC_PAC')
            ->selectRaw('p.COD_PAC, p.NOM_PAC, p.APE_PAC, p.CI_PAC, p.SEX_PAC, p.FECNAC_PAC,
                COUNT(*) AS VISITAS, COUNT(DISTINCT es.COD_ESP) AS ESPECIALIDADES, MAX(c.FEC_CIT) AS ULTIMA')
            ->orderByDesc('VISITAS')->orderByDesc('ULTIMA')
            ->limit($limite)
            ->get();

        if ($filas->isEmpty()) {
            return $filas;
        }

        // Especialidad que más visita cada uno
        $favoritas = $this->base($f)
            ->where('c.EST_CIT', 'Atendida')
            ->whereIn('c.COD_PAC', $filas->pluck('COD_PAC'))
            ->groupBy('c.COD_PAC', 'es.DES_ESP')
            ->selectRaw('c.COD_PAC, es.DES_ESP, COUNT(*) AS n')
            ->get()->groupBy('COD_PAC')
            ->map(fn ($g) => $g->sortByDesc('n')->first()->DES_ESP);

        return $filas->each(function ($p) use ($favoritas) {
            $p->VISITAS = (int) $p->VISITAS;
            $p->EDAD = Carbon::parse($p->FECNAC_PAC)->age;
            $p->FAVORITA = $favoritas[$p->COD_PAC] ?? '—';
        });
    }

    // ------------------------------------------------------------------
    // Perfil de los pacientes atendidos
    // ------------------------------------------------------------------

    public function perfilPacientes(array $f): array
    {
        $pacientes = $this->base($f)
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->where('c.EST_CIT', 'Atendida')
            ->distinct()
            ->get(['p.COD_PAC', 'p.SEX_PAC', 'p.FECNAC_PAC']);

        $edades = ['0–14' => 0, '15–29' => 0, '30–44' => 0, '45–59' => 0, '60 o más' => 0];
        foreach ($pacientes as $p) {
            $e = Carbon::parse($p->FECNAC_PAC)->age;
            $grupo = match (true) { $e < 15 => '0–14', $e < 30 => '15–29', $e < 45 => '30–44', $e < 60 => '45–59', default => '60 o más' };
            $edades[$grupo]++;
        }

        return [
            'sexo'   => ['Mujeres' => $pacientes->where('SEX_PAC', 'F')->count(), 'Hombres' => $pacientes->where('SEX_PAC', 'M')->count()],
            'edades' => $edades,
        ];
    }

    /** Horas del día con más atenciones. */
    public function horasPico(array $f): array
    {
        $filas = $this->base($f)->where('c.EST_CIT', 'Atendida')
            ->groupBy(DB::raw('HOUR(c.HOR_CIT)'))
            ->selectRaw('HOUR(c.HOR_CIT) AS h, COUNT(*) AS n')
            ->pluck('n', 'h');

        $out = [];
        for ($h = 8; $h <= 17; $h++) {
            $out[sprintf('%02d:00', $h)] = (int) ($filas[$h] ?? 0);
        }
        return $out;
    }

    /** Especialidades y médicos para los filtros. */
    public function catalogos(): array
    {
        return [
            'especialidades' => DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get(['COD_ESP', 'DES_ESP']),
            'medicos'        => DB::table('EMPLEADO as e')
                ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
                ->orderBy('es.DES_ESP')->orderBy('e.APE_EMP')
                ->get(['e.COD_EMP', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'e.COD_ESP', 'es.DES_ESP']),
        ];
    }

    // ------------------------------------------------------------------
    // Camas (para el dashboard)
    // ------------------------------------------------------------------

    public function camas(): array
    {
        $estados = DB::table('CAMA')->groupBy('EST_CAM')->selectRaw('EST_CAM, COUNT(*) AS n')->pluck('n', 'EST_CAM');
        $total = (int) $estados->sum();

        $limpieza = ['pendientes' => 0, 'promedio' => null, 'terminales' => 0];
        try {
            $limpieza['pendientes'] = DB::table('LIMPIEZA_CAMA')->where('EST_LIM', '<>', 'Completada')->count();
            $limpieza['terminales'] = DB::table('LIMPIEZA_CAMA')->where('EST_LIM', '<>', 'Completada')->where('TIP_LIM', 'Terminal')->count();
            $prom = DB::table('LIMPIEZA_CAMA')->where('EST_LIM', 'Completada')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, FECSOL_LIM, FECFIN_LIM)) AS m')->value('m');
            $limpieza['promedio'] = $prom !== null ? (int) round($prom) : null;
        } catch (\Illuminate\Database\QueryException $e) {
            // sin el script 04 todavía
        }

        return [
            'estados'   => ['Disponible' => (int) ($estados['Disponible'] ?? 0), 'Ocupada' => (int) ($estados['Ocupada'] ?? 0), 'Limpieza' => (int) ($estados['Limpieza'] ?? 0)],
            'total'     => $total,
            'ocupacion' => $total > 0 ? round(($estados['Ocupada'] ?? 0) * 100 / $total, 1) : 0,
            'limpieza'  => $limpieza,
        ];
    }

    /** Ingresos confirmados por mes (últimos 12 meses). */
    public function ingresosMensuales(int $meses = 12): array
    {
        $ini = Carbon::today()->startOfMonth()->subMonths($meses - 1);
        $filas = DB::table('PAGO')->where('EST_PAG', 'Confirmado')->where('FEC_PAG', '>=', $ini->toDateTimeString())
            ->groupBy(DB::raw("DATE_FORMAT(FEC_PAG, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(FEC_PAG, '%Y-%m') AS k, SUM(MON_PAG) AS t")
            ->pluck('t', 'k');

        $etiquetas = $valores = [];
        for ($d = $ini->copy(); $d->lte(Carbon::today()); $d->addMonth()) {
            $etiquetas[] = self::MESES[$d->month] . ' ' . $d->format('y');
            $valores[] = round((float) ($filas[$d->format('Y-m')] ?? 0), 2);
        }
        return ['etiquetas' => $etiquetas, 'valores' => $valores];
    }
}
