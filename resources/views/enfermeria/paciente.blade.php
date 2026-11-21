@extends('layouts.admin')
@section('titulo', 'Atención de enfermería')
@section('contenido')
<a href="{{ route('enfermeria.pacientes') }}">← Pacientes y turno</a>
@php
    $seccion = request('seccion', 'resumen');
    if (!in_array($seccion, ['resumen','signos','dosis','notas','balance','insumos'], true)) { $seccion = 'resumen'; }
@endphp
<h1>{{ $i->NOM_PAC }} {{ $i->APE_PAC }}</h1>
<div class="barra">@foreach(['resumen'=>'Resumen','signos'=>'Signos vitales','dosis'=>'Dosis','notas'=>'Notas','balance'=>'Balance hídrico','insumos'=>'Insumos'] as $clave=>$nombre)<a class="btn {{ $seccion === $clave ? 'principal' : '' }}" href="{{ route('enfermeria.paciente', ['internacion'=>$i->COD_INT, 'seccion'=>$clave]) }}">{{ $nombre }}</a>@endforeach</div>
<p class="sub">CI {{ $i->CI_PAC }} · {{ $i->NOM_SAL }}, cama {{ $i->NRO_CAM }} · Médico: {{ $i->NOM_EMP }} {{ $i->APE_EMP }} · {{ $i->EST_INT }}</p>
<div class="grid"><div class="card"><h2>Alergias</h2>@forelse($alergias as $a)<p>{{ $a->DES_ALE }} ({{ $a->SEV_ALE }})</p>@empty<p>Sin alergias registradas; verificar con el paciente.</p>@endforelse</div><div class="card"><h2>Antecedentes</h2>@forelse($antecedentes as $a)<p>{{ $a->TIP_ANT }}: {{ $a->DES_ANT }}</p>@empty<p>Sin antecedentes registrados.</p>@endforelse</div></div>
@if ($seccion === 'resumen')
<div class="card" style="margin-bottom:20px;overflow-x:auto"><h2>Indicaciones y recetas activas</h2><table><thead><tr><th>Medicamento</th><th>Dosis</th><th>Vía</th><th>Frecuencia</th><th>Duración</th><th>Fecha e indicaciones</th></tr></thead><tbody>@forelse($recetas as $r)<tr><td>{{ $r->DES_MED }}</td><td>{{ $r->DOS_DRE }}</td><td>{{ $r->DES_VIA }}</td><td>Cada {{ $r->FRE_DRE }} h</td><td>{{ $r->DUR_DRE }} días</td><td>{{ $r->FEC_REC }} · {{ $r->OBS_REC }}</td></tr>@empty<tr><td colspan="6">No hay recetas activas.</td></tr>@endforelse</tbody></table></div>
@endif
@if ($seccion === 'signos')
@if($i->EST_INT === 'Internado')
<div class="card" style="margin-bottom:20px"><h2>Registrar signos vitales</h2><form class="barra" method="post" action="{{ route('enfermeria.signos', $i->COD_INT) }}">@csrf <input name="sistolica" type="number" min="1" max="400" placeholder="Sistólica (mmHg)" required><input name="diastolica" type="number" min="1" max="300" placeholder="Diastólica (mmHg)" required><input name="pulso" type="number" min="1" max="300" placeholder="Pulso (lpm)" required><input name="temperatura" type="number" step="0.1" min="30" max="45" placeholder="Temperatura (°C)" required><button class="btn principal">Guardar signos</button></form></div>
@endif
<div class="card" style="margin-bottom:20px;overflow-x:auto"><h2>Signos recientes</h2><table><tr><th>Fecha</th><th>Presión</th><th>Pulso</th><th>Temperatura</th></tr>@forelse($signos as $s)<tr><td>{{ $s->FEC_SIV }}</td><td>{{ $s->SIS_SIV }}/{{ $s->DIA_SIV }} mmHg</td><td>{{ $s->PUL_SIV }} lpm</td><td>{{ $s->TEM_SIV }} °C</td></tr>@empty<tr><td colspan="4">Sin signos registrados.</td></tr>@endforelse</table></div>
@endif
@if ($seccion === 'dosis')
<div class="card" id="dosis" style="margin-bottom:20px;overflow-x:auto"><h2>Dosis de medicamentos</h2>
@foreach($revisiones as $revision)<p>El insumo {{ $revision->COD_INS }} tiene una dosis anterior sin unidades verificadas: <a href="{{ route('enfermeria.paciente', ['internacion'=>$revision->COD_INT, 'seccion'=>'dosis']) }}">revisar {{ $revision->COD_DOS }}</a>.</p>@endforeach
@if($i->EST_INT === 'Internado')
<form class="barra" method="post" action="{{ route('enfermeria.dosis.programar', $i->COD_INT) }}">@csrf <select name="receta" required><option value="">Indicación médica</option>@foreach($recetas as $r)<option value="{{ $r->COD_DRE }}">{{ $r->DES_MED }} · {{ $r->DOS_DRE }} · {{ $r->DES_VIA }}</option>@endforeach</select><select name="insumo" required><option value="">Insumo verificado</option>@foreach($insumos as $x)@if(!$x->TIPO && $x->NRO_CEN)<option value="{{ $x->COD_INS }}">{{ $x->DES_MED }} · {{ $x->DOS_INS }} · lote {{ $x->LOT_INS }} · vence {{ $x->FECVEN_INS }}</option>@endif @endforeach</select><label>Unidades previstas <input type="number" name="unidades" min="0.001" max="32000" step="0.001" required></label><input type="datetime-local" name="fecha" required><button class="btn principal">Programar dosis</button></form><p class="sub">Indica las unidades de la presentación recibida que se reservarán para esta dosis, siguiendo la indicación médica.</p>
@endif
@forelse($dosis as $d)<div style="border-top:1px solid #e2e8f0;padding:16px 0"><strong>{{ $d->FECPRG_DOS }} · {{ $d->DES_MED }}</strong> — {{ $d->DOS_DOS }} · {{ $d->DES_VIA }} · {{ $d->EST_DOS }} @if($d->FECADM_DOS) ({{ $d->FECADM_DOS }}) @endif @if($d->MOT_INC)<p>Motivo de omisión: {{ $d->MOT_INC }}</p>@endif
@if($d->PROGRAMADAS !== null)<p>Unidades previstas: {{ $d->PROGRAMADAS }}</p>@endif @if($d->USADAS !== null)<p>Unidades utilizadas: {{ $d->USADAS }}</p>@endif
@if((($d->EST_DOS === 'Programada' && $d->PROGRAMADAS === null) || ($d->EST_DOS === 'Administrada' && $d->USADAS === null)) && !$d->INSUMO_CERRADO)
<form method="post" class="barra" action="{{ route('enfermeria.dosis.unidades-anteriores', $d->COD_DOS) }}">@csrf <label>{{ $d->EST_DOS === 'Administrada' ? 'Unidades utilizadas según el registro anterior' : 'Unidades previstas para esta dosis anterior' }} <input type="number" name="unidades" min="0.001" max="32000" step="0.001" required></label><label><input type="checkbox" name="confirmacion" value="1" required> Confirmo que revisé el registro original.</label><button class="btn">Registrar revisión</button></form>
@endif
@if($d->INSUMO_CERRADO && $d->EST_DOS === 'Administrada' && $d->USADAS === null)<p>Registro anterior sin unidades verificadas; el insumo ya fue cerrado.</p>@endif
@if($d->EST_DOS === 'Programada' && $i->EST_INT === 'Internado' && $d->PROGRAMADAS !== null)
<form method="post" class="firma-dosis" action="{{ route('enfermeria.dosis.administrar', $d->COD_DOS) }}" style="margin-top:12px">@csrf <p>Confirmar los cinco correctos:</p><div class="barra">@foreach(['paciente'=>'Paciente','medicamento'=>'Medicamento','dosis'=>'Dosis','via'=>'Vía','hora'=>'Horario'] as $key=>$label)<label><input type="checkbox" name="{{ $key }}" value="1" required> {{ $label }}</label>@endforeach</div><label>Unidades realmente utilizadas <input type="number" name="unidades" min="0.001" max="32000" step="0.001" required></label><p>Firma de enfermería:</p><canvas width="350" height="100" style="border:1px solid #cbd5e1;touch-action:none;max-width:100%"></canvas><input type="hidden" name="firma"><div><button type="button" class="btn limpiar-firma">Limpiar firma</button> <button class="btn principal">Registrar administración</button></div></form>
<form method="post" class="barra" action="{{ route('enfermeria.dosis.omitir', $d->COD_DOS) }}" style="margin-top:10px">@csrf <input name="motivo" maxlength="200" placeholder="Motivo de omisión" required><button class="btn">Omitir y avisar al médico</button></form>
@elseif($d->EST_DOS === 'Programada' && $i->EST_INT === 'Internado')
<form method="post" class="barra" action="{{ route('enfermeria.dosis.omitir', $d->COD_DOS) }}">@csrf <input name="motivo" maxlength="200" placeholder="Motivo de omisión" required><button class="btn">Omitir y avisar al médico</button></form>
@endif</div>@empty<p>No hay dosis programadas.</p>@endforelse</div>
@endif
@if ($seccion === 'notas')
<div class="card" style="margin-bottom:20px"><h2>Notas de enfermería y entrega de turno</h2>@if($i->EST_INT === 'Internado')<form method="post" action="{{ route('enfermeria.nota', $i->COD_INT) }}">@csrf <textarea name="texto" maxlength="500" required placeholder="Evolución, cuidados y pendientes del turno" style="width:100%;min-height:80px"></textarea><button class="btn principal">Agregar nota</button></form>@endif @forelse($notas as $n)<p style="margin-top:12px"><strong>{{ $n->FECHA }} · {{ $n->NOM_EMP }} {{ $n->APE_EMP }}</strong><br>{{ $n->TEXTO }}</p>@empty<p>Sin notas registradas.</p>@endforelse</div>
@endif
@if ($seccion === 'balance')
<div class="card" style="margin-bottom:20px"><h2>Balance hídrico</h2>@if($i->EST_INT === 'Internado')<form class="barra" method="post" action="{{ route('enfermeria.balance', $i->COD_INT) }}">@csrf <input name="ingreso" type="number" min="0" step="0.01" placeholder="Ingreso (ml)" required><input name="egreso" type="number" min="0" step="0.01" placeholder="Egreso (ml)" required><input name="observacion" maxlength="250" placeholder="Observación"><button class="btn principal">Registrar balance</button></form>@endif<table><tr><th>Fecha</th><th>Ingreso</th><th>Egreso</th><th>Balance</th><th>Observación</th></tr>@forelse($balances as $b)<tr><td>{{ $b->FECHA }}</td><td>{{ $b->INGRESO }} ml</td><td>{{ $b->EGRESO }} ml</td><td>{{ $b->INGRESO - $b->EGRESO }} ml</td><td>{{ $b->OBSERVACION }}</td></tr>@empty<tr><td colspan="5">Sin balances registrados.</td></tr>@endforelse</table></div>
@endif
@if ($seccion === 'insumos')
<div class="card" style="overflow-x:auto"><h2>Insumos de familiares</h2>@forelse($insumos as $x)<div style="border-top:1px solid #e2e8f0;padding:12px 0"><strong>{{ $x->DES_MED }}</strong> · {{ $x->DOS_INS }} · {{ $x->CAN_INS }} unidades · lote {{ $x->LOT_INS }} · vence {{ $x->FECVEN_INS }} @if(!$x->TIPO) · @if($x->PENDIENTE_REVISION)<strong>Saldo pendiente de revisión de dosis anteriores</strong>@else Utilizadas: {{ $x->UTILIZADO }} · Reservadas: {{ $x->RESERVADO }} · Disponibles: {{ $x->DISPONIBLE }} @endif @endif @if(!$x->NRO_CEN)<strong> · Comprobante pendiente; no disponible para dosis.</strong>@endif @if($x->TIPO) — {{ $x->TIPO }}: {{ $x->CERRADO }} unidades @else <form method="post" class="barra" action="{{ route('enfermeria.insumos.cierre', $x->COD_INS) }}" style="margin-top:10px">@csrf <select name="tipo" required><option value="Devolución">Devolución</option><option value="Descarte">Descarte</option></select><input name="detalle" maxlength="250" required placeholder="Detalle y responsable"><button class="btn">Registrar destino del sobrante</button></form>@endif</div>@empty<p>Sin insumos verificados.</p>@endforelse</div>
@endif
<script>
document.querySelectorAll('.firma-dosis').forEach(form => {
    const canvas = form.querySelector('canvas'), ctx = canvas.getContext('2d'), input = form.querySelector('[name=firma]');
    let dibujando = false, firmado = false;
    const point = e => { const r = canvas.getBoundingClientRect(); return [(e.clientX-r.left)*canvas.width/r.width,(e.clientY-r.top)*canvas.height/r.height]; };
    canvas.addEventListener('pointerdown', e => { dibujando = true; canvas.setPointerCapture(e.pointerId); const [x,y] = point(e); ctx.beginPath(); ctx.moveTo(x,y); });
    canvas.addEventListener('pointermove', e => { if (!dibujando) return; const [x,y] = point(e); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineTo(x,y); ctx.stroke(); firmado = true; });
    canvas.addEventListener('pointerup', () => dibujando = false);
    form.querySelector('.limpiar-firma').addEventListener('click', () => { ctx.clearRect(0,0,canvas.width,canvas.height); firmado = false; });
    form.addEventListener('submit', e => { if (!firmado) { e.preventDefault(); alert('Dibuje su firma antes de registrar la administración.'); } else input.value = canvas.toDataURL('image/png'); });
});
</script>
@endsection
