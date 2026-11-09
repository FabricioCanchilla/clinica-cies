@extends('layouts.admin')

@section('titulo', 'Editar empleado')

@section('contenido')
    <h1>Editar empleado</h1>
    <p class="sub">{{ $empleado->COD_EMP }} · {{ $empleado->NOM_EMP }} {{ $empleado->APE_EMP }}</p>

    <form method="POST" action="{{ route('admin.empleados.actualizar', $empleado->COD_EMP) }}" class="card formulario ancho">
        @method('PUT')
        @include('admin.empleados._form')

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn principal">Guardar cambios</button>
            <a href="{{ route('admin.empleados') }}" class="btn">Cancelar</a>
        </div>
    </form>
@endsection