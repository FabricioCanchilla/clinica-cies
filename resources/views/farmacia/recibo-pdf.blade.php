<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { color: #3b6449; font-size: 16px; margin: 0; }
        .sub { color: #475569; margin: 2px 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #dcebe1; color: #2d4c39; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 6px; }
        .der { text-align: right; }
        .cab { margin: 0 0 10px; border-bottom: 2px solid #cc783c; }
        .cab td { border: 0; padding: 0 0 8px; vertical-align: middle; }
        .total td { font-weight: bold; font-size: 13px; background: #eef5f0; }
        .anulado { color: #b91c1c; font-size: 20px; font-weight: bold; border: 3px solid #b91c1c; padding: 6px; text-align: center; margin: 10px 0; }
        .pie { margin-top: 24px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <table class="cab"><tr>
        <td style="width: 45%;">@if (extension_loaded('gd'))<img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-cies-pdf.png'))) }}" style="height: 48px;">@endif</td>
        <td style="text-align: right;"><h1>Recibo de farmacia</h1><div>N° {{ $v->NRO_VEN }}</div></td>
    </tr></table>
    <p class="sub">{{ \Carbon\Carbon::parse($v->FEC_VEN)->format('d/m/Y H:i') }} · {{ $v->TIP_VEN === 'Hospitalizado' ? 'Cargo a la internación ' . $v->COD_INT : 'Venta en mostrador' }}</p>
    @if ($v->EST_VEN === 'Anulada')
        <div class="anulado">ANULADO · {{ $v->MOTANU_VEN }}</div>
    @endif
    <p><strong>Cliente:</strong> {{ $v->CLI_VEN }} @if ($v->CI_PAC) (CI {{ $v->CI_PAC }}) @endif<br>
       <strong>Atendió:</strong> {{ $v->NOM_EMP }} {{ $v->APE_EMP }}
       @if ($v->DES_MET)<br><strong>Pago:</strong> {{ $v->DES_MET }}@endif</p>

    <table>
        <thead><tr><th>Medicamento</th><th>Lote</th><th class="der">Cant.</th><th class="der">P. unit.</th><th class="der">Importe</th></tr></thead>
        <tbody>
            @foreach ($v->ITEMS as $i)
                <tr>
                    <td>{{ $i->DES_MED }}</td>
                    <td>{{ $i->NRO_LOT }} <small>(vence {{ \Carbon\Carbon::parse($i->FECVEN_LOT)->format('m/Y') }})</small></td>
                    <td class="der">{{ $i->CAN_DVE }}</td>
                    <td class="der">{{ number_format($i->PRE_DVE, 2) }}</td>
                    <td class="der">{{ number_format($i->CAN_DVE * $i->PRE_DVE, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="4">TOTAL Bs</td><td class="der">{{ number_format($v->TOT_VEN, 2) }}</td></tr>
        </tbody>
    </table>
    <p class="pie">Recibo interno de la Clínica CIES; no reemplaza a la factura. Stock descontado bajo el principio PEPS (primero en vencer, primero en salir).</p>
</body>
</html>
