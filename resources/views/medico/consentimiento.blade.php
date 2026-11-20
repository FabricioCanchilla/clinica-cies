@extends('layouts.admin')

@section('titulo', 'Consentimiento informado')
@section('encabezado', 'Portal Médico · Consentimiento informado')

@section('contenido')
    <h1>Consentimiento informado</h1>
    <p class="sub">RF024 / RN16: {{ $paciente->NOM_PAC }} {{ $paciente->APE_PAC }} (CI {{ $paciente->CI_PAC }}) firma en pantalla antes del procedimiento. Se guardan la imagen de la firma, la fecha, la hora, el usuario y la IP.</p>

    <div class="card formulario ancho">
        <form method="POST" action="{{ route('medico.historia.consentimiento.guardar', $paciente->COD_PAC) }}" id="form-consentimiento">
            @csrf
            <label for="procedimiento">Procedimiento</label>
            <input id="procedimiento" name="procedimiento" maxlength="150" required value="{{ old('procedimiento') }}" placeholder="Ej. Colocación de DIU, extracción dental, legrado">

            <label for="riesgos">Riesgos, beneficios y alternativas explicados al paciente</label>
            <textarea id="riesgos" name="riesgos" rows="4" minlength="20" maxlength="500" required>{{ old('riesgos') }}</textarea>

            <div class="fila">
                <div>
                    <label for="firmante">Nombre de quien firma</label>
                    <input id="firmante" name="firmante" maxlength="100" required value="{{ old('firmante', $paciente->NOM_PAC . ' ' . $paciente->APE_PAC) }}">
                </div>
                <div>
                    <label for="cita">Cita relacionada (opcional)</label>
                    <select id="cita" name="cita">
                        <option value="">—</option>
                        @foreach ($citas as $c)
                            <option value="{{ $c->COD_CIT }}">{{ \Carbon\Carbon::parse($c->FEC_CIT)->format('d/m/Y') }} {{ substr($c->HOR_CIT, 0, 5) }} · {{ $c->MOT_CIT }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label style="display: flex; gap: 8px; align-items: flex-start; font-size: 13px; font-weight: 500;">
                <input type="checkbox" name="acepta" value="1" required style="width: auto; margin: 2px 0 0;">
                Declaro que recibí información clara sobre el procedimiento, sus riesgos y alternativas, que pude hacer preguntas y que acepto que se realice.
            </label>

            @include('partials.firma', ['form' => 'form-consentimiento', 'campo' => 'firma', 'quien' => 'el paciente'])

            <button class="btn principal"><i class="fas fa-file-signature"></i> Guardar consentimiento firmado</button>
            <a href="{{ route('medico.historia', $paciente->COD_PAC) }}" class="btn">Volver</a>
        </form>
    </div>
@endsection
