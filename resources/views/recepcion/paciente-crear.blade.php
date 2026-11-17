@extends('layouts.admin')

@section('titulo', 'Registrar paciente')

@section('contenido')
    <h1>Registrar paciente</h1>
    <p class="sub">Se crea la ficha y su cuenta con rol Paciente. El usuario es el CI y la clave temporal se cambia en el primer ingreso.</p>

    <div class="card formulario ancho">
        <form method="POST" action="{{ route('recepcion.pacientes.guardar') }}">
            @csrf
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0 16px;">
                <div><label>CI</label><input name="ci" value="{{ old('ci', $ci) }}" required maxlength="10" inputmode="numeric"></div>
                <div><label>Fecha de nacimiento</label><input type="date" name="fecnac" value="{{ old('fecnac') }}" required max="{{ now()->subDay()->toDateString() }}"></div>
                <div><label>Nombres</label><input name="nombres" value="{{ old('nombres') }}" required maxlength="40"></div>
                <div><label>Apellidos</label><input name="apellidos" value="{{ old('apellidos') }}" required maxlength="40"></div>
                <div><label>Sexo</label>
                    <select name="sexo" required>
                        <option value="">Selecciona</option>
                        <option value="F" @selected(old('sexo') === 'F')>Femenino</option>
                        <option value="M" @selected(old('sexo') === 'M')>Masculino</option>
                    </select>
                </div>
                <div><label>Estado civil</label>
                    <select name="estciv" required>
                        @foreach (['Soltero','Casado','Divorciado','Viudo','Concubino'] as $ec)
                            <option value="{{ $ec }}" @selected(old('estciv') === $ec)>{{ $ec }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Teléfono</label><input name="telefono" value="{{ old('telefono') }}" required maxlength="8" inputmode="numeric"></div>
                <div><label>Correo</label><input type="email" name="correo" value="{{ old('correo') }}" required maxlength="60"></div>
                <div style="grid-column: 1 / -1;"><label>Dirección</label><input name="direccion" value="{{ old('direccion') }}" required maxlength="100"></div>
            </div>
            <label style="display:flex; gap:8px; align-items:flex-start; font-weight:500; margin: 6px 0 16px;">
                <input type="checkbox" name="privacidad" value="1" required style="width:auto; margin-top:2px;" @checked(old('privacidad'))>
                El paciente acepta el aviso de privacidad y los términos de uso (RN07).
            </label>
            <button type="submit" class="btn principal">Registrar paciente</button>
            <a href="{{ route('recepcion.pacientes') }}" class="btn">Cancelar</a>
        </form>
    </div>
@endsection
