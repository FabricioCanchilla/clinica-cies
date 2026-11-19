@extends('layouts.admin')

@section('titulo', 'Reportes')

@php
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
    $maxEsp = max(1, (int) $porEspecialidad->max('ATENDIDAS'));
    $maxMed = max(1, (int) $porMedico->max('ATENDIDAS'));
@endphp

@section('contenido')
    @include('partials.graficos')

    <h1>Reportes de atención</h1>
    <p class="sub">Quién visita la clínica, qué especialidades se frecuentan y cuántos pacientes atiende cada médico.</p>

    @include('partials.filtro-reporte', [
        'ruta'      => route('admin.reportes'),
        'exportar'  => route('admin.reportes.excel', request()->query()),
    ])
    <p class="rango-actual"><i class="fas fa-calendar"></i> Mostrando {{ $f['etiqueta'] }}
        @if ($f['especialidad'] !== '') · {{ $catalogos['especialidades']->firstWhere('COD_ESP', $f['especialidad'])->DES_ESP ?? '' }} @endif
        @if ($f['medico'] !== '') · {{ optional($catalogos['medicos']->firstWhere('COD_EMP', $f['medico']), fn ($m) => "{$m->TIT_EMP} {$m->NOM_EMP} {$m->APE_EMP}") }} @endif
    </p>

    <div class="kpis">
        <div class="kpi"><i class="fas fa-user-check ico"></i><div class="v">{{ number_format($kpi['atendidas']) }}</div><div class="t">Atenciones (visitas)</div></div>
        <div class="kpi"><i class="fas fa-users ico"></i><div class="v">{{ number_format($kpi['pacientes']) }}</div><div class="t">Pacientes distintos</div><div class="d" style="color:var(--suave);">{{ $kpi['nuevos'] }} nuevos</div></div>
        <div class="kpi"><i class="fas fa-calendar-check ico"></i><div class="v">{{ number_format($kpi['agendadas']) }}</div><div class="t">Citas agendadas</div></div>
        <div class="kpi"><i class="fas fa-percent ico"></i><div class="v">{{ $kpi['asistencia'] !== null ? $kpi['asistencia'] . ' %' : '—' }}</div><div class="t">Asistencia</div><div class="d" style="color:var(--suave);">{{ $kpi['noAsistio'] }} inasistencias</div></div>
        <div class="kpi"><i class="fas fa-ban ico"></i><div class="v">{{ number_format($kpi['canceladas']) }}</div><div class="t">Cancelaciones</div></div>
        <div class="kpi"><i class="fas fa-sack-dollar ico"></i><div class="v" style="font-size:22px;">{{ $bs($kpi['ingresos']) }}</div><div class="t">Ingresos confirmados</div></div>
    </div>

    <div class="graficos">
        <div class="card ancho-total">
            <h2><i class="fas fa-chart-line"></i>Evolución de las atenciones</h2>
            <p class="nota">{{ $f['agrupar'] === 'hora' ? 'Por hora del día' : ($f['agrupar'] === 'dia' ? 'Por día' : 'Por mes') }}: pacientes atendidos frente a citas agendadas.</p>
            <div class="lienzo"><canvas id="g-serie"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-stethoscope"></i>Atenciones por especialidad</h2>
            <p class="nota">Qué especialidades se frecuentan más en el periodo.</p>
            <div class="lienzo alto"><canvas id="g-esp"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-user-doctor"></i>Pacientes que visitan a cada médico</h2>
            <p class="nota">Pacientes distintos y atenciones por médico.</p>
            <div class="lienzo alto"><canvas id="g-medico"></canvas></div>
        </div>

        <div class="card ancho-total">
            <h2><i class="fas fa-calendar-alt"></i>Especialidad más frecuentada cada mes</h2>
            <p class="nota">12 meses hasta {{ \App\Services\ReporteService::MESES_LARGOS[\Carbon\Carbon::parse($f['hasta'])->month] }} de {{ \Carbon\Carbon::parse($f['hasta'])->year }}.</p>
            <div class="lienzo alto"><canvas id="g-espmes"></canvas></div>
            <div class="tabla" style="margin-top: 14px;">
                <table>
                    <thead><tr><th>Mes</th><th>Especialidad más frecuentada</th><th class="derecha">Atenciones</th><th class="derecha">% del mes</th><th>En segundo lugar</th><th class="derecha">Total del mes</th></tr></thead>
                    <tbody>
                        @foreach (array_reverse($espPorMes['ganadoras']) as $g)
                            <tr>
                                <td><strong>{{ $g['mes'] }}</strong></td>
                                <td>{{ $g['especialidad'] }}</td>
                                <td class="derecha">{{ $g['atenciones'] }}</td>
                                <td class="derecha">{{ $g['porcentaje'] }} %</td>
                                <td>{{ $g['segunda'] }}</td>
                                <td class="derecha">{{ $g['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-ranking-star"></i>Pacientes que más visitan la clínica</h2>
            <p class="nota">Visitas atendidas en el periodo.</p>
            <div class="lienzo alto"><canvas id="g-top"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-circle-half-stroke"></i>Estado de las citas</h2>
            <p class="nota">Del total de citas agendadas en el periodo.</p>
            <div class="lienzo"><canvas id="g-estados"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-clock"></i>Horas con más atenciones</h2>
            <p class="nota">Ayuda a reforzar recepción y caja en las horas pico.</p>
            <div class="lienzo"><canvas id="g-horas"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-people-group"></i>Perfil de los pacientes atendidos</h2>
            <p class="nota">Por sexo y grupo de edad.</p>
            <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap: 10px;">
                <div class="lienzo bajo"><canvas id="g-sexo"></canvas></div>
                <div class="lienzo bajo"><canvas id="g-edad"></canvas></div>
            </div>
        </div>
    </div>

    <h2>Detalle por especialidad</h2>
    <div class="card tabla" style="margin-bottom: 24px;">
        <table>
            <thead><tr><th>Especialidad</th><th class="derecha">Atenciones</th><th></th><th class="derecha">Pacientes distintos</th><th class="derecha">Inasistencias</th><th class="derecha">Citas agendadas</th><th class="derecha">Ingresos</th></tr></thead>
            <tbody>
                @forelse ($porEspecialidad as $r)
                    <tr>
                        <td><strong>{{ $r->DES_ESP }}</strong></td>
                        <td class="derecha">{{ $r->ATENDIDAS }}</td>
                        <td style="width:18%;"><div class="barra-mini"><span style="width: {{ round($r->ATENDIDAS * 100 / $maxEsp) }}%;"></span></div></td>
                        <td class="derecha">{{ $r->PACIENTES }}</td>
                        <td class="derecha">{{ $r->INASISTENCIAS }}</td>
                        <td class="derecha">{{ (int) $r->AGENDADAS }}</td>
                        <td class="derecha nowrap">{{ $bs($r->INGRESOS) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="vacio">Sin datos para el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Detalle por médico</h2>
    <div class="card tabla" style="margin-bottom: 24px;">
        <table>
            <thead><tr><th>Médico</th><th>Especialidad</th><th class="derecha">Pacientes distintos</th><th class="derecha">Atenciones</th><th></th><th class="derecha">Inasistencias</th><th class="derecha">Cancelaciones</th></tr></thead>
            <tbody>
                @forelse ($porMedico as $r)
                    <tr>
                        <td><a href="{{ route('admin.reportes', array_merge(request()->query(), ['medico' => $r->COD_EMP])) }}">{{ $r->NOMBRE }}</a></td>
                        <td>{{ $r->DES_ESP }}</td>
                        <td class="derecha"><strong>{{ $r->PACIENTES }}</strong></td>
                        <td class="derecha">{{ $r->ATENDIDAS }}</td>
                        <td style="width:16%;"><div class="barra-mini"><span style="width: {{ round($r->ATENDIDAS * 100 / $maxMed) }}%;"></span></div></td>
                        <td class="derecha">{{ $r->INASISTENCIAS }}</td>
                        <td class="derecha">{{ $r->CANCELADAS }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="vacio">Sin datos para el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Pacientes más frecuentes</h2>
    <div class="card tabla">
        <table class="podio">
            <thead><tr><th>#</th><th>Paciente</th><th>Edad</th><th class="derecha">Visitas</th><th class="derecha">Especialidades</th><th>Especialidad que más visita</th><th>Última visita</th><th class="no-imprimir"></th></tr></thead>
            <tbody>
                @forelse ($topPacientes as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}<br><small>{{ $p->COD_PAC }} · CI {{ $p->CI_PAC }}</small></td>
                        <td>{{ $p->EDAD }}</td>
                        <td class="derecha"><strong>{{ $p->VISITAS }}</strong></td>
                        <td class="derecha">{{ (int) $p->ESPECIALIDADES }}</td>
                        <td>{{ $p->FAVORITA }}</td>
                        <td>{{ \Carbon\Carbon::parse($p->ULTIMA)->format('d/m/Y') }}</td>
                        <td class="no-imprimir"><a class="btn sm" href="{{ route('reportes.paciente', $p->COD_PAC) }}"><i class="fas fa-file-medical"></i> Reporte</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="vacio">Sin atenciones en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    CIES.linea('g-serie', @json($serie['etiquetas']), { 'Atendidos': @json($serie['atendidas']), 'Citas agendadas': @json($serie['agendadas']) });
    CIES.barras('g-esp', @json($porEspecialidad->pluck('DES_ESP')->values()),
        { 'Atenciones': @json($porEspecialidad->pluck('ATENDIDAS')->values()), 'Pacientes distintos': @json($porEspecialidad->pluck('PACIENTES')->values()) },
        { horizontal: true });
    CIES.barras('g-medico',
        {!! json_encode($porMedico->map(fn ($m) => $m->TIT_EMP . ' ' . explode(' ', $m->APE_EMP)[0] . ' · ' . \Illuminate\Support\Str::limit($m->DES_ESP, 14, '.'))->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Pacientes distintos': @json($porMedico->pluck('PACIENTES')->values()), 'Atenciones': @json($porMedico->pluck('ATENDIDAS')->values()) },
        { horizontal: true });
    CIES.barras('g-espmes', @json($espPorMes['etiquetas']), @json($espPorMes['series']), { apilado: true });
    CIES.barras('g-top', {!! json_encode($topPacientes->map(fn ($p) => $p->NOM_PAC . ' ' . explode(' ', $p->APE_PAC)[0])->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Visitas': @json($topPacientes->pluck('VISITAS')->values()) }, { horizontal: true, colores: '#cc783c', sufijo: ' visitas' });
    CIES.dona('g-estados', @json(array_keys($kpi['porEstado'])), @json(array_values($kpi['porEstado'])),
        { colores: ['#3b82f6', '#4c7d5f', '#eab308', '#ec4899', '#ef4444', '#94a3b8'] });
    CIES.barras('g-horas', @json(array_keys($horas)), { 'Atenciones': @json(array_values($horas)) }, { colores: '#4c7d5f' });
    CIES.dona('g-sexo', @json(array_keys($perfil['sexo'])), @json(array_values($perfil['sexo'])), { colores: ['#cc783c', '#4c7d5f'] });
    CIES.barras('g-edad', @json(array_keys($perfil['edades'])), { 'Pacientes': @json(array_values($perfil['edades'])) }, { colores: '#4c7d5f' });
</script>
@endpush
