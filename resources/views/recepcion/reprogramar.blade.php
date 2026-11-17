@extends('layouts.admin')

@section('titulo', 'Reprogramar cita')

@section('contenido')
    <h1>Reprogramar cita {{ $cita->COD_CIT }}</h1>
    <p class="sub">
        {{ $cita->NOM_PAC }} {{ $cita->APE_PAC }} · {{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }} ·
        actual: {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} {{ substr($cita->HOR_CIT, 0, 5) }}
    </p>
    <div class="alerta aviso">RN21: cuando la clínica reprograma, el cambio es sin costo y el anticipo se traslada. No consume los 2 cambios del paciente (RN22).</div>

    <div class="card formulario">
        <form method="POST" action="{{ route('recepcion.reprogramar.guardar', $cita->COD_CIT) }}">
            @csrf
            <label for="fecha">Nueva fecha</label>
            <input type="date" id="fecha" name="fecha" min="{{ $minFecha }}" max="{{ $maxFecha }}" value="{{ old('fecha') }}" required>
            <label for="hora">Nueva hora</label>
            <select id="hora" name="hora" required><option value="">Primero elige la fecha</option></select>
            <label for="motivo">Motivo (justificación)</label>
            <input id="motivo" name="motivo" minlength="10" maxlength="150" required value="{{ old('motivo') }}">
            <button type="submit" class="btn principal">Reprogramar</button>
            <a href="{{ route('recepcion.paciente', $cita->COD_PAC) }}" class="btn">Volver</a>
        </form>
    </div>

    <script>
        const selHora = document.getElementById('hora');
        document.getElementById('fecha').addEventListener('change', async (e) => {
            selHora.innerHTML = '<option value="">Buscando horarios...</option>';
            const url = '{{ route('recepcion.horarios') }}?medico={{ $cita->COD_EMP }}&fecha=' + encodeURIComponent(e.target.value);
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const horas = r.ok ? await r.json() : [];
                selHora.innerHTML = '<option value="">' + (horas.length ? 'Selecciona la hora' : 'Sin horarios libres ese día') + '</option>';
                horas.forEach(h => selHora.insertAdjacentHTML('beforeend', `<option value="${h}">${h}</option>`));
            } catch (_) {
                selHora.innerHTML = '<option value="">No se pudieron cargar los horarios</option>';
            }
        });
    </script>
@endsection
