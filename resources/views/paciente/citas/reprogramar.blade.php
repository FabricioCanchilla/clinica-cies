@extends('layouts.admin')

@section('titulo', 'Reprogramar cita')

@section('contenido')
<h1>Reprogramar cita {{ $cita->COD_CIT }}</h1>
<p class="sub">Elige una nueva fecha y hora con el mismo médico.</p>

<div class="card formulario ancho">
    <div class="resumen">
        <div><span>Fecha actual</span><span>{{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} a las {{ substr($cita->HOR_CIT, 0, 5) }}</span></div>
        <div><span>Médico</span><span>{{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}</span></div>
        <div><span>Servicio</span><span>{{ $cita->NOM_SRV }}</span></div>
    </div>

    <div class="alerta info">
        <i class="fas fa-circle-info"></i>
        RN22: se puede reprogramar hasta 24 horas antes y como máximo 2 veces (usadas: {{ $veces }} de 2).
        El anticipo pagado se traslada a la nueva fecha.
    </div>

    <form method="POST" action="{{ route('paciente.citas.reprogramar.guardar', $cita->COD_CIT) }}">
        @csrf
        <div class="fila">
            <div>
                <label for="fecha">Nueva fecha</label>
                <input type="date" id="fecha" name="fecha" min="{{ $minFecha }}" max="{{ $maxFecha }}" value="{{ old('fecha') }}" required>
            </div>
            <div>
                <label for="hora">Nueva hora (mismo médico)</label>
                <select id="hora" name="hora" required>
                    <option value="">Primero elige la fecha</option>
                </select>
            </div>
        </div>

        <div class="acciones">
            <button type="submit" class="btn principal"><i class="fas fa-calendar-alt"></i> Reprogramar</button>
            <a href="{{ route('paciente.dashboard') }}#mis-citas" class="btn"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    const selHora = document.getElementById('hora');
    document.getElementById('fecha').addEventListener('change', async (e) => {
        selHora.innerHTML = '<option value="">Buscando horarios...</option>';
        const url = '{{ route('paciente.citas.horarios') }}'
            + '?medico={{ $cita->COD_EMP }}&fecha=' + encodeURIComponent(e.target.value);
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
@endpush
