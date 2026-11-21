@extends('layouts.admin')

@section('titulo', 'Dashboard')

@php
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
    $variacion = function ($actual, $anterior) {
        if (!$anterior) return null;
        return round(($actual - $anterior) * 100 / $anterior, 1);
    };
    $vAt = $variacion($kpi['vsAtendidas'], $kpiAnterior['atendidas']);
    $vIng = $variacion($kpi['vsIngresos'], $kpiAnterior['ingresos']);
    $topMes = $espPorMes['ganadoras'] ? end($espPorMes['ganadoras']) : null;
@endphp

@section('contenido')
    @include('partials.graficos')

    <div class="card-cabecera" style="margin-bottom: 18px;">
        <div>
            <h1>Dashboard</h1>
            <p class="sub" style="margin-bottom:0;">Clínica CIES La Paz · {{ ucfirst($mes['etiqueta']) }}</p>
        </div>
        <a href="{{ route('admin.reportes') }}" class="btn principal no-imprimir"><i class="fas fa-chart-pie"></i> Ver reportes detallados</a>
    </div>

    {{-- Indicadores del mes --}}
    <div class="kpis">
        <div class="kpi">
            <i class="fas fa-user-check ico"></i>
            <div class="v">{{ number_format($kpi['atendidas']) }}</div>
            <div class="t">Atenciones este mes</div>
            @if ($vAt !== null)
                <div class="d {{ $vAt >= 0 ? 'sube' : 'baja' }}"><i class="fas fa-arrow-{{ $vAt >= 0 ? 'up' : 'down' }}"></i> {{ abs($vAt) }} % vs. el mismo tramo del mes anterior</div>
            @endif
        </div>
        <div class="kpi">
            <i class="fas fa-users ico"></i>
            <div class="v">{{ number_format($kpi['pacientes']) }}</div>
            <div class="t">Pacientes atendidos este mes</div>
            <div class="d" style="color:var(--suave);">{{ $kpi['nuevos'] }} vinieron por primera vez</div>
        </div>
        <div class="kpi">
            <i class="fas fa-sack-dollar ico"></i>
            <div class="v">{{ $bs($kpi['ingresos']) }}</div>
            <div class="t">Ingresos confirmados del mes</div>
            @if ($vIng !== null)
                <div class="d {{ $vIng >= 0 ? 'sube' : 'baja' }}"><i class="fas fa-arrow-{{ $vIng >= 0 ? 'up' : 'down' }}"></i> {{ abs($vIng) }} % vs. el mismo tramo del mes anterior</div>
            @endif
        </div>
        <div class="kpi">
            <i class="fas fa-calendar-xmark ico"></i>
            <div class="v">{{ $kpi['asistencia'] !== null ? $kpi['asistencia'] . ' %' : '—' }}</div>
            <div class="t">Asistencia a las citas</div>
            <div class="d" style="color:var(--suave);">{{ $kpi['noAsistio'] }} inasistencias · {{ $kpi['canceladas'] }} cancelaciones</div>
        </div>
        <div class="kpi">
            <i class="fas fa-stethoscope ico"></i>
            <div class="v" style="font-size:19px;">{{ $topMes['especialidad'] ?? '—' }}</div>
            <div class="t">Especialidad más frecuentada del mes</div>
            @if ($topMes)
                <div class="d" style="color:var(--suave);">{{ $topMes['atenciones'] }} atenciones ({{ $topMes['porcentaje'] }} %)</div>
            @endif
        </div>
        <div class="kpi">
            <i class="fas fa-bed ico"></i>
            <div class="v">{{ $camas['ocupacion'] }} %</div>
            <div class="t">Ocupación de camas ahora</div>
            <div class="d" style="color:var(--suave);">{{ $camas['estados']['Disponible'] }} libres · {{ $camas['estados']['Limpieza'] }} fuera de servicio por limpieza
                @if ($camas['limpieza']['pendientes'] > 0)<br>{{ $camas['limpieza']['pendientes'] }} tarea(s) de limpieza abiertas @endif</div>
        </div>
    </div>

    <div class="graficos">
        <div class="card ancho-total">
            <h2><i class="fas fa-chart-line"></i>Atenciones de los últimos 30 días</h2>
            <p class="nota">Pacientes atendidos por día frente a las citas que se agendaron.</p>
            <div class="lienzo"><canvas id="g-serie"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-ranking-star"></i>Pacientes que más visitan la clínica</h2>
            <p class="nota">Visitas atendidas en los últimos 12 meses.</p>
            <div class="lienzo alto"><canvas id="g-top"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-stethoscope"></i>Atenciones por especialidad · este mes</h2>
            <p class="nota">Cuántas consultas atendió cada especialidad.</p>
            <div class="lienzo alto"><canvas id="g-esp"></canvas></div>
        </div>

        <div class="card ancho-total">
            <h2><i class="fas fa-calendar-alt"></i>Especialidad más frecuentada cada mes</h2>
            <p class="nota">Atenciones por especialidad en los últimos 12 meses (las 6 más concurridas; el resto en «Otras»).</p>
            <div class="lienzo alto"><canvas id="g-espmes"></canvas></div>
            <div class="tabla" style="margin-top: 14px;">
                <table>
                    <thead><tr>@foreach ($espPorMes['ganadoras'] as $g)<th style="text-align:center;">{{ $g['mes'] }}</th>@endforeach</tr></thead>
                    <tbody><tr>@foreach ($espPorMes['ganadoras'] as $g)<td style="text-align:center; font-size:12px;"><strong>{{ $g['especialidad'] }}</strong><br><small>{{ $g['atenciones'] }} at.</small></td>@endforeach</tr></tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-user-doctor"></i>Pacientes atendidos por médico · este mes</h2>
            <p class="nota">Pacientes distintos y total de atenciones de cada médico.</p>
            <div class="lienzo alto"><canvas id="g-medico"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-circle-half-stroke"></i>Estado de las citas · este mes</h2>
            <p class="nota">Atendidas, por atender, inasistencias y cancelaciones.</p>
            <div class="lienzo"><canvas id="g-estados"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-sack-dollar"></i>Ingresos de los últimos 12 meses</h2>
            <p class="nota">Anticipos y saldos confirmados.</p>
            <div class="lienzo"><canvas id="g-ingresos"></canvas></div>
        </div>

        <div class="card">
            <h2><i class="fas fa-people-group"></i>¿Quiénes nos visitan?</h2>
            <p class="nota">Pacientes atendidos en los últimos 12 meses, por sexo y edad.</p>
            <div style="display:grid; grid-template-columns: 1fr 1.3fr; gap: 10px;">
                <div class="lienzo bajo"><canvas id="g-sexo"></canvas></div>
                <div class="lienzo bajo"><canvas id="g-edad"></canvas></div>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-bed"></i>Camas de internación</h2>
            <p class="nota">Estado actual de las {{ $camas['total'] }} camas.</p>
            <div class="lienzo bajo"><canvas id="g-camas"></canvas></div>
            <p class="nota" style="margin: 10px 0 0;">
                Limpiezas pendientes: <strong>{{ $camas['limpieza']['pendientes'] }}</strong>
                ({{ $camas['limpieza']['terminales'] }} desinfección terminal)
                @if ($camas['limpieza']['promedio'] !== null)
                    · Tiempo medio hasta liberar una cama: <strong>{{ $camas['limpieza']['promedio'] }} min</strong>
                @endif
            </p>
        </div>

        <div class="card">
            <h2><i class="fas fa-database"></i>Registros del sistema</h2>
            <table>
                <tbody>
                    @foreach ($totales as $etiqueta => $total)
                        <tr><td>{{ $etiqueta }}</td><td class="derecha"><strong>{{ number_format($total) }}</strong></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card-cabecera">
            <h2><i class="fas fa-ranking-star"></i>Detalle de los pacientes más frecuentes</h2>
        </div>
        <div class="tabla">
            <table class="podio">
                <thead><tr><th>#</th><th>Paciente</th><th>Edad</th><th>Visitas (12 meses)</th><th>Especialidad que más visita</th><th>Última visita</th><th></th></tr></thead>
                <tbody>
                    @forelse ($topPacientes as $i => $p)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}<br><small>{{ $p->COD_PAC }} · CI {{ $p->CI_PAC }}</small></td>
                            <td>{{ $p->EDAD }}</td>
                            <td><strong>{{ $p->VISITAS }}</strong></td>
                            <td>{{ $p->FAVORITA }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->ULTIMA)->format('d/m/Y') }}</td>
                            <td><a class="btn sm" href="{{ route('reportes.paciente', $p->COD_PAC) }}"><i class="fas fa-file-medical"></i> Reporte</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="vacio">Todavía no hay atenciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-user-plus"></i>Últimos usuarios registrados</h2>
        <table>
            <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th></tr></thead>
            <tbody>
                @foreach ($ultimos as $u)
                    <tr>
                        <td>{{ $u->USR_USR }}</td>
                        <td>{{ $u->DES_ROL }}</td>
                        <td><span class="badge {{ strtolower($u->EST_USR) }}">{{ $u->EST_USR }}</span></td>
                        <td>{{ $u->FECULT_USR ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    CIES.linea('g-serie', @json($serie30['etiquetas']), { 'Atendidos': @json($serie30['atendidas']), 'Citas agendadas': @json($serie30['agendadas']) });

    CIES.barras('g-top',
        {!! json_encode($topPacientes->map(fn ($p) => $p->NOM_PAC . ' ' . explode(' ', $p->APE_PAC)[0])->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Visitas': @json($topPacientes->pluck('VISITAS')->values()) },
        { horizontal: true, colores: '#cc783c', sufijo: ' visitas' });

    CIES.barras('g-esp',
        @json($porEspecialidad->pluck('DES_ESP')->values()),
        { 'Atenciones': @json($porEspecialidad->pluck('ATENDIDAS')->values()) },
        { horizontal: true, multicolor: true });

    CIES.barras('g-espmes', @json($espPorMes['etiquetas']), @json($espPorMes['series']), { apilado: true });

    CIES.barras('g-medico',
        {!! json_encode($porMedico->map(fn ($m) => $m->TIT_EMP . ' ' . explode(' ', $m->APE_EMP)[0] . ' · ' . \Illuminate\Support\Str::limit($m->DES_ESP, 14, '.'))->values(), JSON_UNESCAPED_UNICODE) !!},
        { 'Pacientes distintos': @json($porMedico->pluck('PACIENTES')->values()), 'Atenciones': @json($porMedico->pluck('ATENDIDAS')->values()) },
        { horizontal: true });

    CIES.dona('g-estados', @json(array_keys($kpi['porEstado'])), @json(array_values($kpi['porEstado'])),
        { colores: ['#3b82f6', '#4c7d5f', '#eab308', '#ec4899', '#ef4444', '#94a3b8'] });

    CIES.barras('g-ingresos', @json($ingresos['etiquetas']), { 'Ingresos': @json($ingresos['valores']) }, { moneda: true });

    CIES.dona('g-sexo', @json(array_keys($perfil['sexo'])), @json(array_values($perfil['sexo'])), { colores: ['#cc783c', '#4c7d5f'] });
    CIES.barras('g-edad', @json(array_keys($perfil['edades'])), { 'Pacientes': @json(array_values($perfil['edades'])) }, { colores: '#4c7d5f' });

    CIES.dona('g-camas', @json(array_keys($camas['estados'])), @json(array_values($camas['estados'])),
        { colores: ['#4c7d5f', '#e11d48', '#eab308'] });
</script>
@endpush
