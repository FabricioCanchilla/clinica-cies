@extends('layouts.admin')

@section('titulo', 'Cobrar cita')

@section('contenido')
    <h1>Cobrar cita {{ $cita->COD_CIT }}</h1>
    <p class="sub">
        {{ $cita->NOM_PAC }} {{ $cita->APE_PAC }} (CI {{ $cita->CI_PAC }}) ·
        {{ $cita->NOM_SRV }} con {{ $cita->TIT_EMP }} {{ $cita->APE_EMP }} ·
        {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} {{ substr($cita->HOR_CIT, 0, 5) }}
    </p>

    <div class="grid" style="margin-bottom: 24px;">
        <div class="card"><div class="num">Bs {{ number_format($cita->PRE_SRV, 2) }}</div><div class="lbl">Precio del servicio</div></div>
        <div class="card"><div class="num">Bs {{ number_format($cita->PAGADO, 2) }}</div><div class="lbl">Ya pagado (anticipo)</div></div>
        <div class="card"><div class="num">Bs {{ number_format($cita->SALDO, 2) }}</div><div class="lbl">Saldo</div></div>
    </div>

    <div class="card formulario">
        <form method="POST" action="{{ route('caja.cobrar.guardar', $cita->COD_CIT) }}">
            @csrf
            <label for="metodo">Método de pago</label>
            <select id="metodo" name="metodo" required>
                @foreach ($metodos as $m)
                    <option value="{{ $m->COD_MET }}" @selected(old('metodo', config('cies.metodo_efectivo')) === $m->COD_MET)>{{ $m->DES_MET }}</option>
                @endforeach
            </select>

            <label for="descuento">Descuento (%)</label>
            <input id="descuento" type="number" name="descuento" min="0" max="99" step="1" value="{{ old('descuento', 0) }}" required>

            <div id="autorizacion" style="display: none; margin-top: 8px; padding: 12px; border: 1px dashed #f59e0b; border-radius: 8px;">
                <p style="font-size: 13px; color: #92400e; margin-bottom: 8px;">
                    Un descuento mayor a {{ $limite }} % requiere la autorización de un administrador (RN29).
                </p>
                <div class="fila">
                    <div>
                        <label for="admin_usuario">Usuario administrador</label>
                        <input id="admin_usuario" type="text" name="admin_usuario" value="{{ old('admin_usuario') }}" autocomplete="off">
                    </div>
                    <div>
                        <label for="admin_clave">Contraseña</label>
                        <input id="admin_clave" type="password" name="admin_clave" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <p style="margin: 14px 0; font-size: 16px;">A cobrar: <strong id="total">Bs {{ number_format($cita->SALDO, 2) }}</strong></p>

            <button type="submit" class="btn principal">Registrar cobro y emitir comprobante</button>
            <a href="{{ route('caja.dashboard') }}" class="btn">Volver</a>
        </form>
    </div>

    <script>
        (function () {
            const saldo = {{ (float) $cita->SALDO }};
            const limite = {{ $limite }};
            const desc = document.getElementById('descuento');
            const caja = document.getElementById('autorizacion');
            const total = document.getElementById('total');
            function actualizar() {
                const d = Math.min(99, Math.max(0, parseInt(desc.value || '0', 10)));
                caja.style.display = d > limite ? 'block' : 'none';
                total.textContent = 'Bs ' + (Math.round(saldo * (100 - d)) / 100).toFixed(2);
            }
            desc.addEventListener('input', actualizar);
            actualizar();
        })();
    </script>
@endsection
