{{-- Cuerpo de la orden de pago (pantalla y PDF). Variable: $o --}}
@php $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ','); @endphp
<div class="op">
    <table class="op-cab"><tr>
        <td><div class="op-marca">CIES · Clínica La Paz</div><div class="op-sub">Av. Tejada Sorzano #393, Miraflores · Tel. (2) 2216061</div></td>
        <td style="text-align:right;"><div class="op-titulo">ORDEN DE PAGO</div><div class="op-nro">N° {{ $o->NRO }}</div></td>
    </tr></table>

    <table class="op-datos">
        <tr><td class="k">Paciente</td><td><strong>{{ $o->NOM_PAC }} {{ $o->APE_PAC }}</strong> · CI {{ $o->CI_PAC }}</td></tr>
        <tr><td class="k">Especialidad</td><td>{{ $o->DES_ESP ?? '—' }}</td></tr>
        <tr><td class="k">Médico</td><td>{{ $o->TIT_EMP }} {{ $o->NOM_EMP }} {{ $o->APE_EMP }}</td></tr>
        <tr><td class="k">Cita</td><td>{{ \Carbon\Carbon::parse($o->FEC_CIT)->format('d/m/Y') }} a las {{ substr($o->HOR_CIT, 0, 5) }} · {{ $o->NOM_CON }} ({{ $o->UBI_CON }})</td></tr>
        <tr><td class="k">Emitida</td><td>{{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->USR_USR ?? '—' }}</td></tr>
    </table>

    <table class="op-detalle">
        <thead><tr><th>Código</th><th>Concepto</th><th class="der">Importe</th></tr></thead>
        <tbody>
            <tr><td>{{ $o->COD_SRV }}</td><td>{{ $o->NOM_SRV }}</td><td class="der">{{ $bs($o->PRE_SRV) }}</td></tr>
            @foreach ($o->PAGOS as $pg)
                <tr class="menos"><td>{{ $pg->COD_PAG }}</td>
                    <td>(−) {{ $pg->TIP_PAG === 'Adelanto' ? 'Anticipo pagado' : 'Pago' }} el {{ \Carbon\Carbon::parse($pg->FEC_PAG)->format('d/m/Y') }} · {{ $pg->DES_MET }}</td>
                    <td class="der">− {{ $bs($pg->MON_PAG) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="2">SALDO A PAGAR EN CAJA</td><td class="der">{{ $bs($o->SALDO) }}</td></tr>
        </tfoot>
    </table>

    <div class="op-estado {{ $o->SALDO <= 0 ? 'pagada' : '' }}">Estado: <strong>{{ $o->ESTADO_ORDEN }}</strong></div>

    <p class="op-nota">
        Presente esta orden en caja indicando el número <strong>{{ $o->NRO }}</strong> o su CI.
        Se aceptan efectivo, QR, tarjeta y transferencia. El comprobante se emite al confirmar el cobro.
        La orden pierde validez si la cita se cancela o el paciente no asiste.
    </p>
</div>
