@extends('auth.layout-simple')

@section('titulo', 'Nueva contraseña')

@section('contenido')
    <h1>Crear una contraseña nueva</h1>
    <p class="sub">Cuenta: <strong>{{ $usuario }}</strong>. Mínimo 8 caracteres.</p>

    <form method="POST" action="{{ $accion }}">
        @csrf
        <label for="clave">Nueva contraseña</label>
        <input id="clave" type="password" name="clave" minlength="8" maxlength="72" required autofocus>
        <label for="clave_confirmation">Repite la contraseña</label>
        <input id="clave_confirmation" type="password" name="clave_confirmation" minlength="8" maxlength="72" required>
        <button type="submit">Guardar contraseña</button>
    </form>

    <a class="volver" href="{{ route('login') }}">Volver al inicio de sesión</a>
@endsection
