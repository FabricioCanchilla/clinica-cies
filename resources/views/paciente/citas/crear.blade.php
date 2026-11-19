@extends('layouts.admin')

@section('titulo', 'Agendar cita')

@section('contenido')
<h1>Agendar cita médica</h1>
<p class="sub">Elige la especialidad, el profesional y el horario. El cupo te queda reservado {{ $minutos }} minutos para pagar el anticipo.</p>

<div class="card formulario ancho">
    <form method="POST" action="{{ route('paciente.citas.guardar') }}">
        @csrf

        <div class="fila">
            <div>
                <label for="especialidad">Especialidad</label>
                <select id="especialidad" required>
                    <option value="">Selecciona una especialidad</option>
                    @foreach ($especialidades as $esp)
                        <option value="{{ $esp->COD_ESP }}">{{ $esp->DES_ESP }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="medico">Médico</label>
                <select name="medico" id="medico" required disabled>
                    <option value="">Primero elige la especialidad</option>
                </select>
            </div>
        </div>

        <div class="fila">
            <div>
                <label for="fecha">Fecha</label>
                <input type="date" name="fecha" id="fecha" min="{{ $minFecha }}" max="{{ $maxFecha }}" value="{{ old('fecha') }}" required>
            </div>
            <div>
                <label for="hora">Horario disponible</label>
                <select name="hora" id="hora" required disabled>
                    <option value="">Elige médico y fecha</option>
                </select>
                <p class="ayuda">Cada consulta dura 30 minutos.</p>
            </div>
        </div>

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

        <label for="motivo">Motivo de la consulta</label>
        <textarea name="motivo" id="motivo" rows="3" maxlength="200" required>{{ old('motivo') }}</textarea>
        <p class="ayuda">Máximo 200 caracteres.</p>

        <div class="alerta info">
            <i class="fas fa-clock"></i>
            Al reservar, el horario queda bloqueado {{ $minutos }} minutos. Si no pagas el anticipo
            dentro de ese tiempo, el cupo se libera automáticamente.
        </div>

        <div class="acciones">
            <button type="submit" class="btn principal"><i class="fas fa-calendar-plus"></i> Reservar y pagar el anticipo</button>
            <a class="btn" href="{{ route('paciente.dashboard') }}#mis-citas"><i class="fas fa-arrow-left"></i> Volver a mis citas</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
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

        // Propone el servicio de consulta de la especialidad elegida
        const nombre = (this.selectedOptions[0] ? this.selectedOptions[0].textContent : '').trim().toLowerCase();
        const selServicio = document.getElementById('servicio');
        const op = [...selServicio.options].find(o => o.value && o.textContent.toLowerCase().includes(nombre))
                || (nombre === 'medicina general' ? [...selServicio.options].find(o => o.textContent.toLowerCase().includes('consulta general')) : null);
        if (op) selServicio.value = op.value;
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
@endpush
