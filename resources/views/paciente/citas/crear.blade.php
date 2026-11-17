<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar cita | Clínica CIES</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0fdf4;
            color: #1e293b;
        }

        header {
            padding: 22px 8%;
            background: linear-gradient(135deg, #047857, #10b981);
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        main {
            max-width: 900px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .panel {
            background: white;
            padding: 30px;
            border: 1px solid #d1fae5;
            border-radius: 18px;
        }

        h1 { color: #065f46; }

        .campo { margin: 20px 0; }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        select, input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
        }

        textarea { resize: vertical; }

        .aviso {
            margin-top: 22px;
            padding: 16px;
            border-radius: 10px;
            background: #ecfdf5;
            color: #065f46;
        }

        .error {
            margin-bottom: 22px;
            padding: 16px;
            border-radius: 10px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .error ul { margin: 0; padding-left: 20px; }

        .boton {
            width: 100%;
            margin-top: 10px;
            padding: 14px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .boton:hover { background: #047857; }

        .volver {
            display: inline-block;
            margin-top: 22px;
            color: #047857;
            text-decoration: none;
            font-weight: bold;
        }

        .ayuda {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/paciente.css') }}"></head>
<body>

<header><div class="paciente-logo"><x-brand-logo :width="155" /></div><strong>Portal del paciente</strong><a class="btn btn-sec" href="{{ route('paciente.dashboard') }}">Volver al inicio</a></header>@include('paciente._navegacion')

<main>
    <section class="panel">

        <h1>Agendar cita médica</h1>

        <p>Elige al profesional y el horario. El cupo te queda reservado {{ $minutos }} minutos para pagar el anticipo.</p>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('paciente.citas.guardar') }}">
            @csrf

            <div class="campo">
                <label for="especialidad">Especialidad</label>
                <select id="especialidad" required>
                    <option value="">Selecciona una especialidad</option>
                    @foreach ($especialidades as $esp)
                        <option value="{{ $esp->COD_ESP }}">{{ $esp->DES_ESP }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo">
                <label for="medico">Médico</label>
                <select name="medico" id="medico" required disabled>
                    <option value="">Primero elige la especialidad</option>
                </select>
            </div>

            <div class="campo">
                <label for="fecha">Fecha</label>
                <input type="date" name="fecha" id="fecha"
                       min="{{ $minFecha }}" max="{{ $maxFecha }}"
                       value="{{ old('fecha') }}" required>
            </div>

            <div class="campo">
                <label for="hora">Horario disponible</label>
                <select name="hora" id="hora" required disabled>
                    <option value="">Elige médico y fecha</option>
                </select>
                <p class="ayuda">Cada consulta dura 30 minutos.</p>
            </div>

            <div class="campo">
                <label for="servicio">Servicio</label>
                <select name="servicio" id="servicio" required>
                    <option value="">Selecciona el servicio</option>
                    @foreach ($servicios as $srv)
                        <option value="{{ $srv->COD_SRV }}" {{ old('servicio') === $srv->COD_SRV ? 'selected' : '' }}>
                            {{ $srv->NOM_SRV }} - Bs {{ $srv->PRE_SRV }} (anticipo Bs {{ $srv->ANTICIPO }})
                        </option>
                    @endforeach
                </select>
                <p class="ayuda">Para confirmar la cita se paga un anticipo del {{ $porcentaje }} % del servicio.</p>
            </div>

            <div class="campo">
                <label for="motivo">Motivo de la consulta</label>
                <textarea name="motivo" id="motivo" rows="3" maxlength="200" required>{{ old('motivo') }}</textarea>
                <p class="ayuda">Máximo 200 caracteres.</p>
            </div>

            <button type="submit" class="boton">Reservar y pagar el anticipo</button>
        </form>

        <div class="aviso">
            Al reservar, el horario queda bloqueado {{ $minutos }} minutos. Si no pagas el anticipo
            dentro de ese tiempo, el cupo se libera automáticamente.
        </div>

        <a class="volver" href="{{ route('paciente.dashboard') }}">
            Volver a mi panel
        </a>

    </section>
</main>

<script>
    const MEDICOS = @json($medicos);

    const selEspecialidad = document.getElementById('especialidad');
    const selMedico       = document.getElementById('medico');
    const inpFecha        = document.getElementById('fecha');
    const selHora         = document.getElementById('hora');

    function vaciar(select, texto) {
        select.innerHTML = '<option value="">' + texto + '</option>';
    }

    selEspecialidad.addEventListener('change', function () {
        const delArea = MEDICOS.filter(m => m.COD_ESP === this.value);

        vaciar(selMedico, delArea.length ? 'Selecciona el médico' : 'No hay médicos en esta especialidad');
        selMedico.disabled = delArea.length === 0;

        delArea.forEach(function (m) {
            const op = document.createElement('option');
            op.value = m.COD_EMP;
            op.textContent = ((m.TIT_EMP || '') + ' ' + m.NOM_EMP + ' ' + m.APE_EMP).trim();
            selMedico.appendChild(op);
        });

        vaciar(selHora, 'Elige médico y fecha');
        selHora.disabled = true;
    });

    async function cargarHorarios() {
        if (!selMedico.value || !inpFecha.value) {
            return;
        }

        vaciar(selHora, 'Buscando horarios...');
        selHora.disabled = true;

        const url = '{{ route('paciente.citas.horarios') }}'
                  + '?medico=' + encodeURIComponent(selMedico.value)
                  + '&fecha=' + encodeURIComponent(inpFecha.value);

        try {
            const respuesta = await fetch(url, { headers: { 'Accept': 'application/json' } });

            if (!respuesta.ok) {
                vaciar(selHora, 'No se pudo consultar la disponibilidad');
                return;
            }

            const horas = await respuesta.json();

            vaciar(selHora, horas.length ? 'Selecciona la hora' : 'Sin horarios libres ese día');
            selHora.disabled = horas.length === 0;

            horas.forEach(function (h) {
                const op = document.createElement('option');
                op.value = h;
                op.textContent = h;
                selHora.appendChild(op);
            });
        } catch (e) {
            vaciar(selHora, 'No se pudo consultar la disponibilidad');
        }
    }

    selMedico.addEventListener('change', cargarHorarios);
    inpFecha.addEventListener('change', cargarHorarios);
</script>

</body>
</html>
