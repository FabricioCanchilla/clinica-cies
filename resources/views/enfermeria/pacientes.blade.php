@extends('layouts.admin')
@section('titulo', 'Pacientes y turno')
@section('contenido')
@php
    $seccion = request('seccion', 'pacientes');
    if (!in_array($seccion, ['pacientes','dosis','camas'], true)) { $seccion = 'pacientes'; }
@endphp
<h1>Pacientes y turno</h1>
<div class="barra">@foreach(['pacientes'=>'Pacientes','dosis'=>'Dosis pendientes','camas'=>'Camas'] as $clave=>$nombre)<a class="btn {{ $seccion === $clave ? 'principal' : '' }}" href="{{ route('enfermeria.pacientes', ['seccion'=>$clave]) }}">{{ $nombre }}</a>@endforeach</div>
<p class="sub">Internaciones, camas y dosis pendientes para el seguimiento de enfermería.</p>
<div class="grid">
    <div class="card"><div class="num">{{ $pacientes->count() }}</div><div class="lbl">Pacientes internados</div></div>
    <div class="card"><div class="num">{{ $pendientes->count() }}</div><div class="lbl">Dosis pendientes</div></div>
    <div class="card"><div class="num">{{ $pendientes->filter(fn ($d) => \Carbon\Carbon::parse($d->FECPRG_DOS)->isPast())->count() }}</div><div class="lbl">Dosis con horario vencido</div></div>
</div>
@if ($seccion === 'pacientes')
<div class="card" style="overflow-x:auto;margin-bottom:20px"><h2>Pacientes internados</h2><table><thead><tr><th>Paciente</th><th>Sala y cama</th><th>Ingreso</th><th>Última nota de turno</th><th></th></tr></thead><tbody>
@forelse($pacientes as $p)<tr><td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}<br>CI {{ $p->CI_PAC }}</td><td>{{ $p->NOM_SAL }} · {{ $p->NRO_CAM }} ({{ $p->EST_CAM }})</td><td>{{ $p->FECING_INT }}</td><td>{{ $p->ULTIMA_NOTA ?? 'Sin notas' }}</td><td><a class="btn principal" href="{{ route('enfermeria.paciente', $p->COD_INT) }}">Ver atención</a></td></tr>@empty<tr><td colspan="5">No hay pacientes internados.</td></tr>@endforelse
</tbody></table></div>
@endif
@if ($seccion === 'dosis')
<div class="card" style="overflow-x:auto;margin-bottom:20px"><h2>Dosis pendientes y entrega de turno</h2><table><thead><tr><th>Horario</th><th>Paciente</th><th>Medicamento</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($pendientes as $d)<tr><td>{{ $d->FECPRG_DOS }}</td><td>{{ $d->NOM_PAC }} {{ $d->APE_PAC }}</td><td>{{ $d->DES_MED }} · {{ $d->DOS_DOS }}</td><td>{{ \Carbon\Carbon::parse($d->FECPRG_DOS)->isPast() ? 'Horario vencido' : 'Programada' }}</td><td><a class="btn" href="{{ route('enfermeria.paciente', ['internacion'=>$d->COD_INT, 'seccion'=>'dosis']) }}">Ver dosis</a></td></tr>@empty<tr><td colspan="5">No hay dosis pendientes.</td></tr>@endforelse
</tbody></table></div>
@endif
@if ($seccion === 'camas')
<div class="card" style="overflow-x:auto"><h2>Estado de camas</h2><table><thead><tr><th>Sala</th><th>Cama</th><th>Estado</th></tr></thead><tbody>@forelse($camas as $c)<tr><td>{{ $c->NOM_SAL }}</td><td>{{ $c->NRO_CAM }}</td><td>{{ $c->EST_CAM }}</td></tr>@empty<tr><td colspan="3">No hay camas registradas.</td></tr>@endforelse</tbody></table></div>
@endif
@endsection
