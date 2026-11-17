@extends('auth.layout-simple')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
    <h1>Cambiar contraseña</h1>
    <p class="sub">{{ auth()->user()->USR_USR }}</p>

    @if ($obligatorio)
        <div class="alerta aviso">Tu contraseña es temporal. Debes cambiarla para continuar.</div>
    @endif

    <form method="POST" action="{{ route('clave.actualizar') }}">
        @csrf
        <label for="actual">Contraseña actual</label>
        <input type="password" id="actual" name="actual" autofocus required>

        <label for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
        <input type="password" id="nueva" name="nueva" required>

        <label for="nueva_confirmation">Repite la nueva contraseña</label>
        <input type="password" id="nueva_confirmation" name="nueva_confirmation" required>

        <button type="submit">Guardar contraseña</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="secundario">Cerrar sesión</button>
    </form>
@endsection
