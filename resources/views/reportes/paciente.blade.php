@extends('layouts.admin')

@section('titulo', 'Reporte del paciente')

@php
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
    $f = fn ($d, $fmt = 'd/m/Y') => $d ? \Carbon\Carbon::parse($d)->format($fmt) : '—';
    $r = $resumen;
@endphp

@section('contenido')
    @include('partials.graficos')

    <div class="card-cabecera">
        <div>
            <h1>{{ $propio ? 'Mi reporte de atención' : 'Reporte del paciente' }}</h1>
            <p class="sub" style="margin-bottom: 0;">Actividad completa en la Clínica CIES La Paz · generado el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        <div class="acciones no-imprimir">
            <a href="{{ $pdfUrl }}" target="_blank" class="btn acento"><i class="fas fa-file-pdf"></i> Descargar PDF</a>
            <button type="button" class="btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
            @unless ($propio)
                <a href="{{ route('reportes.pacientes') }}" class="btn"><i class="fas fa-arrow-left"></i> Otro paciente</a>
            @endunless
        </div>
    </div>

    <div class="bienvenida" style="margin-top: 18px;">
        <div style="width:64px; height:64px; border-radius: 50%; background: var(--cies); color:#fff; display:flex; align-items:center; justify-content:center; font-size: 26px; flex-shrink:0;">
            <i class="fas fa-{{ $p->SEX_PAC === 'F' ? 'person-dress' : 'person' }}"></i>
        </div>
        <div style="flex:1;">
            <h1 style="margin-bottom: 2px;">{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</h1>
            <p>
                {{ $p->COD_PAC }} · CI {{ $p->CI_PAC }} · {{ $p->EDAD }} años · {{ $p->SEX_PAC === 'F' ? 'Femenino' : 'Masculino' }} · {{ $p->ESTCIV_PAC }}<br>
                <i class="fas fa-phone"></i> {{ $p->TEL_PAC }} · <i class="fas fa-envelope"></i> {{ $p->COR_PAC }} · <i class="fas fa-location-dot"></i> {{ $p->DIR_PAC }}
                @if ($historia)<br><i class="fas fa-folder-open"></i> Historia clínica {{ $historia->NRO_HIS }} (abierta el {{ $f($historia->FECAPE_HIS) }})@endif
            </p>
        </div>
        @if ($alergias->isNotEmpty())
            <div class="alerta error" style="margin:0; max-width: 280px;">
                <strong><i class="fas fa-triangle-exclamation"></i> Alergias</strong><br>
                @foreach ($alergias as $a){{ $a->DES_ALE }} ({{ $a->SEV_ALE }})@if (!$loop->last), @endif @endforeach
            </div>
        @endif
    </div>

    <div class="kpis">
        <div class="kpi"><div class="v">{{ $r['atendidas'] }}</div><div class="t">Visitas atendidas</div><div class="d" style="color:var(--suave);">de {{ $r['citas'] }} citas agendadas</div></div>
        <div class="kpi"><div class="v" style="font-size:18px;">{{ $r['especialidad'] ?? '—' }}</div><div class="t">Especialidad que más visita</div></div>
        <div class="kpi"><div class="v" style="font-size:20px;">{{ $f($r['primera']) }}</div><div class="t">Primera visita</div><div class="d" style="color:var(--suave);">Última: {{ $f($r['ultima']) }}</div></div>
        <div class="kpi"><div class="v" style="font-size:20px;">{{ $r['proxima'] ? $f($r['proxima']->FEC_CIT) . ' ' . substr($r['proxima']->HOR_CIT, 0, 5) : '—' }}</div><div class="t">Próxima cita</div>
            @if ($r['proxima'])<div class="d" style="color:var(--suave);">{{ $r['proxima']->DES_ESP }}</div>@endif</div>
        <div class="kpi"><div class="v">{{ $r['inasistencias'] }} / {{ $r['canceladas'] }}</div><div class="t">Inasistencias / cancelaciones</div></div>
        <div class="kpi"><div class="v" style="font-size:22px;">{{ $bs($r['pagado']) }}</div><div class="t">Total pagado</div></div>
    </div>

    <div class="graficos">
        <div class="card">
            <h2><i class="fas fa-chart-column"></i>Visitas por mes</h2>
            <p class="nota">Últimos 12 meses.</p>
            <div class="lienzo bajo"><canvas id="g-meses"></canvas></div>
        </div>
        <div class="card">
            <h2><i class="fas fa-stethoscope"></i>Especialidades que visitó</h2>
            <p class="nota">Atenciones por especialidad.</p>
            <div class="lienzo bajo"><canvas id="g-esp"></canvas></div>
        </div>
    </div>

    @if ($medicos->isNotEmpty())
        <h2><i class="fas fa-user-doctor"></i>Médicos que lo atendieron</h2>
        <div class="card tabla" style="margin-bottom: 24px;">
            <table>
                <thead><tr><th>Médico</th><th>Especialidad</th><th class="derecha">Visitas</th></tr></thead>
                <tbody>
                    @foreach ($medicos as $m)
                        <tr><td>{{ $m['nombre'] }}</td><td>{{ $m['especialidad'] }}</td><td class="derecha">{{ $m['visitas'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2><i class="fas fa-calendar-check"></i>Citas y consultas ({{ $citas->count() }})</h2>
    <div class="card tabla" style="margin-bottom: 24px; max-height: 520px; overflow-y: auto;">
        <table>
            <thead><tr><th>Fecha</th><th>Especialidad · Médico</th><th>Motivo</th><th>Diagnóstico y plan</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($citas as $c)
                    <tr>
                        <td class="nowrap"><strong>{{ $f($c->FEC_CIT) }}</strong><br><small>{{ substr($c->HOR_CIT, 0, 5) }} · {{ $c->COD_CIT }}</small></td>
                        <td>{{ $c->DES_ESP }}<br><small>{{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }}</small></td>
                        <td>{{ $c->MOT_CIT }}</td>
                        <td>@if ($c->DIA_CNS)<strong>{{ $c->DIA_CNS }}</strong><br><small>{{ $c->PLA_CNS }}</small>@else<small>—</small>@endif</td>
                        <td><span class="badge estado-{{ \Illuminate\Support\Str::slug($c->EST_CIT) }}">{{ $c->EST_CIT }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin citas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="graficos">
        <div class="card">
            <h2><i class="fas fa-prescription"></i>Recetas ({{ $recetas->count() }})</h2>
            @forelse ($recetas as $rc)
                <div style="border-bottom: 1px solid var(--borde); padding: 8px 0; font-size: 14px;">
                    <strong>{{ $rc->COD_REC }}</strong> · {{ $f($rc->FEC_REC) }} · {{ $rc->EST_REC }}
                    <small>· {{ $rc->TIT_EMP }} {{ $rc->APE_EMP }}</small>
                    <ul style="margin: 4px 0 0 18px; font-size: 13px; color: var(--suave);">
                        @foreach ($rc->ITEMS as $it)
                            <li>{{ $it->DES_MED }} — {{ $it->DOS_DRE }} cada {{ $it->FRE_DRE }} h por {{ $it->DUR_DRE }} días</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="vacio">Sin recetas.</p>
            @endforelse
        </div>
        <div class="card">
            <h2><i class="fas fa-vials"></i>Exámenes ({{ $examenes->count() }})</h2>
            <table>
                <tbody>
                    @forelse ($examenes as $e)
                        <tr>
                            <td>{{ $f($e->FEC_ORD) }}</td>
                            <td>{{ $e->NOM_SRV }}@if ($e->PRI_ORD === 'Urgente') <span class="badge critica">Urgente</span>@endif<br><small>{{ $e->IND_ORD }}</small></td>
                            <td>{{ $e->EST_ORD }}@if ($e->EST_RES)<br><small>Resultado {{ strtolower($e->EST_RES) }}@if ($e->CRI_RES) · <span style="color:#b91c1c;">valor crítico</span>@endif</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td class="vacio">Sin exámenes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2><i class="fas fa-receipt"></i>Pagos ({{ $pagos->count() }})</h2>
    <div class="card tabla" style="margin-bottom: 24px; max-height: 420px; overflow-y: auto;">
        <table>
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Método</th><th class="derecha">Monto</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($pagos as $pg)
                    <tr>
                        <td class="nowrap">{{ $f($pg->FEC_PAG, 'd/m/Y H:i') }}</td>
                        <td>{{ $pg->NOM_SRV }} <small>({{ $pg->COD_CIT }})</small></td>
                        <td>{{ $pg->TIP_PAG === 'Adelanto' ? 'Anticipo' : 'Saldo' }}</td>
                        <td>{{ $pg->DES_MET }}</td>
                        <td class="derecha">{{ $bs($pg->MON_PAG) }}</td>
                        <td>{{ $pg->EST_PAG }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio">Sin pagos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($internaciones->isNotEmpty() || $insumos->isNotEmpty())
        <div class="graficos">
            <div class="card">
                <h2><i class="fas fa-bed"></i>Internaciones ({{ $internaciones->count() }})</h2>
                <table>
                    <tbody>
                        @forelse ($internaciones as $i)
                            <tr>
                                <td class="nowrap">{{ $f($i->FECING_INT) }} → {{ $i->FECALT_INT ? $f($i->FECALT_INT) : 'internado' }}</td>
                                <td>{{ $i->MOT_INT }}@if (!empty($i->AIS_INT))<br><span class="badge critica">Aislamiento: {{ $i->AIS_INT }}</span>@endif
                                    <br><small>{{ $i->NOM_SAL }}, cama {{ $i->NRO_CAM }} · {{ $i->TIT_EMP }} {{ $i->APE_EMP }}</small></td>
                            </tr>
                        @empty
                            <tr><td class="vacio">Sin internaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h2><i class="fas fa-hand-holding-medical"></i>Insumos entregados por familiares ({{ $insumos->count() }})</h2>
                <table>
                    <tbody>
                        @forelse ($insumos as $in)
                            <tr><td>{{ $f($in->FEC_INS) }}</td><td>{{ $in->DES_MED }} × {{ $in->CAN_INS }}<br><small>Entregó {{ $in->NOM_ENT }} {{ $in->APE_ENT }}</small></td><td>{{ $in->EST_INS }}</td></tr>
                        @empty
                            <tr><td class="vacio">Sin insumos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    CIES.barras('g-meses', @json(array_keys($porMes)), { 'Visitas': @json(array_values($porMes)) }, { colores: '#4c7d5f' });
    CIES.dona('g-esp', @json($porEspecialidad->keys()), @json($porEspecialidad->values()));
</script>
@endpush
