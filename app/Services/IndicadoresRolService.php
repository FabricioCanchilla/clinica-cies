<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores y gráficos de la pantalla principal de cada rol (RF045: indicadores operacionales).
 * Cada método devuelve ['kpis' => [...], 'graficos' => [...]] para el parcial «partials.panel-graficos».
 *   kpi:     ['v' => valor, 't' => texto, 'd' => detalle opcional, 'ico' => icono FontAwesome]
 *   grafico: ['id', 'titulo', 'nota', 'tipo' => barras|linea|dona, 'etiquetas', 'series' | 'valores', 'op', 'ancho', 'alto']
 */
class IndicadoresRolService
{
    /** Colores fijos por estado (los mismos de las etiquetas RNF011), así un estado nunca cambia de color. */
    public const COLOR_ESTADO = [
        'Pendiente de pago' => '#f59e0b', 'Confirmada' => '#4c7d5f', 'En espera' => '#8b5cf6', 'En consulta' => '#14b8a6',
        'Atendida' => '#3b82f6', 'Cancelada' => '#ef4444', 'Expirada' => '#94a3b8', 'No asistio' => '#ec4899',
        'Disponible' => '#4c7d5f', 'Ocupada' => '#e11d48', 'Limpieza' => '#cc783c',
        'Pendiente' => '#f59e0b', 'Muestra tomada' => '#8b5cf6', 'En proceso' => '#3b82f6', 'Listo' => '#4c7d5f', 'Cancelado' => '#94a3b8',
        'Activa' => '#3b82f6', 'Completada' => '#4c7d5f', 'Anulada' => '#94a3b8',
        'Administrada' => '#4c7d5f', 'Omitida' => '#ef4444', 'Programada' => '#f59e0b',
        'Verificado' => '#4c7d5f', 'Pendiente de verificación' => '#f59e0b', 'Rechazado' => '#ef4444',
        'Baja' => '#3b82f6', 'Media' => '#f59e0b', 'Alta' => '#f97316', 'Crítica' => '#e11d48',
        'Vigente' => '#4c7d5f', 'Por vencer' => '#f59e0b', 'Vencido' => '#e11d48', 'Agotado' => '#94a3b8',
    ];
    private const ACTIVAS = ['Confirmada', 'En espera', 'En consulta'];

    // ------------------------------------------------------------------ helpers

    /** Cuenta por día de los últimos $dias días: [etiquetas, valores]. */
    private function porDia($query, string $col, int $dias, ?string $suma = null): array
    {
        $desde = Carbon::today()->subDays($dias - 1);
        $filas = (clone $query)->where($col, '>=', $desde->toDateString())
            ->groupBy(DB::raw("DATE({$col})"))
            ->selectRaw(($suma ? "SUM({$suma})" : 'COUNT(*)') . ' as n, ' . "DATE({$col})" . ' as k')->pluck('n', 'k');
        $et = []; $val = [];
        for ($d = $desde->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $et[] = $d->format('d/m');
            $val[] = round((float) ($filas[$d->toDateString()] ?? 0), 2);
        }
        return [$et, $val];
    }

    private function dona(string $id, string $titulo, string $nota, $pares, array $op = []): array
    {
        $pares = collect($pares)->filter(fn ($v) => $v > 0);
        return ['id' => $id, 'titulo' => $titulo, 'nota' => $nota, 'tipo' => 'dona',
                'etiquetas' => $pares->keys()->all(), 'valores' => $pares->values()->map(fn ($v) => (int) $v)->all(),
                'op' => $op + ['colores' => $pares->keys()->map(fn ($k) => self::COLOR_ESTADO[$k] ?? null)->filter()->count() === $pares->count()
                    ? $pares->keys()->map(fn ($k) => self::COLOR_ESTADO[$k])->all() : null]];
    }

    private function colores(array $nombres): array
    {
        return array_map(fn ($n) => self::COLOR_ESTADO[$n] ?? '#64748b', $nombres);
    }

    // ------------------------------------------------------------------ Recepción

