@extends('layouts.admin')

@section('titulo', 'Internación ' . $int->COD_INT)

@push('head')
<style>
    .enf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
    .enf-grid .card { margin-bottom: 20px; }
    .compacto { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .compacto input, .compacto select, .form-linea input, .form-linea select, .form-linea textarea {
        width: 100%; padding: 8px 10px; border: 1px solid var(--borde-input); border-radius: 8px; font: inherit; font-size: 13px;
    }
    .form-linea { display: grid; gap: 8px; margin-top: 10px; }
    .dosis-fila td { vertical-align: top; }
    .cinco { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 6px; }
    .cinco input, .cinco select { padding: 6px 8px; border: 1px solid var(--borde-input); border-radius: 6px; font: inherit; font-size: 12px; width: 100%; }
    .check { font-size: 12px; margin-right: 8px; }
    .check.ok { color: #166534; } .check.no { color: #b91c1c; font-weight: 700; }
    .saldo { font-size: 26px; font-weight: 800; }
    .ale { color: #b91c1c; font-weight: 700; }
    @media (max-width: 1000px) { .enf-grid { grid-template-columns: 1fr; } .compacto, .cinco { grid-template-columns: 1fr 1fr; } }
</style>
@endpush

@section('contenido')
    <div class="card-cabecera">
        <div>
            <h1>{{ $int->NOM_PAC }} {{ $int->APE_PAC }} · cama {{ $int->NRO_CAM }}</h1>
            <p class="sub" style="margin-bottom: 0;">
                CI {{ $int->CI_PAC }} · {{ \Carbon\Carbon::parse($int->FECNAC_PAC)->age }} años · {{ $int->NOM_SAL }} ·
                {{ $int->TIT_EMP }} {{ $int->NOM_EMP }} {{ $int->APE_EMP }} · desde {{ \Carbon\Carbon::parse($int->FECING_INT)->format('d/m/Y H:i') }}
                · <strong>{{ $int->EST_INT }}</strong>
            </p>
            <p style="margin-top: 4px;">{{ $int->MOT_INT }} @if (!empty($int->AIS_INT))<span class="badge alta">Aislamiento {{ $int->AIS_INT }}</span>@endif</p>
        </div>
        <a class="btn" href="{{ route('enfermeria.internados') }}"><i class="fas fa-arrow-left"></i> Internados</a>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <h2><i class="fas fa-clipboard-list"></i> Resumen clínico (RF030)</h2>
        <div class="fila" style="gap: 16px;">
            <div>
                <strong style="color: #b91c1c;"><i class="fas fa-allergies"></i> Alergias:</strong>
                @forelse ($alergias as $a)<span class="ale">{{ $a->DES_ALE }}</span> <small>({{ $a->SEV_ALE }})</small>@if (!$loop->last), @endif
                @empty <small>ninguna registrada</small>@endforelse
                <div style="margin-top: 8px;"><strong>Antecedentes:</strong>
                    @forelse ($antecedentes as $a){{ $a->DES_ANT }}@if (!$loop->last); @endif
                    @empty <small>ninguno</small>@endforelse
                </div>
            </div>
            <div>
                <strong>Indicaciones activas:</strong>
                @forelse ($indicaciones as $i)
                    <div style="font-size: 13px;">• {{ $i->DES_MED }} {{ $i->DOS_DRE }} · {{ $i->DES_VIA }} · cada {{ $i->FRE_DRE }} h · {{ $i->DUR_DRE }} días <small>({{ $i->COD_REC }})</small></div>
                @empty
                    <small>Sin recetas activas.</small>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card" id="dosis" style="margin-bottom: 20px;">
        <div class="card-cabecera">
            <h2><i class="fas fa-syringe"></i> Dosis · 5 correctos (RF033, RF034)</h2>
            @if ($indicaciones->isNotEmpty() && $int->EST_INT === 'Internado')
                <form method="POST" action="{{ route('enfermeria.internaciones.programar', $int->COD_INT) }}" class="acciones">
                    @csrf
                    <select name="indicacion" required style="padding: 7px; border: 1px solid var(--borde-input); border-radius: 8px;">
                        @foreach ($indicaciones as $i)<option value="{{ $i->COD_DRE }}">{{ $i->DES_MED }} {{ $i->DOS_DRE }} c/{{ $i->FRE_DRE }} h</option>@endforeach
                    </select>
                    <input type="datetime-local" name="inicio" required value="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                           style="padding: 6px; border: 1px solid var(--borde-input); border-radius: 8px;">
                    <button class="btn sm principal"><i class="fas fa-calendar-plus"></i> Programar dosis</button>
                </form>
            @endif
        </div>
        <p class="ayuda" style="margin-top: 0;">Escanea la pulsera (código del paciente o CI) y el envase (código del medicamento o número de lote). La hora es correcta dentro de ±{{ $ventana }} min de la programada (RN37).</p>
        @if ($dosis->isEmpty())
            <div class="vacio"><i class="fas fa-pills"></i> Sin dosis programadas en las próximas 24 h.</div>
        @else
            <div class="tabla">
                <table>
                    <thead><tr><th>Programada</th><th>Medicamento</th><th>Estado</th><th style="min-width: 420px;">Acción</th></tr></thead>
                    <tbody>
                    @foreach ($dosis as $d)
                        @php $atrasada = $d->EST_DOS === 'Programada' && \Carbon\Carbon::parse($d->FECPRG_DOS)->lt(now()->subMinutes($ventana)); @endphp
                        <tr class="dosis-fila">
                            <td class="nowrap"><strong>{{ \Carbon\Carbon::parse($d->FECPRG_DOS)->format('H:i') }}</strong><br><small>{{ \Carbon\Carbon::parse($d->FECPRG_DOS)->format('d/m') }}</small></td>
                            <td>{{ $d->DES_MED }}<br><small>{{ $d->DOS_DOS }} · {{ $d->DES_VIA }}{{ $d->COD_INS ? ' · insumo ' . $d->COD_INS : '' }}</small></td>
                            <td>
                                <span class="badge {{ $d->EST_DOS === 'Administrada' ? 'activo' : ($d->EST_DOS === 'Omitida' ? 'bloqueado' : ($atrasada ? 'critica' : 'media')) }}">{{ $atrasada ? 'Atrasada' : $d->EST_DOS }}</span>
                                @if ($d->EST_DOS === 'Administrada')<br><small>{{ \Carbon\Carbon::parse($d->FECADM_DOS)->format('H:i') }} · {{ $d->NOM_EMP }} {{ $d->APE_EMP }}</small>@endif
                                @if ($d->MOT_INC)<br><small>{{ $d->MOT_INC }}</small>@endif
                            </td>
                            <td>
                                @if ($d->EST_DOS === 'Programada')
                                    @if (session('checks_' . $d->COD_DOS))
                                        <div>@foreach (session('checks_' . $d->COD_DOS) as $k => $ok)<span class="check {{ $ok ? 'ok' : 'no' }}"><i class="fas fa-{{ $ok ? 'check' : 'xmark' }}"></i> {{ $k }}</span>@endforeach</div>
                                    @endif
                                    <form method="POST" action="{{ route('enfermeria.dosis.administrar', $d->COD_DOS) }}">
                                        @csrf
                                        <div class="cinco">
                                            <input name="paciente" required placeholder="Pulsera" autocomplete="off">
                                            <input name="medicamento" required placeholder="Medicamento/lote" autocomplete="off">
                                            <input name="dosis" required placeholder="Dosis" value="">
                                            <select name="via" required>
                                                <option value="">Vía…</option>
                                                @foreach ($vias as $v)<option value="{{ $v->COD_VIA }}">{{ $v->DES_VIA }}</option>@endforeach
                                            </select>
                                        </div>
                                        <button class="btn sm principal" style="margin-top: 6px;"><i class="fas fa-check-double"></i> Verificar y administrar</button>
                                    </form>
                                    <details>
                                        <summary style="font-size: 12px; cursor: pointer; color: #b91c1c; margin-top: 6px;">Omitir dosis</summary>
                                        <form method="POST" action="{{ route('enfermeria.dosis.omitir', $d->COD_DOS) }}" class="form-linea">
                                            @csrf
                                            <input name="motivo" minlength="5" maxlength="200" required placeholder="Motivo (paciente en ayunas, rechazo, vómito…)">
                                            <button class="btn sm peligro" style="justify-self: start;">Registrar omisión y avisar al médico</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="enf-grid">
        <div>
            <div class="card">
                <h2><i class="fas fa-heart-pulse"></i> Signos vitales</h2>
                @if ($int->EST_INT === 'Internado')
                    <form method="POST" action="{{ route('enfermeria.internaciones.signos', $int->COD_INT) }}">
                        @csrf
                        <div class="compacto">
                            <input type="number" name="sistolica" placeholder="Sistólica" min="40" max="300" required value="{{ old('sistolica') }}">
                            <input type="number" name="diastolica" placeholder="Diastólica" min="20" max="200" required value="{{ old('diastolica') }}">
                            <input type="number" name="pulso" placeholder="Pulso" min="1" max="250" required value="{{ old('pulso') }}">
                            <input type="number" name="temperatura" placeholder="T° °C" step="0.1" min="30" max="45" required value="{{ old('temperatura') }}">
                        </div>
                        <button class="btn sm principal" style="margin-top: 8px;"><i class="fas fa-save"></i> Registrar</button>
                    </form>
                @endif
                <div class="tabla" style="margin-top: 12px;">
                    <table>
                        <thead><tr><th>Hora</th><th>PA</th><th>Pulso</th><th>T°</th><th>Registró</th></tr></thead>
                        <tbody>
                        @forelse ($signos as $s)
                            <tr>
                                <td class="nowrap"><small>{{ \Carbon\Carbon::parse($s->FEC_SIV)->format('d/m H:i') }}</small></td>
                                <td>{{ $s->SIS_SIV }}/{{ $s->DIA_SIV }}</td><td>{{ $s->PUL_SIV }}</td><td>{{ $s->TEM_SIV }}</td>
                                <td><small>{{ $s->NOM_EMP }} {{ $s->APE_EMP }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="vacio">Sin registros.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-droplet"></i> Balance hídrico (24 h)</h2>
                <div class="grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 12px;">
                    <div><div class="lbl">Ingresos</div><div class="saldo" style="color: var(--cies);">{{ $balance['ingresos'] }} <small>mL</small></div></div>
                    <div><div class="lbl">Egresos</div><div class="saldo" style="color: var(--naranja);">{{ $balance['egresos'] }} <small>mL</small></div></div>
                    <div><div class="lbl">Saldo</div><div class="saldo">{{ $balance['saldo'] > 0 ? '+' : '' }}{{ $balance['saldo'] }} <small>mL</small></div></div>
                </div>
                @if ($int->EST_INT === 'Internado')
                    <form method="POST" action="{{ route('enfermeria.internaciones.balance', $int->COD_INT) }}" class="compacto" id="form-balance">
                        @csrf
                        <select name="tipo" id="bal-tipo"><option>Ingreso</option><option>Egreso</option></select>
                        <select name="concepto" id="bal-concepto"></select>
                        <input type="number" name="volumen" min="1" max="5000" required placeholder="mL">
                        <button class="btn sm principal">Registrar</button>
                    </form>
                @endif
                <div class="tabla" style="margin-top: 12px;">
                    <table>
                        <tbody>
                        @foreach ($balance['filas']->take(12) as $b)
                            <tr>
                                <td class="nowrap"><small>{{ \Carbon\Carbon::parse($b->FEC_BAL)->format('d/m H:i') }}</small></td>
                                <td>{{ $b->TIP_BAL }}</td><td>{{ $b->CON_BAL }}</td><td class="derecha">{{ $b->VOL_BAL }} mL</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h2><i class="fas fa-people-arrows"></i> Cambio de turno (turno {{ $turno }})</h2>
                @if ($int->EST_INT === 'Internado')
                    <form method="POST" action="{{ route('enfermeria.internaciones.nota', $int->COD_INT) }}" class="form-linea">
                        @csrf
                        <input type="hidden" name="tipo" value="Cambio de turno">
                        <textarea name="texto" rows="7" maxlength="1000" required>{{ $borrador }}</textarea>
                        <button class="btn sm acento" style="justify-self: start;"><i class="fas fa-people-arrows"></i> Entregar turno</button>
                    </form>
                @endif
            </div>

            <div class="card">
                <h2><i class="fas fa-pen-to-square"></i> Notas de enfermería</h2>
                @if ($int->EST_INT === 'Internado')
                    <form method="POST" action="{{ route('enfermeria.internaciones.nota', $int->COD_INT) }}" class="form-linea">
                        @csrf
                        <input type="hidden" name="tipo" value="Nota">
                        <textarea name="texto" rows="3" minlength="5" maxlength="1000" required placeholder="Evolución, cuidados realizados, observaciones"></textarea>
                        <button class="btn sm principal" style="justify-self: start;">Guardar nota</button>
                    </form>
                @endif
                @forelse ($notas as $n)
                    <div style="border-bottom: 1px solid var(--borde); padding: 10px 0; font-size: 13px;">
                        <small>{{ \Carbon\Carbon::parse($n->FEC_NEN)->format('d/m/Y H:i') }} · turno {{ $n->TUR_NEN }} · {{ $n->NOM_EMP }} {{ $n->APE_EMP }}</small>
                        @if ($n->TIP_NEN === 'Cambio de turno')<span class="badge alta">Cambio de turno</span>@endif
                        <div style="white-space: pre-line;">{{ $n->TXT_NEN }}</div>
                    </div>
                @empty
                    <div class="vacio">Sin notas.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const conceptos = { Ingreso: @json($ingresos), Egreso: @json($egresos) };
    const tipo = document.getElementById('bal-tipo'), con = document.getElementById('bal-concepto');
    if (!tipo) return;
    const llenar = () => { con.innerHTML = conceptos[tipo.value].map(c => `<option>${c}</option>`).join(''); };
    tipo.addEventListener('change', llenar); llenar();
})();
</script>
@endpush
