@extends('layouts.admin')

@section('titulo', 'Mis estadísticas')
@section('encabezado', 'Portal Médico')

@section('contenido')
    @include('partials.graficos')

    <h1>Mis estadísticas</h1>
    <p class="sub">{{ $medico->TIT_EMP }} {{ $medico->NOM_EMP }} {{ $medico->APE_EMP }} · {{ $medico->DES_ESP }}</p>

    @include('partials.filtro-reporte', ['ruta' => route('medico.estadisticas'), 'sinMedico' => true, 'sinEspecialidad' => true])
    <p class="rango-actual"><i class="fas fa-calendar"></i> Mostrando {{ $f['etiqueta'] }}</p>

    <div class="kpis">
        <div class="kpi"><i class="fas fa-user-check ico"></i><div class="v">{{ $kpi['atendidas'] }}</div><div class="t">Pacientes atendidos (visitas)</div></div>
        <div class="kpi"><i class="fas fa-users ico"></i><div class="v">{{ $kpi['pacientes'] }}</div><div class="t">Pacientes distintos</div><div class="d" style="color:var(--suave);">{{ $kpi['nuevos'] }} nuevos</div></div>
        <div class="kpi"><i class="fas fa-calendar-check ico"></i><div class="v">{{ $kpi['agendadas'] }}</div><div class="t">Citas agendadas</div></div>
        <div class="kpi"><i class="fas fa-percent ico"></i><div class="v">{{ $kpi['asistencia'] !== null ? $kpi['asistencia'] . ' %' : '—' }}</div><div class="t">Asistencia</div><div class="d" style="color:var(--suave);">{{ $kpi['noAsistio'] }} no asistieron</div></div>
        <div class="kpi"><i class="fas fa-chart-pie ico"></i><div class="v">{{ $participacion !== null ? $participacion . ' %' : '—' }}</div><div class="t">De las atenciones de {{ $medico->DES_ESP }}</div></div>
    </div>

    <div class="graficos">
        <div class="card ancho-total">
            <h2><i class="fas fa-chart-line"></i>Mis atenciones</h2>
            <p class="nota">Pacientes atendidos frente a citas agendadas.</p>
            <div class="lienzo"><canvas id="g-serie"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-ranking-star"></i>Mis pacientes más frecuentes</h2>
            <p class="nota">Visitas atendidas en el periodo.</p>
            <div class="lienzo"><canvas id="g-top"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-notes-medical"></i>Diagnósticos más frecuentes</h2>
            <p class="nota">En mis consultas del periodo.</p>
            <div class="lienzo"><canvas id="g-diag"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-user-doctor"></i>Médicos de {{ $medico->DES_ESP }}</h2>
            <p class="nota">Pacientes atendidos por cada médico de la especialidad.</p>
            <div class="lienzo bajo"><canvas id="g-colegas"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-people-group"></i>Mis pacientes</h2>
            <p class="nota">Por sexo y edad.</p>
            <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap: 10px;">
                <div class="lienzo bajo"><canvas id="g-sexo"></canvas></div>
                <div class="lienzo bajo"><canvas id="g-edad"></canvas></div>
            </div>
        </div>

        <div class="card ancho-total">
            <h2><i class="fas fa-hospital"></i>En la clínica: especialidad más frecuentada cada mes</h2>
            <p class="nota">Todas las especialidades, 12 meses. Sirve para ver en qué meses sube la demanda de cada área.</p>
            <div class="lienzo alto"><canvas id="g-espmes"></canvas></div>
            <div class="tabla" style="margin-top: 14px;">
                <table>
                    <thead><tr>@foreach ($espPorMes['ganadoras'] as $g)<th style="text-align:center;">{{ $g['mes'] }}</th>@endforeach</tr></thead>
                    <tbody><tr>@foreach ($espPorMes['ganadoras'] as $g)<td style="text-align:center; font-size:12px;"><strong>{{ $g['especialidad'] }}</strong><br><small>{{ $g['atenciones'] }} at.</small></td>@endforeach</tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <h2>Mis pacientes más frecuentes</h2>
    <div class="card tabla">
        <table class="podio">
            <thead><tr><th>#</th><th>Paciente</th><th>Edad</th><th class="derecha">Visitas</th><th>Última visita</th><th class="no-imprimir"></th></tr></thead>
            <tbody>
                @forelse ($topPacientes as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}<br><small>CI {{ $p->CI_PAC }}</small></td>
                        <td>{{ $p->EDAD }}</td>
                        <td class="derecha"><strong>{{ $p->VISITAS }}</strong></td>
                        <td>{{ \Carbon\Carbon::parse($p->ULTIMA)->format('d/m/Y') }}</td>
                        <td class="no-imprimir"><a class="btn sm" href="{{ route('reportes.paciente', $p->COD_PAC) }}"><i class="fas fa-file-medical"></i> Reporte</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio">Sin atenciones en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    CIES.linea('g-serie', @json($serie['etiquetas']), { 'Atendidos': @json($serie['atendidas']), 'Citas agendadas': @json($serie['agendadas']) });
    CIES.barras('g-top', {!! json_encode($topPacientes->map(fn ($p) => $p->NOM_PAC . ' ' . explode(' ', $p->APE_PAC)[0])->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Visitas': @json($topPacientes->pluck('VISITAS')->values()) }, { horizontal: true, colores: '#cc783c', sufijo: ' visitas' });
    CIES.barras('g-diag', @json($diagnosticos->keys()), { 'Consultas': @json($diagnosticos->values()) }, { horizontal: true, multicolor: true });
    CIES.barras('g-colegas', {!! json_encode($colegas->map(fn ($m) => $m->TIT_EMP . ' ' . explode(' ', $m->APE_EMP)[0])->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Pacientes distintos': @json($colegas->pluck('PACIENTES')->values()), 'Atenciones': @json($colegas->pluck('ATENDIDAS')->values()) });
    CIES.dona('g-sexo', @json(array_keys($perfil['sexo'])), @json(array_values($perfil['sexo'])), { colores: ['#cc783c', '#4c7d5f'] });
    CIES.barras('g-edad', @json(array_keys($perfil['edades'])), { 'Pacientes': @json(array_values($perfil['edades'])) }, { colores: '#4c7d5f' });
    CIES.barras('g-espmes', @json($espPorMes['etiquetas']), @json($espPorMes['series']), { apilado: true });
</script>
@endpush
