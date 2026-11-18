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
        .cab { margin: 0 0 10px; border-bottom: 2px solid #cc783c; }
        .cab td { border: 0; padding: 0 0 8px; vertical-align: middle; }
        .pie { margin-top: 30px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <table class="cab"><tr>
        <td style="width: 45%;">@if (extension_loaded('gd'))<img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-cies-pdf.png'))) }}" style="height: 48px;">@endif</td>
        <td style="text-align: right;"><h1>Receta médica</h1></td>
    </tr></table>
    <p class="sub">{{ $r->COD_REC }} · {{ \Carbon\Carbon::parse($r->FEC_REC)->format('d/m/Y') }} · Estado: {{ $r->EST_REC }}</p>

    <p><strong>Paciente:</strong> {{ $r->NOM_PAC }} {{ $r->APE_PAC }} ({{ $r->COD_PAC }}, CI {{ $r->CI_PAC }})<br>
       <strong>Médico:</strong> {{ $r->TIT_EMP }} {{ $r->NOM_EMP }} {{ $r->APE_EMP }}<br>
       <strong>Diagnóstico:</strong> {{ $r->DIA_CNS }}</p>

    <table>
        <thead><tr><th>Medicamento</th><th>Dosis</th><th>Vía</th><th>Frecuencia</th><th>Duración</th></tr></thead>
        <tbody>
            @foreach ($items as $i)
                <tr>
                    <td>{{ $i->DES_MED }}</td>
                    <td>{{ $i->DOS_DRE }}</td>
                    <td>{{ $i->DES_VIA }}</td>
                    <td>cada {{ $i->FRE_DRE }} h</td>
                    <td>{{ $i->DUR_DRE }} días</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($r->OBS_REC)
        <p style="margin-top: 12px;"><strong>Indicaciones:</strong> {{ $r->OBS_REC }}</p>
    @endif

    <p class="pie">Receta electrónica emitida por el Sistema de Gestión de la Clínica CIES. Se despacha en farmacia con el código {{ $r->COD_REC }}.</p>
</body>
</html>
