@extends('layouts.admin')

@section('titulo', 'Nuevo empleado')

@section('contenido')
    <h1>Nuevo empleado</h1>
    <p class="sub">Registra al personal. Después podrás crearle su cuenta desde Usuarios.</p>

    <form method="POST" action="{{ route('admin.empleados.guardar') }}" class="card formulario ancho">
        @include('admin.empleados._form')

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn principal">Registrar empleado</button>
            <a href="{{ route('admin.empleados') }}" class="btn">Cancelar</a>
        </div>
    </form>
@endsection