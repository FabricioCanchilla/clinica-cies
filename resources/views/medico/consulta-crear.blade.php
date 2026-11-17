@extends('medico.layout')
@section('titulo','Registrar consulta')
@section('contenido')
<h1>Consulta · {{ $p->NOM_PAC }} {{ $p->APE_PAC }}</h1><p class="muted">{{ $c->FEC_CIT }} {{ substr($c->HOR_CIT,0,5) }} · {{ $c->MOT_CIT }}</p><a href="{{ route('medico.historias.ver',$p->COD_PAC) }}">Abrir historia completa →</a>
<section class="card"><h2>Alergias registradas</h2>@forelse($alergias as $a)<p class="danger">{{ $a->DES_ALE }} · {{ $a->SEV_ALE }}</p>@empty<p class="muted">Sin alergias registradas; confirmar con el paciente.</p>@endforelse</section>
<form class="card" method="post" action="{{ route('medico.consultas.guardar',$c->COD_CIT) }}">@csrf<h2>Diagnóstico y plan</h2><p class="muted">Inicia la consulta desde la agenda. Al guardar, la cita pasa a Atendida. Las correcciones posteriores se hacen con adendas.</p><label for="cie10">Diagnóstico CIE-10</label><input id="cie10" name="cie10" list="catalogo" required maxlength="8" value="{{ old('cie10') }}" placeholder="Buscar código o descripción"><datalist id="catalogo">@foreach($diagnosticos as $d)<option value="{{ $d->CODIGO }}">{{ $d->DESCRIPCION }}</option>@endforeach</datalist><p class="muted">Catálogo institucional disponible: {{ $totalDiagnosticos }} códigos. Fuente: DEIS Chile, agosto 2026. Ampliable mediante importación institucional.</p><label for="diagnostico">Diagnóstico clínico</label><textarea id="diagnostico" name="diagnostico" required maxlength="200">{{ old('diagnostico') }}</textarea><label for="plan">Plan e indicaciones</label><textarea id="plan" name="plan" required maxlength="255">{{ old('plan') }}</textarea><button>Finalizar y guardar consulta</button></form>
@endsection
@push('scripts')
<script>
const campo=document.getElementById('cie10'),lista=document.getElementById('catalogo');let temporizador,peticion;
campo.addEventListener('input',()=>{clearTimeout(temporizador);temporizador=setTimeout(async()=>{if(peticion)peticion.abort();peticion=new AbortController();try{const respuesta=await fetch(@json(route('medico.cie10'))+'?buscar='+encodeURIComponent(campo.value),{signal:peticion.signal,headers:{Accept:'application/json'}});if(!respuesta.ok)return;const datos=await respuesta.json();lista.replaceChildren(...datos.map(d=>{const o=document.createElement('option');o.value=d.CODIGO;o.label=d.DESCRIPCION;return o;}));}catch(e){if(e.name!=='AbortError')console.error('No se pudo consultar el catálogo');}},250);});
</script>
@endpush