    public function recepcion(): array
    {
        $hoy = Carbon::today()->toDateString();
        $citas = DB::table('CITA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->leftJoin('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->where('c.FEC_CIT', $hoy)->where('c.EST_CIT', '!=', 'Expirada')
            ->get(['c.HOR_CIT', 'c.EST_CIT', 'es.DES_ESP']);

        // Citas de hoy por hora, apiladas por estado
        $horas = $citas->map(fn ($c) => substr($c->HOR_CIT, 0, 2) . ':00')->unique()->sort()->values()->all();
        $estados = $citas->pluck('EST_CIT')->unique()->sortBy(fn ($e) => array_search($e, array_keys(self::COLOR_ESTADO)))->values()->all();
        $series = [];
        foreach ($estados as $e) {
            $series[$e] = array_map(fn ($h) => $citas->where('EST_CIT', $e)->filter(fn ($c) => substr($c->HOR_CIT, 0, 2) . ':00' === $h)->count(), $horas);
        }

        $q = DB::table('AUDITORIA')->where('ENT_AUD', 'CITA')->where('ACC_AUD', 'Check-in');
        [$etL, $llegadas] = $this->porDia($q, 'FEC_AUD', 14);
        [, $atendidas] = $this->porDia(DB::table('CITA')->where('EST_CIT', 'Atendida')->where('FEC_CIT', '<=', $hoy), 'FEC_CIT', 14);
        [, $inasist] = $this->porDia(DB::table('CITA')->where('EST_CIT', 'No asistio')->where('FEC_CIT', '<=', $hoy), 'FEC_CIT', 14);

        $porEsp = $citas->groupBy(fn ($c) => $c->DES_ESP ?? 'Sin especialidad')->map->count()->sortDesc();

        return ['graficos' => [
            ['id' => 'rc-horas', 'titulo' => 'Citas de hoy por hora', 'nota' => 'Cada barra suma las citas de esa hora según su estado.',
             'tipo' => 'barras', 'etiquetas' => $horas, 'series' => $series, 'op' => ['apilado' => true, 'colores' => $this->colores($estados)]],
            $this->dona('rc-estados', 'Estado de las citas de hoy', 'Confirmadas por llegar, en espera, atendidas e inasistencias.',
                $citas->countBy('EST_CIT')),
            ['id' => 'rc-esp', 'titulo' => 'Citas de hoy por especialidad', 'nota' => 'Dónde se concentra la demanda del día.',
             'tipo' => 'barras', 'etiquetas' => $porEsp->keys()->all(), 'series' => ['Citas' => $porEsp->values()->all()], 'op' => ['horizontal' => true]],
            ['id' => 'rc-14', 'titulo' => 'Últimos 14 días', 'nota' => 'Atendidas, llegadas registradas en recepción e inasistencias por día.',
             'tipo' => 'linea', 'etiquetas' => $etL, 'series' => ['Atendidas' => $atendidas, 'Check-in' => $llegadas, 'No asistió' => $inasist]],
        ]];
    }

    // ------------------------------------------------------------------ Médico

    public function medico(string $codEmp): array
    {
        $hoy = Carbon::today();
        [$et, $aten] = $this->porDia(DB::table('CITA')->where('COD_EMP', $codEmp)->where('EST_CIT', 'Atendida')
            ->where('FEC_CIT', '<=', $hoy->toDateString()), 'FEC_CIT', 30);

        $mes = DB::table('CITA')->where('COD_EMP', $codEmp)
            ->whereBetween('FEC_CIT', [$hoy->copy()->startOfMonth()->toDateString(), $hoy->copy()->endOfMonth()->toDateString()])
            ->groupBy('EST_CIT')->selectRaw('COUNT(*)' . ' as n, ' . 'EST_CIT' . ' as k')->pluck('n', 'k');

        $dx = DB::table('CONSULTA as c')->leftJoin('CIE10 as x', 'x.COD_CIE', '=', 'c.COD_CIE')
            ->where('c.COD_EMP', $codEmp)->where('c.FEC_CNS', '>=', $hoy->copy()->subMonths(6))
            ->groupBy('c.COD_CIE', 'c.DIA_CNS')->orderByDesc(DB::raw('COUNT(*)'))->limit(8)
            ->get([DB::raw("CONCAT(COALESCE(c.COD_CIE, ''), ' ', c.DIA_CNS) as dx"), DB::raw('COUNT(*) as n')]);

        $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $sem = DB::table('CITA')->where('COD_EMP', $codEmp)->where('EST_CIT', 'Atendida')
            ->where('FEC_CIT', '>=', $hoy->copy()->subMonths(3)->toDateString())
            ->groupBy(DB::raw('WEEKDAY(FEC_CIT)'))->selectRaw('COUNT(*)' . ' as n, ' . 'WEEKDAY(FEC_CIT)' . ' as k')->pluck('n', 'k');

        $pendDer = DB::table('DERIVACION')->where('EST_DER', 'Pendiente')
            ->where('COD_ESP', DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->value('COD_ESP'))->count();

        return [
            'kpis' => [
                ['v' => array_sum(array_slice($aten, -7)), 't' => 'Atenciones en los últimos 7 días', 'ico' => 'fa-user-check'],
                ['v' => (int) ($mes['Atendida'] ?? 0), 't' => 'Atendidas este mes', 'ico' => 'fa-calendar-check'],
                ['v' => (int) collect($mes)->only(self::ACTIVAS)->sum(), 't' => 'Citas por atender este mes', 'ico' => 'fa-hourglass-half'],
                ['v' => $pendDer, 't' => 'Interconsultas pendientes para tu especialidad', 'ico' => 'fa-share-from-square'],
            ],
            'graficos' => [
                ['id' => 'md-30', 'titulo' => 'Mis atenciones · últimos 30 días', 'nota' => 'Citas atendidas por día.', 'ancho' => true,
                 'tipo' => 'linea', 'etiquetas' => $et, 'series' => ['Atendidas' => $aten]],
                $this->dona('md-mes', 'Mis citas de este mes por estado', 'Incluye las que aún están por venir.', $mes),
                ['id' => 'md-dx', 'titulo' => 'Mis diagnósticos más frecuentes', 'nota' => 'Últimos 6 meses, con su código CIE-10.',
                 'tipo' => 'barras', 'etiquetas' => $dx->pluck('dx')->map(fn ($d) => mb_strimwidth(trim($d), 0, 38, '…'))->all(),
                 'series' => ['Consultas' => $dx->pluck('n')->all()], 'op' => ['horizontal' => true]],
                ['id' => 'md-sem', 'titulo' => 'Atenciones por día de la semana', 'nota' => 'Últimos 3 meses.',
                 'tipo' => 'barras', 'etiquetas' => $dias, 'series' => ['Atenciones' => array_map(fn ($i) => (int) ($sem[$i] ?? 0), range(0, 6))]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Enfermería

    public function enfermeria(): array
    {
        $camas = DB::table('CAMA')->groupBy('EST_CAM')->selectRaw('COUNT(*)' . ' as n, ' . 'EST_CAM' . ' as k')->pluck('n', 'k');
        [$et, $ingresos] = $this->porDia(DB::table('INTERNACION'), 'FECING_INT', 30);
        [, $altas] = $this->porDia(DB::table('INTERNACION')->whereNotNull('FECALT_INT'), 'FECALT_INT', 30);

        $dosis = DB::table('DOSIS')->where('FECPRG_DOS', '>=', Carbon::today()->subDays(6))
            ->where('FECPRG_DOS', '<', Carbon::tomorrow())
            ->get(['FECPRG_DOS', 'EST_DOS']);
        $dias = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        $serie = fn ($e) => $dias->map(fn ($d) => $dosis->where('EST_DOS', $e)->filter(fn ($x) => str_starts_with($x->FECPRG_DOS, $d->toDateString()))->count())->all();

        $total = (int) collect($camas)->sum();
        return [
            'kpis' => [
                ['v' => (int) ($camas['Ocupada'] ?? 0) . ' / ' . $total, 't' => 'Camas ocupadas', 'ico' => 'fa-bed',
                 'd' => $total ? round(100 * ($camas['Ocupada'] ?? 0) / $total) . ' % de ocupación' : null],
                ['v' => (int) ($camas['Limpieza'] ?? 0), 't' => 'Camas en limpieza', 'ico' => 'fa-broom'],
                ['v' => DB::table('DOSIS')->where('EST_DOS', 'Programada')->where('FECPRG_DOS', '<', now()->subMinutes((int) config('cies.ventana_dosis_min', 30)))->count(),
                 't' => 'Dosis atrasadas', 'ico' => 'fa-triangle-exclamation'],
                ['v' => DB::table('INSUMO_TERCERO')->where('EST_INS', 'like', 'Pendiente%')->count(), 't' => 'Insumos por verificar', 'ico' => 'fa-hand-holding-medical'],
            ],
            'graficos' => [
                $this->dona('en-camas', 'Camas por estado', 'Tiempo real: disponible, ocupada o en limpieza.', $camas),
                ['id' => 'en-dosis', 'titulo' => 'Dosis de los últimos 7 días', 'nota' => 'Administradas frente a omitidas (con incidencia).',
                 'tipo' => 'barras', 'etiquetas' => $dias->map->format('d/m')->all(),
                 'series' => ['Administrada' => $serie('Administrada'), 'Omitida' => $serie('Omitida')],
                 'op' => ['apilado' => true, 'colores' => $this->colores(['Administrada', 'Omitida'])]],
                ['id' => 'en-mov', 'titulo' => 'Ingresos y altas · últimos 30 días', 'nota' => 'Movimiento de pacientes internados por día.', 'ancho' => true,
                 'tipo' => 'linea', 'etiquetas' => $et, 'series' => ['Ingresos' => $ingresos, 'Altas' => $altas]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Camas e internación

    public function camas(): array
    {
        $total = max(1, DB::table('CAMA')->count());
        $estados = DB::table('CAMA')->groupBy('EST_CAM')->selectRaw('COUNT(*) as n, EST_CAM as k')->pluck('n', 'k');

        // Ocupación diaria de los últimos 30 días: internaciones vigentes en cada día
        $desde = Carbon::today()->subDays(29);
        $ints = DB::table('INTERNACION')->where('FECING_INT', '<', Carbon::tomorrow())
            ->where(fn ($q) => $q->whereNull('FECALT_INT')->orWhere('FECALT_INT', '>=', $desde))
            ->get(['FECING_INT', 'FECALT_INT']);
        $et = []; $ocup = [];
        for ($d = $desde->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $fin = $d->copy()->endOfDay();
            $n = $ints->filter(fn ($i) => Carbon::parse($i->FECING_INT)->lte($fin) && (!$i->FECALT_INT || Carbon::parse($i->FECALT_INT)->gte($d)))->count();
            $et[] = $d->format('d/m');
            $ocup[] = round(100 * min($n, $total) / $total, 1);
        }

        // Ingresos y estancia media por mes (12 meses)
        $meses = collect(range(11, 0))->map(fn ($i) => Carbon::today()->startOfMonth()->subMonths($i));
        $porMes = DB::table('INTERNACION')->where('FECING_INT', '>=', $meses->first())
            ->groupBy(DB::raw("DATE_FORMAT(FECING_INT, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(FECING_INT, '%Y-%m') as k, COUNT(*) as n, AVG(TIMESTAMPDIFF(HOUR, FECING_INT, COALESCE(FECALT_INT, NOW()))) / 24 as e")
            ->get()->keyBy('k');
        $etM = $meses->map(fn ($m) => ucfirst($m->locale('es')->isoFormat('MMM YY')))->all();

        $motivos = DB::table('INTERNACION')->where('FECING_INT', '>=', Carbon::today()->subMonths(6))
            ->groupBy('MOT_INT')->orderByDesc(DB::raw('COUNT(*)'))->limit(8)
            ->selectRaw('COUNT(*) as n, MOT_INT as k')->pluck('n', 'k');

        $limp = DB::table('LIMPIEZA_CAMA')->get(['TIP_LIM', 'EST_LIM', 'FECINI_LIM', 'FECFIN_LIM']);
        $tiposLim = ['Rutinaria', 'Terminal'];
        $estLim = ['Pendiente', 'En proceso', 'Completada'];
        $seriesLim = [];
        foreach ($estLim as $e) {
            $seriesLim[$e] = array_map(fn ($t) => $limp->where('TIP_LIM', $t)->where('EST_LIM', $e)->count(), $tiposLim);
        }
        $hechas = $limp->where('EST_LIM', 'Completada')->filter(fn ($l) => $l->FECINI_LIM && $l->FECFIN_LIM);
        $minProm = $hechas->count() ? round($hechas->avg(fn ($l) => Carbon::parse($l->FECINI_LIM)->diffInMinutes(Carbon::parse($l->FECFIN_LIM)))) : null;

        $estanciaActual = DB::table('INTERNACION')->where('EST_INT', 'Internado')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, FECING_INT, NOW())'));

        return [
            'kpis' => [
                ['v' => round(100 * ($estados['Ocupada'] ?? 0) / $total) . ' %', 't' => 'Ocupación actual', 'ico' => 'fa-bed-pulse',
                 'd' => ($estados['Ocupada'] ?? 0) . ' de ' . $total . ' camas'],
                ['v' => round(array_sum($ocup) / max(1, count($ocup))) . ' %', 't' => 'Ocupación promedio (30 días)', 'ico' => 'fa-chart-line'],
                ['v' => $estanciaActual ? round($estanciaActual / 24, 1) . ' días' : '—', 't' => 'Estancia media de los internados actuales', 'ico' => 'fa-hourglass-half'],
                ['v' => $minProm !== null ? $minProm . ' min' : '—', 't' => 'Duración media de una limpieza', 'ico' => 'fa-broom',
                 'd' => $limp->whereIn('EST_LIM', ['Pendiente', 'En proceso'])->count() . ' limpiezas abiertas'],
            ],
            'graficos' => [
                ['id' => 'cm-ocup', 'titulo' => 'Ocupación de camas · últimos 30 días', 'nota' => 'Porcentaje de camas con un paciente internado cada día.', 'ancho' => true,
                 'tipo' => 'linea', 'etiquetas' => $et, 'series' => ['Ocupación %' => $ocup], 'op' => ['sufijo' => ' %']],
                $this->dona('cm-est', 'Camas por estado', 'Ahora mismo.', $estados),
                ['id' => 'cm-lim', 'titulo' => 'Limpiezas por tipo y estado', 'nota' => 'Rutinaria tras un alta común; terminal tras un aislamiento (AJ08).',
                 'tipo' => 'barras', 'etiquetas' => $tiposLim, 'series' => $seriesLim,
                 'op' => ['apilado' => true, 'colores' => ['#f59e0b', '#3b82f6', '#4c7d5f']]],
                ['id' => 'cm-ing', 'titulo' => 'Internaciones por mes', 'nota' => 'Pacientes ingresados en los últimos 12 meses.',
                 'tipo' => 'barras', 'etiquetas' => $etM, 'series' => ['Ingresos' => $meses->map(fn ($m) => (int) ($porMes[$m->format('Y-m')]->n ?? 0))->all()]],
                ['id' => 'cm-est-m', 'titulo' => 'Estancia media por mes', 'nota' => 'Días promedio internado, según el mes de ingreso.',
                 'tipo' => 'linea', 'etiquetas' => $etM, 'series' => ['Días' => $meses->map(fn ($m) => round((float) ($porMes[$m->format('Y-m')]->e ?? 0), 1))->all()], 'op' => ['sufijo' => ' días']],
                ['id' => 'cm-mot', 'titulo' => 'Motivos de internación más frecuentes', 'nota' => 'Últimos 6 meses.', 'ancho' => true,
                 'tipo' => 'barras', 'etiquetas' => $motivos->keys()->all(), 'series' => ['Internaciones' => $motivos->values()->all()], 'op' => ['horizontal' => true]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Caja

    public function caja(): array
    {
        $hoy = Carbon::today()->toDateString();
        $pagosHoy = DB::table('PAGO as p')->join('METODO_PAGO as m', 'm.COD_MET', '=', 'p.COD_MET')
            ->where('p.EST_PAG', 'Confirmado')->whereDate('p.FEC_PAG', $hoy)
            ->get(['p.MON_PAG', 'p.TIP_PAG', 'p.FEC_PAG', 'm.DES_MET']);
        $base = DB::table('PAGO')->where('EST_PAG', 'Confirmado')->where('FEC_PAG', '<', Carbon::tomorrow());
        [$et, $anticipos] = $this->porDia((clone $base)->where('TIP_PAG', 'Adelanto'), 'FEC_PAG', 14, 'MON_PAG');
        [, $saldos] = $this->porDia((clone $base)->where('TIP_PAG', 'Total'), 'FEC_PAG', 14, 'MON_PAG');
        $metodo = $pagosHoy->groupBy('DES_MET')->map(fn ($g) => round($g->sum('MON_PAG'), 2));
        $porHora = $pagosHoy->groupBy(fn ($p) => substr($p->FEC_PAG, 11, 2) . ':00')->sortKeys()->map(fn ($g) => round($g->sum('MON_PAG'), 2));

        return [
            'kpis' => [
                ['v' => 'Bs ' . number_format($pagosHoy->sum('MON_PAG'), 2), 't' => 'Ingresos confirmados hoy', 'ico' => 'fa-sack-dollar'],
                ['v' => $pagosHoy->count(), 't' => 'Pagos de hoy', 'ico' => 'fa-receipt',
                 'd' => $pagosHoy->where('TIP_PAG', 'Adelanto')->count() . ' anticipos en línea · ' . $pagosHoy->where('TIP_PAG', 'Total')->count() . ' saldos'],
                ['v' => DB::table('COMPROBANTE')->where('EST_COM', 'Anulado')->whereDate('FEC_COM', $hoy)->count(), 't' => 'Comprobantes anulados hoy', 'ico' => 'fa-ban'],
            ],
            'graficos' => [
                ['id' => 'cj-14', 'titulo' => 'Ingresos de los últimos 14 días', 'nota' => 'Anticipos pagados en línea y saldos cobrados en caja.', 'ancho' => true,
                 'tipo' => 'barras', 'etiquetas' => $et, 'series' => ['Anticipos' => $anticipos, 'Saldos en caja' => $saldos], 'op' => ['apilado' => true, 'moneda' => true]],
                ['id' => 'cj-met', 'titulo' => 'Hoy por método de pago', 'nota' => 'Monto en bolivianos.', 'tipo' => 'dona',
                 'etiquetas' => $metodo->keys()->all(), 'valores' => $metodo->values()->all(), 'op' => ['moneda' => true]],
                ['id' => 'cj-hora', 'titulo' => 'Cobros de hoy por hora', 'nota' => 'Ayuda a planificar la atención en ventanilla.',
                 'tipo' => 'barras', 'etiquetas' => $porHora->keys()->all(), 'series' => ['Monto' => $porHora->values()->all()], 'op' => ['moneda' => true]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Laboratorio

    public function laboratorio(): array
    {
        $estados = DB::table('ORDEN_EXAMEN')->groupBy('EST_ORD')->selectRaw('COUNT(*)' . ' as n, ' . 'EST_ORD' . ' as k')->pluck('n', 'k');
        [$et, $ordenes] = $this->porDia(DB::table('ORDEN_EXAMEN'), 'FEC_ORD', 30);
        [, $publicados] = $this->porDia(DB::table('RESULTADO_EXAMEN')->where('EST_RES', 'Publicado'), 'FEC_RES', 30);
        $examenes = DB::table('ORDEN_EXAMEN as o')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
            ->where('o.FEC_ORD', '>=', Carbon::today()->subMonths(3)->toDateString())
            ->groupBy('s.NOM_SRV')->orderByDesc(DB::raw('COUNT(*)'))->limit(8)->selectRaw('COUNT(*)' . ' as n, ' . 's.NOM_SRV' . ' as k')->pluck('n', 'k');
        $pend = DB::table('ORDEN_EXAMEN')->whereIn('EST_ORD', ['Pendiente', 'Muestra tomada', 'En proceso']);

        return [
            'kpis' => [
                ['v' => (clone $pend)->count(), 't' => 'Órdenes en curso', 'ico' => 'fa-vials'],
                ['v' => (clone $pend)->where('PRI_ORD', 'Urgente')->count(), 't' => 'Urgentes en curso', 'ico' => 'fa-bolt'],
                ['v' => DB::table('RESULTADO_EXAMEN')->whereIn('EST_RES', ['Cargado', 'Validado'])->count(), 't' => 'Resultados sin publicar', 'ico' => 'fa-file-circle-check'],
                ['v' => DB::table('RESULTADO_EXAMEN')->where('CRI_RES', 1)->where('FEC_RES', '>=', Carbon::today()->subDays(30))->count(),
                 't' => 'Valores críticos (30 días)', 'ico' => 'fa-triangle-exclamation'],
            ],
            'graficos' => [
                ['id' => 'lb-30', 'titulo' => 'Órdenes y resultados · últimos 30 días', 'nota' => 'Órdenes recibidas frente a resultados publicados por día.', 'ancho' => true,
                 'tipo' => 'linea', 'etiquetas' => $et, 'series' => ['Órdenes recibidas' => $ordenes, 'Resultados publicados' => $publicados]],
                $this->dona('lb-est', 'Órdenes por estado', 'Flujo de la muestra (RN30).', $estados),
                ['id' => 'lb-ex', 'titulo' => 'Exámenes más solicitados', 'nota' => 'Últimos 3 meses.', 'tipo' => 'barras',
                 'etiquetas' => $examenes->keys()->all(), 'series' => ['Órdenes' => $examenes->values()->all()], 'op' => ['horizontal' => true]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Farmacia

    public function farmacia(): array
    {
        $hoy = Carbon::today()->toDateString();
        $limite = Carbon::today()->addDays(FarmaciaService::DIAS_ALERTA)->toDateString();
        $stock = DB::table('MEDICAMENTO as m')
            ->leftJoin('LOTE_MEDICAMENTO as l', fn ($j) => $j->on('l.COD_MED', '=', 'm.COD_MED')->where('l.FECVEN_LOT', '>=', $hoy))
            ->groupBy('m.DES_MED')->orderBy(DB::raw('COALESCE(SUM(l.CANDIS_LOT), 0)'))
            ->selectRaw('COALESCE(SUM(l.CANDIS_LOT), 0)' . ' as n, ' . 'm.DES_MED' . ' as k')->pluck('n', 'k');
        $lotes = DB::table('LOTE_MEDICAMENTO')->get(['FECVEN_LOT', 'CANDIS_LOT'])
            ->countBy(fn ($l) => $l->CANDIS_LOT <= 0 ? 'Agotado' : ($l->FECVEN_LOT < $hoy ? 'Vencido' : ($l->FECVEN_LOT <= $limite ? 'Por vencer' : 'Vigente')));
        $recetas = DB::table('RECETA')->groupBy('EST_REC')->selectRaw('COUNT(*)' . ' as n, ' . 'EST_REC' . ' as k')->pluck('n', 'k');
        $ventas = DB::table('VENTA')->where('EST_VEN', 'Emitida')->where('FEC_VEN', '<', Carbon::tomorrow());
        [$et, $montos] = $this->porDia($ventas, 'FEC_VEN', 14, 'TOT_VEN');

        return [
            'kpis' => [
                ['v' => (int) ($recetas['Activa'] ?? 0), 't' => 'Recetas por despachar', 'ico' => 'fa-prescription'],
                ['v' => (int) ($lotes['Por vencer'] ?? 0), 't' => 'Lotes por vencer (≤ ' . FarmaciaService::DIAS_ALERTA . ' días)', 'ico' => 'fa-hourglass-end'],
                ['v' => $stock->filter(fn ($s) => $s < 30)->count(), 't' => 'Medicamentos con menos de 30 unidades', 'ico' => 'fa-box-open'],
                ['v' => 'Bs ' . number_format((float) (clone $ventas)->whereDate('FEC_VEN', $hoy)->sum('TOT_VEN'), 2), 't' => 'Ventas de hoy', 'ico' => 'fa-cash-register'],
            ],
            'graficos' => [
                ['id' => 'fm-stock', 'titulo' => 'Stock vigente por medicamento', 'nota' => 'Unidades en lotes no vencidos; los de menos stock arriba.', 'alto' => true,
                 'tipo' => 'barras', 'etiquetas' => $stock->keys()->all(), 'series' => ['Unidades' => $stock->values()->map(fn ($v) => (int) $v)->all()], 'op' => ['horizontal' => true]],
                $this->dona('fm-lotes', 'Lotes por vencimiento', 'PEPS: sale primero el lote que vence antes.', $lotes),
                $this->dona('fm-rec', 'Recetas por estado', 'Activas pendientes de despacho y completadas.', $recetas),
                ['id' => 'fm-ventas', 'titulo' => 'Ventas en mostrador · últimos 14 días', 'nota' => 'Monto emitido por día (sin anuladas).', 'ancho' => true,
                 'tipo' => 'barras', 'etiquetas' => $et, 'series' => ['Ventas' => $montos], 'op' => ['moneda' => true]],
            ],
        ];
    }

    // ------------------------------------------------------------------ Paciente

    public function paciente(string $codPac): array
    {
        $estados = DB::table('CITA')->where('COD_PAC', $codPac)->groupBy('EST_CIT')->selectRaw('COUNT(*)' . ' as n, ' . 'EST_CIT' . ' as k')->pluck('n', 'k');
        $esp = DB::table('CITA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->where('c.COD_PAC', $codPac)->where('c.EST_CIT', 'Atendida')
            ->groupBy('es.DES_ESP')->orderByDesc(DB::raw('COUNT(*)'))->selectRaw('COUNT(*)' . ' as n, ' . 'es.DES_ESP' . ' as k')->pluck('n', 'k');

        $meses = collect(range(11, 0))->map(fn ($i) => Carbon::today()->startOfMonth()->subMonths($i));
        $pagos = DB::table('PAGO as p')->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')
            ->where('c.COD_PAC', $codPac)->where('p.EST_PAG', 'Confirmado')
            ->where('p.FEC_PAG', '>=', $meses->first()->toDateString())
            ->groupBy(DB::raw("DATE_FORMAT(p.FEC_PAG, '%Y-%m')"))->selectRaw('SUM(p.MON_PAG)' . ' as n, ' . "DATE_FORMAT(p.FEC_PAG, '%Y-%m')" . ' as k')->pluck('n', 'k');

        $signos = DB::table('SIGNO_VITAL')->where('COD_PAC', $codPac)->orderByDesc('FEC_SIV')->limit(12)->get()->reverse()->values();

        $g = [
            $this->dona('pc-est', 'Mis citas por estado', 'Todo tu historial en la clínica.', $estados),
            ['id' => 'pc-esp', 'titulo' => 'Mis atenciones por especialidad', 'nota' => 'Consultas a las que asististe.',
             'tipo' => 'barras', 'etiquetas' => $esp->keys()->all(), 'series' => ['Atenciones' => $esp->values()->all()], 'op' => ['horizontal' => true]],
            ['id' => 'pc-pag', 'titulo' => 'Mis pagos · últimos 12 meses', 'nota' => 'Anticipos y saldos confirmados, en bolivianos.', 'ancho' => true,
             'tipo' => 'barras', 'etiquetas' => $meses->map(fn ($m) => ucfirst($m->locale('es')->isoFormat('MMM YY')))->all(),
             'series' => ['Pagado' => $meses->map(fn ($m) => round((float) ($pagos[$m->format('Y-m')] ?? 0), 2))->all()], 'op' => ['moneda' => true]],
        ];
        if ($signos->count() >= 2) {
            $g[] = ['id' => 'pc-pa', 'titulo' => 'Mi presión arterial', 'nota' => 'Últimos registros de enfermería (mmHg).', 'ancho' => true,
                    'tipo' => 'linea', 'etiquetas' => $signos->map(fn ($s) => Carbon::parse($s->FEC_SIV)->format('d/m H:i'))->all(),
                    'series' => ['Sistólica' => $signos->pluck('SIS_SIV')->all(), 'Diastólica' => $signos->pluck('DIA_SIV')->all()]];
        }
        return ['graficos' => $g];
    }

    // ------------------------------------------------------------------ Auditoría

    public function auditoria(): array
    {
        $desde = Carbon::today()->subDays(29);
        $base = DB::table('AUDITORIA')->where('FEC_AUD', '>=', $desde);
        [$et, $total] = $this->porDia(DB::table('AUDITORIA'), 'FEC_AUD', 30);
        [, $altas] = $this->porDia(DB::table('AUDITORIA')->whereIn('CRI_AUD', ['Alta', 'Crítica']), 'FEC_AUD', 30);
        $crit = (clone $base)->groupBy('CRI_AUD')->selectRaw('COUNT(*)' . ' as n, ' . 'CRI_AUD' . ' as k')->pluck('n', 'k');
        $orden = ['Baja', 'Media', 'Alta', 'Crítica'];
        $crit = collect($orden)->mapWithKeys(fn ($c) => [$c => (int) ($crit[$c] ?? 0)]);
        $dom = (clone $base)->join('DOMINIO_AUD as d', 'd.COD_DOM', '=', 'AUDITORIA.COD_DOM')
            ->groupBy('d.DES_DOM')->selectRaw('COUNT(*)' . ' as n, ' . 'd.DES_DOM' . ' as k')->pluck('n', 'k');
        $acc = (clone $base)->groupBy('ACC_AUD')->orderByDesc(DB::raw('COUNT(*)'))->limit(10)->selectRaw('COUNT(*)' . ' as n, ' . 'ACC_AUD' . ' as k')->pluck('n', 'k');

        return [
            'kpis' => [
                ['v' => (int) $crit->sum(), 't' => 'Eventos en 30 días', 'ico' => 'fa-list-check'],
                ['v' => $crit['Crítica'], 't' => 'Críticos en 30 días', 'ico' => 'fa-shield-halved'],
                ['v' => (clone $base)->where('ACC_AUD', 'Acceso emergencia')->count(), 't' => 'Accesos de emergencia', 'ico' => 'fa-unlock'],
                ['v' => (clone $base)->where('ACC_AUD', 'like', 'Login fallido%')->count() + (clone $base)->where('ACC_AUD', 'like', '%bloque%')->count(),
                 't' => 'Intentos fallidos y bloqueos', 'ico' => 'fa-user-lock'],
            ],
            'graficos' => [
                ['id' => 'au-30', 'titulo' => 'Eventos por día · últimos 30 días', 'nota' => 'Total de eventos y los de criticidad Alta o Crítica.', 'ancho' => true,
                 'tipo' => 'linea', 'etiquetas' => $et, 'series' => ['Todos' => $total, 'Alta o Crítica' => $altas]],
                $this->dona('au-crit', 'Por criticidad', 'RN45: Baja, Media, Alta y Crítica.', $crit),
                ['id' => 'au-dom', 'titulo' => 'Por dominio', 'nota' => 'Usuarios, seguridad, clínico y financiero.', 'tipo' => 'barras',
                 'etiquetas' => $dom->keys()->all(), 'series' => ['Eventos' => $dom->values()->all()], 'op' => ['horizontal' => true]],
                ['id' => 'au-acc', 'titulo' => 'Acciones más registradas', 'nota' => 'Últimos 30 días.', 'tipo' => 'barras', 'ancho' => true,
                 'etiquetas' => $acc->keys()->all(), 'series' => ['Eventos' => $acc->values()->all()], 'op' => ['horizontal' => true]],
            ],
        ];
    }
}
