@extends('auth.layout-simple')

@section('titulo', 'Recuperar acceso')

@section('contenido')
    <h1>Recuperar usuario y contraseña</h1>
    <p class="sub">Escribe el correo registrado. Te enviaremos tu usuario y un enlace para crear una contraseña nueva (vence en 30 minutos).</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="correo">Correo electrónico</label>
        <input id="correo" type="email" name="correo" value="{{ old('correo') }}" maxlength="60" required autofocus>
        <button type="submit">Enviar enlace</button>
    </form>

    <a class="volver" href="{{ route('login') }}">Volver al inicio de sesión</a>
@endsection
