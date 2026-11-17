@extends('layouts.admin')

@section('titulo', 'Pagar anticipo')

@push('head')
<style>
    #codigo { letter-spacing: 10px; text-align: center; font-size: 24px; font-weight: 700; }
    .reloj {
        margin-bottom: 20px; padding: 12px 16px; border-radius: 10px; text-align: center; font-weight: 600;
        background: #fffbeb; color: #92400e; border: 1px solid #fde68a;
    }
    #lienzo { width: 100%; height: 180px; border: 1px dashed var(--tenue); border-radius: 10px; background: #fff; touch-action: none; display: block; margin-bottom: 6px; }
    .ayuda.firma { margin-top: 0; }
</style>
@endpush

@section('contenido')
<h1>Pagar el anticipo</h1>
<p class="sub">El pago se confirma con tu firma y un código de verificación (RF008).</p>

<div class="card formulario ancho">
    <div class="resumen">
        <div><span>Cita</span><span>{{ $cita->COD_CIT }}</span></div>
        <div><span>Fecha y hora</span><span>{{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} a las {{ substr($cita->HOR_CIT, 0, 5) }}</span></div>
        <div><span>Médico</span><span>{{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}</span></div>
        <div><span>Consultorio</span><span>{{ $cita->NOM_CON }}</span></div>
        <div><span>Servicio</span><span>{{ $cita->NOM_SRV }} (Bs {{ $cita->PRE_SRV }})</span></div>
        <div><span>Motivo</span><span>{{ $cita->MOT_CIT }}</span></div>
        <div class="total"><span>Anticipo a pagar</span><span>Bs {{ $cita->anticipo }}</span></div>
    </div>

    <div class="reloj"><i class="fas fa-hourglass-half"></i> Tiempo restante para pagar: <span id="reloj">--:--</span></div>

    @if (!$otp)
        <form method="POST" action="{{ route('paciente.citas.otp', $cita->COD_CIT) }}">
            @csrf
            <button type="submit" class="btn principal bloque"><i class="fas fa-envelope"></i> Enviarme el código de verificación</button>
            <p class="ayuda" style="margin-top: 8px;">Mandamos un código de 6 dígitos al correo registrado en tu ficha.</p>
        </form>
    @else
        <form method="POST" action="{{ route('paciente.citas.confirmar', $cita->COD_CIT) }}" id="form-pago">
            @csrf

            <label for="metodo">Método de pago</label>
            <select name="metodo" id="metodo" required>
                <option value="">Selecciona el método</option>
                @foreach ($metodos as $met)
                    <option value="{{ $met->COD_MET }}" {{ old('metodo') === $met->COD_MET ? 'selected' : '' }}>{{ $met->DES_MET }}</option>
                @endforeach
            </select>

            <label for="codigo">Código enviado a {{ $otp['correo'] }}</label>
            <input type="text" name="codigo" id="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" required autofocus>
            <p class="ayuda">Dura 5 minutos y tienes 3 intentos.</p>

            <label>Tu firma (con el mouse o el dedo)</label>
            <canvas id="lienzo" width="600" height="180"></canvas>
            <p class="ayuda firma">
                El pago se confirma con tu firma y el código (verificación en dos capas).
                <a href="#" id="limpiar">Borrar firma</a>
            </p>
            <input type="hidden" name="firma" id="firma">

            <button type="submit" class="btn principal bloque"><i class="fas fa-lock"></i> Confirmar el pago</button>
        </form>

        <form method="POST" action="{{ route('paciente.citas.otp', $cita->COD_CIT) }}" style="margin-top: 10px;">
            @csrf
            <button type="submit" class="btn bloque"><i class="fas fa-rotate-right"></i> Enviar un código nuevo</button>
        </form>
    @endif

    <div style="margin-top: 18px;">
        <a class="btn" href="{{ route('paciente.dashboard') }}#mis-citas"><i class="fas fa-arrow-left"></i> Volver a mis citas</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const VENCE = new Date('{{ \Carbon\Carbon::parse($cita->FECEXP_CIT)->toIso8601String() }}').getTime();
    const reloj = document.getElementById('reloj');

    function pintarReloj() {
        const faltan = Math.floor((VENCE - Date.now()) / 1000);

        if (faltan <= 0) {
            reloj.textContent = '00:00 (el cupo se liberó, recarga la página)';
            clearInterval(tic);
            return;
        }

        const min = String(Math.floor(faltan / 60)).padStart(2, '0');
        const seg = String(faltan % 60).padStart(2, '0');
        reloj.textContent = min + ':' + seg;
    }

    const tic = setInterval(pintarReloj, 1000);
    pintarReloj();
</script>

<script>
    // RN15/RN16: firma manuscrita en pantalla para confirmar el anticipo
    (function () {
        const c = document.getElementById('lienzo');
        if (!c) return;
        const ctx = c.getContext('2d');
        ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
        let dibujando = false, trazos = 0;

        function punto(e) {
            const r = c.getBoundingClientRect();
            return { x: (e.clientX - r.left) * c.width / r.width, y: (e.clientY - r.top) * c.height / r.height };
        }
        c.addEventListener('pointerdown', e => { dibujando = true; const p = punto(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); c.setPointerCapture(e.pointerId); });
        c.addEventListener('pointermove', e => { if (!dibujando) return; const p = punto(e); ctx.lineTo(p.x, p.y); ctx.stroke(); trazos++; });
        ['pointerup', 'pointerleave', 'pointercancel'].forEach(t => c.addEventListener(t, () => { dibujando = false; }));

        document.getElementById('limpiar').addEventListener('click', e => { e.preventDefault(); ctx.clearRect(0, 0, c.width, c.height); trazos = 0; });

        document.getElementById('form-pago').addEventListener('submit', e => {
            if (trazos < 10) {
                e.preventDefault();
                alert('Falta tu firma.');
                return;
            }
            document.getElementById('firma').value = c.toDataURL('image/png');
        });
    })();
</script>
@endpush
