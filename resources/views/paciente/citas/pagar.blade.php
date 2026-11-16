<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar anticipo | Clínica CIES</title>

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
            max-width: 720px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .panel {
            background: white;
            padding: 30px;
            border: 1px solid #d1fae5;
            border-radius: 18px;
        }

        h1 { color: #065f46; margin-top: 0; }

        .resumen {
            margin: 22px 0;
            border: 1px solid #d1fae5;
            border-radius: 12px;
            overflow: hidden;
        }

        .resumen div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 16px;
            border-bottom: 1px solid #ecfdf5;
        }

        .resumen div:last-child { border-bottom: none; }

        .resumen span:first-child { color: #64748b; }

        .resumen span:last-child { font-weight: 600; text-align: right; }

        .total {
            background: #ecfdf5;
            color: #065f46;
            font-size: 18px;
        }

        .reloj {
            margin: 20px 0;
            padding: 14px 16px;
            border-radius: 10px;
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
            text-align: center;
            font-weight: 600;
        }

        .ok {
            margin-bottom: 20px;
            padding: 16px;
            border-radius: 10px;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .error {
            margin-bottom: 20px;
            padding: 16px;
            border-radius: 10px;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .error ul { margin: 0; padding-left: 20px; }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .campo { margin: 20px 0; }

        select, input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
        }

        #codigo {
            letter-spacing: 10px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }

        .boton {
            width: 100%;
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

        .boton.secundario {
            background: white;
            color: #047857;
            border: 2px solid #059669;
        }

        .boton.secundario:hover { background: #ecfdf5; }

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
</head>
<body>

<header>Clínica CIES</header>

<main>
    <section class="panel">

        <h1>Pagar el anticipo</h1>

        @if (session('ok'))
            <div class="ok">{{ session('ok') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="resumen">
            <div>
                <span>Cita</span>
                <span>{{ $cita->COD_CIT }}</span>
            </div>
            <div>
                <span>Fecha y hora</span>
                <span>{{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} a las {{ substr($cita->HOR_CIT, 0, 5) }}</span>
            </div>
            <div>
                <span>Médico</span>
                <span>{{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}</span>
            </div>
            <div>
                <span>Consultorio</span>
                <span>{{ $cita->NOM_CON }}</span>
            </div>
            <div>
                <span>Servicio</span>
                <span>{{ $cita->NOM_SRV }} (Bs {{ $cita->PRE_SRV }})</span>
            </div>
            <div>
                <span>Motivo</span>
                <span>{{ $cita->MOT_CIT }}</span>
            </div>
            <div class="total">
                <span>Anticipo a pagar</span>
                <span>Bs {{ $cita->anticipo }}</span>
            </div>
        </div>

        <div class="reloj">
            Tiempo restante para pagar: <span id="reloj">--:--</span>
        </div>

        @if (!$otp)

            <form method="POST" action="{{ route('paciente.citas.otp', $cita->COD_CIT) }}">
                @csrf
                <button type="submit" class="boton">Enviarme el código de verificación</button>
                <p class="ayuda">Mandamos un código de 6 dígitos al correo registrado en tu ficha.</p>
            </form>

        @else

            <form method="POST" action="{{ route('paciente.citas.confirmar', $cita->COD_CIT) }}" id="form-pago">
                @csrf

                <div class="campo">
                    <label for="metodo">Método de pago</label>
                    <select name="metodo" id="metodo" required>
                        <option value="">Selecciona el método</option>
                        @foreach ($metodos as $met)
                            <option value="{{ $met->COD_MET }}" {{ old('metodo') === $met->COD_MET ? 'selected' : '' }}>
                                {{ $met->DES_MET }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="codigo">Código enviado a {{ $otp['correo'] }}</label>
                    <input type="text" name="codigo" id="codigo"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autocomplete="one-time-code" required autofocus>
                    <p class="ayuda">Dura 5 minutos y tienes 3 intentos.</p>
                </div>

                <div class="campo">
                    <label>Tu firma (con el mouse o el dedo)</label>
                    <canvas id="lienzo" width="600" height="180"
                            style="width: 100%; height: 180px; border: 1px dashed #94a3b8; border-radius: 10px; background: #fff; touch-action: none; display: block;"></canvas>
                    <p class="ayuda">
                        El pago se confirma con tu firma y el código (verificación en dos capas).
                        <a href="#" id="limpiar">Borrar firma</a>
                    </p>
                    <input type="hidden" name="firma" id="firma">
                </div>

                <button type="submit" class="boton">Confirmar el pago</button>
            </form>

            <form method="POST" action="{{ route('paciente.citas.otp', $cita->COD_CIT) }}" style="margin-top: 12px;">
                @csrf
                <button type="submit" class="boton secundario">Enviar un código nuevo</button>
            </form>

        @endif

        <a class="volver" href="{{ route('paciente.dashboard') }}">
            Volver a mi panel
        </a>

    </section>
</main>

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

</body>
</html>
