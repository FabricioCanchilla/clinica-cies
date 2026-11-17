@extends('auth.layout-simple')
@section('titulo', 'Activar cuenta')
@section('contenido')
<h1>Activa tu cuenta</h1><p class="sub">Elige una contraseña para ingresar con tu número de CI.</p>
<form method="post" action="{{ $accion }}">@csrf
<label for="clave">Contraseña (mínimo 8 caracteres)</label><input id="clave" name="clave" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
<label for="clave_confirmation">Confirmar contraseña</label><input id="clave_confirmation" name="clave_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
<label><input type="checkbox" name="privacidad" value="1" required style="width:auto"> Acepto el aviso de privacidad y el tratamiento de mis datos.</label>
<label><input type="checkbox" name="terminos" value="1" required style="width:auto"> Acepto los términos de uso.</label>
<button type="submit">Activar cuenta</button></form>
@endsection
