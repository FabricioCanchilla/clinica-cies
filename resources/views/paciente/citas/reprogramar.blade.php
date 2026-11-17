<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reprogramar cita | Clínica CIES</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f0fdf4; color: #1e293b; }
        header { padding: 22px 8%; background: linear-gradient(135deg, #047857, #10b981); color: white; font-size: 22px; font-weight: bold; }
        main { max-width: 720px; margin: 35px auto; padding: 0 20px; }
        .panel { background: white; padding: 30px; border: 1px solid #d1fae5; border-radius: 18px; }
        h1 { color: #065f46; margin-top: 0; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        select, input { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        .aviso { background: #ecfdf5; color: #065f46; padding: 12px 16px; border-radius: 10px; margin: 14px 0; font-size: 14px; }
        .acciones { display: flex; gap: 12px; margin-top: 22px; }
        .btn { background: #059669; color: white; border: 0; padding: 12px 22px; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; text-decoration: none; }
        .btn.sec { background: #f1f5f9; color: #334155; }
    </style>
</head>
<body>
<header>Clínica CIES · Reprogramar cita</header>
<main>
    <section class="panel">
        <h1>Reprogramar cita {{ $cita->COD_CIT }}</h1>
        <p>
            <strong>Actual:</strong> {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} a las {{ substr($cita->HOR_CIT, 0, 5) }}<br>
            <strong>Médico:</strong> {{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}<br>
            <strong>Servicio:</strong> {{ $cita->NOM_SRV }}
        </p>
        <div class="aviso">RN22: se puede reprogramar hasta 24 horas antes y como máximo 2 veces (usadas: {{ $veces }} de 2). El anticipo pagado se traslada a la nueva fecha.</div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('paciente.citas.reprogramar.guardar', $cita->COD_CIT) }}">
            @csrf
            <label for="fecha">Nueva fecha</label>
            <input type="date" id="fecha" name="fecha" min="{{ $minFecha }}" max="{{ $maxFecha }}" value="{{ old('fecha') }}" required>

            <label for="hora">Nueva hora (mismo médico)</label>
            <select id="hora" name="hora" required>
                <option value="">Primero elige la fecha</option>
            </select>

            <div class="acciones">
                <button type="submit" class="btn">Reprogramar</button>
                <a href="{{ route('paciente.dashboard') }}" class="btn sec">Volver</a>
            </div>
        </form>
    </section>
</main>
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
</body>
</html>
