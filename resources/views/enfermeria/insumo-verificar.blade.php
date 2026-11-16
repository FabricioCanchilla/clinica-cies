@extends('layouts.admin')

@section('titulo', 'Verificar entrega')

@section('contenido')
    <h1>Verificar entrega del insumo {{ $ins->COD_INS }}</h1>
    <p class="sub">RF032: firma del familiar en pantalla + código OTP enviado al familiar. Al confirmar se emite el comprobante de entrega.</p>

    <div class="grid" style="margin-bottom: 24px;">
        <div class="card">
            <div class="lbl">Paciente</div>
            <div style="font-weight: 600; margin-top: 4px;">{{ $ins->NOM_PAC }} {{ $ins->APE_PAC }}</div>
            <div class="lbl">CI {{ $ins->CI_PAC }}</div>
        </div>
        <div class="card">
            <div class="lbl">Insumo</div>
            <div style="font-weight: 600; margin-top: 4px;">{{ $ins->DES_MED }} {{ $ins->DOS_INS }} · {{ $ins->CAN_INS }} u.</div>
            <div class="lbl">Lote {{ $ins->LOT_INS }} · vence {{ \Carbon\Carbon::parse($ins->FECVEN_INS)->format('d/m/Y') }}</div>
        </div>
        <div class="card">
            <div class="lbl">Entrega</div>
            <div style="font-weight: 600; margin-top: 4px;">{{ $ins->NOM_ENT }} {{ $ins->APE_ENT }}</div>
            <div class="lbl">CI {{ $ins->CI_ENT }}</div>
        </div>
    </div>

    @if ($ins->EST_INS !== 'Pendiente de verificación')
        <div class="alerta aviso">Este insumo está «{{ $ins->EST_INS }}» y ya no se verifica.</div>
        <a href="{{ route('enfermeria.dashboard') }}" class="btn">Volver</a>
    @else
        <h2>1. Enviar el código al familiar</h2>
        <div class="card formulario" style="margin-bottom: 24px;">
            <form method="POST" action="{{ route('enfermeria.insumos.otp', $ins->COD_INS) }}" class="barra" style="margin-bottom: 0;">
                @csrf
                <select name="canal">
                    <option value="CORREO">Correo ({{ $ins->COR_ENT }})</option>
                    <option value="SMS">SMS simulado ({{ $ins->TEL_ENT }})</option>
                </select>
                <button type="submit" class="btn">{{ $otp ? 'Reenviar código' : 'Enviar código' }}</button>
            </form>
            @if ($otp)
                <p style="font-size: 13px; color: var(--suave); margin-top: 8px;">
                    Último código enviado por {{ $otp['canal'] === 'SMS' ? 'SMS' : 'correo' }} a {{ $otp['destino'] }}.
                </p>
            @endif
        </div>

        <h2>2. Firma del familiar y código</h2>
        <div class="card formulario ancho">
            <form method="POST" action="{{ route('enfermeria.insumos.verificar.guardar', $ins->COD_INS) }}" id="form-verificar">
                @csrf
                <label>Firma (con el mouse o el dedo)</label>
                <canvas id="lienzo" width="600" height="200"
                        style="width: 100%; max-width: 600px; height: 200px; border: 1px dashed var(--borde); border-radius: 8px; background: #fff; touch-action: none; display: block;"></canvas>
                <button type="button" class="btn" id="limpiar" style="margin: 8px 0 16px;">Borrar firma</button>
                <input type="hidden" name="firma" id="firma">

                <label for="codigo">Código de 6 dígitos que recibió el familiar</label>
                <input id="codigo" type="text" name="codigo" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required @disabled(!$otp)>

                <button type="submit" class="btn principal" @disabled(!$otp)>Verificar y emitir comprobante</button>
                <a href="{{ route('enfermeria.dashboard') }}" class="btn">Volver</a>
                @if (!$otp)
                    <p style="font-size: 13px; color: #92400e; margin-top: 8px;">Primero envía el código al familiar.</p>
                @endif
            </form>
        </div>

        <script>
            (function () {
                const c = document.getElementById('lienzo');
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

                document.getElementById('limpiar').addEventListener('click', () => { ctx.clearRect(0, 0, c.width, c.height); trazos = 0; });

                document.getElementById('form-verificar').addEventListener('submit', e => {
                    if (trazos < 10) {
                        e.preventDefault();
                        alert('Falta la firma del familiar.');
                        return;
                    }
                    document.getElementById('firma').value = c.toDataURL('image/png');
                });
            })();
        </script>
    @endif
@endsection
