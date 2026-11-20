@extends('layouts.admin')

@section('titulo', 'Acceso de emergencia')
@section('encabezado', 'Portal Médico · Historias clínicas')

@section('contenido')
    <h1>Acceso de emergencia</h1>
    <p class="sub">{{ $paciente->NOM_PAC }} {{ $paciente->APE_PAC }} (CI {{ $paciente->CI_PAC }}) no es tu paciente.</p>

    <div class="alerta aviso"><i class="fas fa-triangle-exclamation"></i>
        Abrir esta historia sin relación asistencial queda registrado en auditoría con criticidad <strong>Alta</strong> (RF047),
        con tu usuario, fecha, hora, IP y el motivo que escribas.
    </div>

    <div class="card formulario">
        <form method="POST" action="{{ route('medico.historia.emergencia', $paciente->COD_PAC) }}">
            @csrf
            <label for="motivo">Motivo del acceso</label>
            <textarea id="motivo" name="motivo" rows="3" minlength="10" maxlength="200" required placeholder="Ej. Paciente ingresa por emergencia inconsciente, se requiere ver alergias"></textarea>
            <button class="btn acento"><i class="fas fa-unlock"></i> Abrir con acceso de emergencia</button>
            <a href="{{ route('medico.historia.buscar') }}" class="btn">Volver</a>
        </form>
    </div>
@endsection
