@extends('layouts.app')

@section('titulo', 'Panel de Administrador')

@section('contenido')
    <h1>Panel de Administrador</h1>
    <p class="sub">Resumen general del sistema</p>

    <div class="grid">
        @foreach ($totales as $etiqueta => $total)
            <div class="card">
                <div class="num">{{ $total }}</div>
                <div class="lbl">{{ $etiqueta }}</div>
            </div>
        @endforeach
    </div>
@endsection