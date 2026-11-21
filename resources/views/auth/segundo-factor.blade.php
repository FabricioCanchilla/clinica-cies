@extends('auth.layout-simple')

@section('titulo', 'Verificación en dos pasos')

@section('contenido')
    <h1>Verificación en dos pasos</h1>
    <p class="sub">Enviamos un código de 6 dígitos a <strong>{{ $correo }}</strong>. Vence en 5 minutos y tienes 3 intentos.</p>

    <form method="POST" action="{{ route('login.2fa.verificar') }}">
        @csrf
        <label for="codigo">Código de verificación</label>
        <input id="codigo" type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               autocomplete="one-time-code" required autofocus placeholder="000000">
        <button type="submit">Verificar e ingresar</button>
    </form>

    <form method="POST" action="{{ route('login.2fa.reenviar') }}" style="margin-top:10px;">
        @csrf
        <button type="submit" style="background:transparent; color:#43704f; border:1px solid #c9ddd0;">Enviar un código nuevo</button>
    </form>

    <a class="volver" href="{{ route('login') }}">Volver al inicio de sesión</a>
@endsection
