@extends('medico.layout')
@section('titulo',ucfirst($seccion))
@section('contenido')
<h1>{{ $seccion==='notas'?'Notas de evolución':ucfirst($seccion) }}</h1><p class="muted">Registros emitidos por ti. Abre una historia o consulta para registrar nuevos.</p><div class="card scroll"><table><thead><tr><th>Fecha</th><th>Paciente</th><th>Registro</th><th>Acciones</th></tr></thead><tbody>@forelse($filas as $f)<tr><td>{{ $f->fecha }}</td><td>{{ $f->NOM_PAC }} {{ $f->APE_PAC }}</td><td>@if($seccion==='notas')<span class="texto">{{ $f->texto }}</span>@else{{ $f->codigo }} <span class="badge">{{ $f->estado }}</span>@endif</td><td><a href="{{ $seccion==='notas'?route('medico.historias.ver',$f->COD_PAC):route('medico.consultas.ver',$f->COD_CNS) }}">Abrir →</a>@if($seccion==='recetas')<br><a href="{{ route('medico.recetas.pdf',$f->codigo) }}">PDF</a>@endif</td></tr>@empty<tr><td colspan="4">Todavía no hay registros.</td></tr>@endforelse</tbody></table>{{ $filas->links() }}</div>
@endsection
