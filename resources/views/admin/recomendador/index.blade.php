@extends('layouts.admin')

@section('titulo', 'Planificación de camas')

@push('head')
<style>
    .pc-reco { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 26px; }
    .pc-reco .card { border-top: 4px solid var(--cies); }
    .pc-reco .card.naranja { border-top-color: var(--naranja); }
    .pc-reco .num { font-size: 30px; }
    .pc-reco .lbl strong { color: var(--titulo); }
    .pc-bloque { margin-bottom: 24px; scroll-margin-top: 80px; }
    .pc-texto { font-size: 14px; color: var(--texto); line-height: 1.6; margin-bottom: 14px; }
    .pc-nota { font-size: 13px; color: var(--suave); line-height: 1.55; margin-top: 10px; }
    .pc-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .pc-tabla td, .pc-tabla th { padding: 10px; }
    .pc-tabla td.c, .pc-tabla th.c { text-align: center; }
    .pc-tabla tr.sugerida td { background: var(--cies-bg); font-weight: 600; }
    .pc-pila { display: flex; height: 18px; border-radius: 6px; overflow: hidden; min-width: 160px; }
    .pc-pila span { display: block; height: 100%; }
    .pc-baja { background: #93c5fd; } .pc-media { background: #fcd34d; } .pc-alta { background: #f87171; }
    .pc-leyenda { display: flex; gap: 14px; font-size: 12px; color: var(--suave); margin-top: 8px; flex-wrap: wrap; }
    .pc-leyenda i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }
    .pc-historial { display: flex; align-items: flex-end; gap: 4px; height: 170px; padding: 8px 4px 0; border-bottom: 1px solid var(--borde); position: relative; }
    .pc-historial .col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
    .pc-historial .col div { border-radius: 4px 4px 0 0; min-height: 2px; }
    .pc-historial .col.actual div { outline: 2px dashed var(--titulo); outline-offset: 1px; }
    .pc-historial .linea { position: absolute; left: 0; right: 0; border-top: 1px dashed #94a3b8; font-size: 10px; color: var(--suave); }
    .pc-meses { display: flex; gap: 4px; padding: 4px 4px 0; }
    .pc-meses span { flex: 1; font-size: 9px; color: var(--suave); text-align: center; overflow: hidden; white-space: nowrap; }
    .pc-vista { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .pc-vista div { border: 1px solid var(--borde); border-radius: 12px; padding: 14px; font-size: 13px; color: var(--suave); }
    .pc-vista strong { display: block; font-size: 18px; color: var(--titulo); margin-top: 4px; }
    .pc-barras .fila { display: grid; grid-template-columns: 90px 1fr 60px; gap: 10px; align-items: center; margin-bottom: 8px; font-size: 13px; }
    .pc-barras .fondo { background: var(--bg); border-radius: 6px; height: 16px; overflow: hidden; }
    .pc-barras .barra { height: 100%; background: #9aa5b1; }
    .pc-barras .barra.top { background: var(--cies); }
    details.pc-ajustes summary { cursor: pointer; font-weight: 700; color: var(--titulo); font-size: 15px; list-style: none; }
    details.pc-ajustes summary::-webkit-details-marker { display: none; }
    details.pc-ajustes[open] summary { margin-bottom: 16px; }
    .pc-param { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0 14px; }
    .pc-param label, .pc-matriz label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .pc-param input, .pc-param select { width: 100%; padding: 9px 10px; margin-bottom: 14px; border: 1px solid var(--borde-input); border-radius: 8px; font-size: 14px; background: var(--bg); }
    .pc-matriz input { width: 100%; padding: 7px 8px; border: 1px solid var(--borde-input); border-radius: 6px; font-size: 13px; text-align: right; }
    .pc-tres { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 12px; }
    @media (max-width: 900px) { .pc-dos, .pc-tres { grid-template-columns: 1fr; } }
</style>
@endpush

@section('contenido')
@php
    $bs = fn ($x) => 'Bs. ' . number_format((float) $x, 0, '.', ',');
    $pct = fn ($x) => number_format(100 * (float) $x, 0) . ' %';
    $caps = $param['capacidades'];
    $camas = fn ($k) => $caps[$k] . ' camas';
    $nivel = ['Baja', 'Media', 'Alta'];
    $claseNivel = ['pc-baja', 'pc-media', 'pc-alta'];
    $largo = $r['ve']['π (largo plazo)'];
    $sug = $r['recLargo'];
    $corto = $r['recCorto'];
    $actual = $caps[0];
    $mesesPico = $r['e0'] === 2 ? $r['retorno'][2] : $r['primerPaso'][$r['e0']][2];
    $orden = $r['desviacion'];
    asort($orden);
    $rango = array_flip(array_keys($orden));
    $estabilidad = fn ($k) => ['Más estable', 'Intermedia', 'Más variable'][$rango[$k]] ?? '—';
    $lista = fn ($ks) => implode(' o ', array_map(fn ($k) => $caps[$k], $ks)) . ' camas';
    $ganaAnual = $r['simulacion']['gana'];
    $mesesHist = $r['historial']['meses'];
    $verHist = array_slice($mesesHist, -36);
@endphp

<h1>Planificación de camas</h1>
<p class="sub">Sugerencia de cuántas camas mantener habilitadas en internación, según la ocupación registrada mes a mes.</p>

@foreach ($r['avisos'] as $aviso)
    <div class="alerta info"><i class="fas fa-circle-info"></i> {{ $aviso }}</div>
@endforeach

{{-- Resumen --}}
<div class="pc-reco">
    <div class="card">
        <div class="lbl">Capacidad sugerida</div>
        <div class="num">{{ $camas($sug) }}</div>
        <div class="lbl">
            @if ($caps[$sug] > $actual) <strong>{{ $caps[$sug] - $actual }} más</strong> que las {{ $actual }} actuales.
            @elseif ($caps[$sug] < $actual) <strong>{{ $actual - $caps[$sug] }} menos</strong> que las {{ $actual }} actuales.
            @else Mantener la capacidad actual. @endif
            Es la opción con mejor resultado esperado a largo plazo.
        </div>
    </div>
    <div class="card naranja">
        <div class="lbl">Para los próximos {{ $r['n'] }} {{ $r['n'] === 1 ? 'mes' : 'meses' }}</div>
        <div class="num" style="color: var(--naranja);">{{ $camas($corto) }}</div>
        <div class="lbl">
            @if ($corto !== $sug)
                Con la demanda actual conviene habilitar <strong>temporalmente</strong> {{ $camas($corto) }} y volver a {{ $caps[$sug] }} cuando baje.
            @else
                Coincide con la capacidad sugerida.
            @endif
        </div>
    </div>
    <div class="card">
        <div class="lbl">Ocupación de este mes</div>
        <div class="num">{{ $r['historial']['actual'] ? number_format($r['historial']['actual']['ocupacion'], 0) . ' %' : '—' }}</div>
        <div class="lbl">Demanda <strong>{{ strtolower($nivel[$r['e0']]) }}</strong>{{ $param['inicio'] !== 'auto' ? ' (indicada en los ajustes)' : '' }}</div>
    </div>
    <div class="card">
        <div class="lbl">Próximo mes de demanda alta</div>
        <div class="num">≈ {{ max(1, (int) round($mesesPico)) }} {{ round($mesesPico) == 1 ? 'mes' : 'meses' }}</div>
        <div class="lbl">{{ $r['e0'] === 2 ? 'La demanda ya es alta; suele repetirse con esa frecuencia.' : 'Tiempo esperado para preparar personal e insumos.' }}</div>
    </div>
</div>

{{-- Historial --}}
<div class="card pc-bloque">
    <div class="card-cabecera"><h2><i class="fas fa-chart-column"></i>Ocupación registrada</h2>
        <span class="pc-nota" style="margin: 0;">Últimos {{ count($verHist) }} meses · {{ $param['capRef'] }} camas de referencia</span></div>
    @if (empty($mesesHist))
        <div class="vacio"><i class="fas fa-bed"></i>Todavía no hay internaciones registradas. La sugerencia usa valores de referencia.</div>
    @else
        <div class="pc-historial">
            <div class="linea" style="bottom: {{ $param['u2'] }}%;">&nbsp;alta</div>
            <div class="linea" style="bottom: {{ $param['u1'] }}%;">&nbsp;baja</div>
            @foreach ($verHist as $m)
                <div class="col" title="{{ ucfirst($m['mes']) }}: {{ number_format($m['ocupacion'], 1) }} %">
                    <div class="{{ $claseNivel[$m['estado']] }}" style="height: {{ min(100, $m['ocupacion']) }}%;"></div></div>
            @endforeach
            @if ($r['historial']['actual'])
                @php $m = $r['historial']['actual']; @endphp
                <div class="col actual" title="{{ ucfirst($m['mes']) }} (en curso): {{ number_format($m['ocupacion'], 1) }} %">
                    <div class="{{ $claseNivel[$m['estado']] }}" style="height: {{ min(100, $m['ocupacion']) }}%;"></div></div>
            @endif
        </div>
        <div class="pc-meses">
            @foreach ($verHist as $i => $m)<span>{{ ($i % 3 === 0 || $i === count($verHist) - 1) ? $m['mes'] : '' }}</span>@endforeach
            @if ($r['historial']['actual'])<span><strong>hoy</strong></span>@endif
        </div>
        <div class="pc-leyenda">
            <span><i class="pc-baja"></i>Demanda baja (menos de {{ $param['u1'] + 0 }} %)</span>
            <span><i class="pc-media"></i>Demanda media</span>
            <span><i class="pc-alta"></i>Demanda alta (más de {{ $param['u2'] + 0 }} %)</span>
        </div>
    @endif
</div>

<div class="pc-dos">
    {{-- Qué esperar --}}
    <div class="card pc-bloque">
        <h2><i class="fas fa-calendar-days"></i>¿Qué esperar en los próximos meses?</h2>
        <p class="pc-texto">Probabilidad de cada nivel de demanda según cómo ha ido cambiando la ocupación de un mes al siguiente.</p>
        <table class="pc-tabla">
            <thead><tr><th>Dentro de</th><th class="c">Baja</th><th class="c">Media</th><th class="c">Alta</th><th></th></tr></thead>
            <tbody>
                @foreach ($r['absolutas'] as $h => $a)
                    @continue($h > 12)
                    <tr><td>{{ $h }} {{ $h === 1 ? 'mes' : 'meses' }}</td>
                        @foreach ($a as $v)<td class="c">{{ $pct($v) }}</td>@endforeach
                        <td><div class="pc-pila">@foreach ($a as $j => $v)<span class="{{ $claseNivel[$j] }}" style="width: {{ 100 * $v }}%;"></span>@endforeach</div></td></tr>
                @endforeach
                <tr class="sugerida"><td>A largo plazo</td>
                    @foreach ($r['pi'] as $v)<td class="c">{{ $pct($v) }}</td>@endforeach
                    <td><div class="pc-pila">@foreach ($r['pi'] as $j => $v)<span class="{{ $claseNivel[$j] }}" style="width: {{ 100 * $v }}%;"></span>@endforeach</div></td></tr>
            </tbody>
        </table>
        <p class="pc-nota">En promedio, <strong>{{ max(1, (int) round(10 * $r['pi'][2])) }} de cada 10 meses</strong> son de demanda alta.
            Después de un mes de demanda alta, otro similar se repite cada {{ number_format($r['retorno'][2], 1) }} meses en promedio.</p>
    </div>

    {{-- Comparación de opciones --}}
    <div class="card pc-bloque">
        <h2><i class="fas fa-scale-balanced"></i>Comparación de opciones</h2>
        <p class="pc-texto">Resultado mensual estimado de cada opción (ingresos menos costos), considerando la probabilidad de cada nivel de demanda.</p>
        <table class="pc-tabla">
            <thead><tr><th>Opción</th><th class="c">Resultado esperado</th><th class="c">Mes malo</th><th class="c">Mes bueno</th><th class="c">Estabilidad</th></tr></thead>
            <tbody>
                @foreach ($caps as $k => $c)
                    <tr @class(['sugerida' => $k === $sug])>
                        <td>{{ $camas($k) }}{!! $k === $sug ? ' <span class="badge activo">sugerida</span>' : '' !!}{!! $k === 0 ? ' <small>(actual)</small>' : '' !!}</td>
                        <td class="c">{{ $bs($largo[$k]) }}</td>
                        <td class="c">{{ $bs(min($r['pagos'][$k])) }}</td>
                        <td class="c">{{ $bs(max($r['pagos'][$k])) }}</td>
                        <td class="c">{{ $estabilidad($k) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="pc-dos">
    {{-- Según la postura frente al riesgo --}}
    <div class="card pc-bloque">
        <h2><i class="fas fa-compass"></i>Según cómo se quiera enfrentar el riesgo</h2>
        <div class="pc-vista">
            <div>Si se quiere asegurar el peor mes<strong>{{ $lista($r['criterios']['Wald']['elegidas']) }}</strong></div>
            <div>Si se apuesta al mejor escenario<strong>{{ $lista($r['criterios']['Maximax']['elegidas']) }}</strong></div>
            <div>Si se quiere evitar el mayor arrepentimiento<strong>{{ $lista($r['criterios']['Savage']['elegidas']) }}</strong></div>
            <div>Considerando lo que muestra el historial<strong>{{ $camas($sug) }}</strong></div>
        </div>
        <p class="pc-nota">Sin datos, cada postura sugiere algo distinto. El historial de ocupación permite elegir con el resultado esperado.</p>
    </div>

    {{-- Confiabilidad --}}
    <div class="card pc-bloque">
        <h2><i class="fas fa-shield-halved"></i>¿Qué tan confiable es la sugerencia?</h2>
        <p class="pc-texto">Se generaron {{ $r['simulacion']['escenarios'] }} escenarios posibles para los próximos {{ $r['simulacion']['meses'] }} meses con el comportamiento observado. Porcentaje de escenarios en que cada opción dio el mejor resultado:</p>
        <div class="pc-barras">
            @foreach ($ganaAnual as $k => $v)
                <div class="fila"><span>{{ $camas($k) }}</span>
                    <div class="fondo"><div class="barra {{ $v === max($ganaAnual) ? 'top' : '' }}" style="width: {{ max(1, 100 * $v) }}%;"></div></div>
                    <strong>{{ number_format(100 * $v, 0) }} %</strong></div>
            @endforeach
        </div>
        <p class="pc-nota">
            {{ $r['usaHistorial'] ? 'Calculado con el historial de ocupación del sistema.' : 'Calculado con valores de referencia, porque el historial todavía no tiene suficientes meses de cada nivel de demanda.' }}
            Saber con certeza la demanda de cada mes valdría como máximo {{ $bs($r['informacion']['veip']) }} al mes.
        </p>
    </div>
</div>

{{-- Decisión --}}
<div class="card pc-bloque" id="decision">
    <h2><i class="fas fa-stamp"></i>Registrar la decisión</h2>
    <div class="pc-dos">
        <form method="POST" action="{{ route('admin.recomendador.decidir') }}" class="formulario" style="max-width: none;">
            @csrf
            <label>Capacidad que se habilitará</label>
            <select name="alternativa" id="pc-alt" required>
                @foreach ($caps as $k => $c)
                    <option value="{{ $k }}" data-cap="{{ $c }}" data-ve="{{ round($largo[$k], 2) }}" @selected($k === $sug)>
                        {{ $camas($k) }}{{ $k === $sug ? ' (sugerida)' : '' }}</option>
                @endforeach
            </select>
            <input type="hidden" name="capacidad" id="pc-cap" value="{{ $caps[$sug] }}">
            <input type="hidden" name="valor" id="pc-ve" value="{{ round($largo[$sug], 2) }}">
            <input type="hidden" name="fuente" value="{{ $r['usaHistorial'] ? 'historial' : 'referencia' }}">
            <label>Motivo principal</label>
            <select name="criterio" required>
                @foreach (['Mejor resultado esperado', 'Demanda de los próximos meses', 'Asegurar el peor mes', 'Aprovechar el mejor escenario', 'Evitar el mayor arrepentimiento', 'Otro (ver observación)'] as $c)
                    <option>{{ $c }}</option>
                @endforeach
            </select>
            <label>Observación (opcional)</label>
            <input type="text" name="observacion" maxlength="120" placeholder="Presupuesto, personal disponible, criterio clínico…">
            <button type="submit" class="btn principal"><i class="fas fa-check"></i> Registrar decisión</button>
            <p class="ayuda" style="margin-top: 10px;">Queda en Auditoría con fecha, hora y usuario.</p>
        </form>
        <div class="tabla">
            <table class="pc-tabla">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Decisión</th></tr></thead>
                <tbody>
                    @forelse ($decisiones as $d)
                        <tr><td class="nowrap">{{ \Carbon\Carbon::parse($d->FEC_AUD)->format('d/m/Y H:i') }}</td><td>{{ $d->USR_USR }}</td><td>{{ $d->DET_AUD }}</td></tr>
                    @empty
                        <tr><td colspan="3"><div class="vacio">Aún no hay decisiones registradas.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Ajustes --}}
<form method="GET" action="{{ route('admin.recomendador') }}" class="card pc-bloque">
    <details class="pc-ajustes" @if (request()->query()) open @endif>
        <summary><i class="fas fa-sliders" style="color: var(--cies); margin-right: 6px;"></i>Ajustes del análisis</summary>

        <div class="pc-param">
            <div><label>Camas de referencia para medir la ocupación</label><input type="number" name="capRef" min="1" max="500" value="{{ $param['capRef'] }}"></div>
            <div><label>Demanda baja por debajo de (%)</label><input type="number" step="1" name="u1" value="{{ $param['u1'] + 0 }}"></div>
            <div><label>Demanda alta por encima de (%)</label><input type="number" step="1" name="u2" value="{{ $param['u2'] + 0 }}"></div>
            <div><label>Meses a planificar</label><input type="number" name="n" min="1" max="12" value="{{ $param['n'] }}"></div>
            <div><label>Situación de este mes</label>
                <select name="inicio">
                    <option value="auto" @selected($param['inicio'] === 'auto')>Según la ocupación registrada</option>
                    @foreach ($nivel as $i => $nv)
                        <option value="{{ $i }}" @selected($param['inicio'] === (string) $i)>Demanda {{ strtolower($nv) }}</option>
                    @endforeach
                </select></div>
            <div><label>Datos para el cálculo</label>
                <select name="fuente">
                    <option value="auto" @selected($param['fuente'] === 'auto')>Historial si es suficiente</option>
                    <option value="historial" @selected($param['fuente'] === 'historial')>Solo el historial</option>
                    <option value="referencia" @selected($param['fuente'] === 'referencia')>Valores de referencia</option>
                </select></div>
            <div><label>Meses mínimos de cada nivel</label><input type="number" name="nmin" min="1" max="24" value="{{ $param['nmin'] }}"></div>
            <div><label>Cómo estimar el resultado</label>
                <select name="modo" onchange="document.querySelectorAll('[data-modo]').forEach(el => el.style.display = el.dataset.modo === this.value ? '' : 'none')">
                    <option value="costos" @selected($modo === 'costos')>Con la tarifa y los costos</option>
                    <option value="matriz" @selected($modo === 'matriz')>Ingresar el resultado de cada opción</option>
                </select></div>
        </div>

        <div class="pc-matriz">
            <label>Opciones de capacidad (camas)</label>
            <div class="pc-tres" style="margin-bottom: 14px; max-width: 420px;">
                @foreach ($caps as $c)
                    <div><input type="number" name="cap[]" min="1" max="500" value="{{ $c }}"></div>
                @endforeach
            </div>

            <div data-modo="matriz" @style(['display: none' => $modo !== 'matriz'])>
                <label>Resultado mensual estimado de cada opción (Bs.)</label>
                <table class="pc-tabla" style="max-width: 640px;">
                    <thead><tr><th>Opción</th>@foreach ($nivel as $nv)<th class="c">Demanda {{ strtolower($nv) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($r['pagos'] as $k => $fila)
                            <tr><td>{{ $camas($k) }}</td>
                                @foreach ($fila as $j => $b)
                                    <td><input type="number" step="1" name="b[{{ $k }}][{{ $j }}]" value="{{ $b + 0 }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-modo="costos" @style(['display: none' => $modo !== 'costos'])>
                <div class="pc-tres" style="max-width: 640px;">
                    <div><label>Tarifa por día-cama (Bs.)</label><input type="number" step="1" name="ic" value="{{ $costos['ic'] + 0 }}"></div>
                    <div><label>Costo fijo por cama al mes (Bs.)</label><input type="number" step="1" name="cf" value="{{ $costos['cf'] + 0 }}"></div>
                    <div><label>Costo de atención por día-cama (Bs.)</label><input type="number" step="1" name="cv" value="{{ $costos['cv'] + 0 }}"></div>
                </div>
                <label style="margin-top: 12px;">Camas que se necesitan en promedio (incluye pacientes que no consiguen cama)</label>
                <div class="pc-tres" style="max-width: 640px;">
                    @foreach ($costos['dem'] as $j => $d)
                        <div><small>Demanda {{ strtolower($nivel[$j]) }}</small><input type="number" step="0.1" name="dem[]" value="{{ $d }}"></div>
                    @endforeach
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 16px;">
            <button type="submit" class="btn principal"><i class="fas fa-rotate"></i> Actualizar sugerencia</button>
            <a href="{{ route('admin.recomendador') }}" class="btn">Restablecer</a>
        </div>
        <p class="pc-nota">La tarifa y los costos por defecto son valores de referencia; actualízalos con los datos de la administración de la clínica.</p>
    </details>
</form>
@endsection

@push('scripts')
<script>
    document.getElementById('pc-alt')?.addEventListener('change', function () {
        const op = this.options[this.selectedIndex];
        document.getElementById('pc-cap').value = op.dataset.cap;
        document.getElementById('pc-ve').value = op.dataset.ve;
    });
</script>
@endpush
