<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de auditoría - Clínica CIES</title>
    <style>
        @page { margin: 28px 24px 34px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #111827; }
        h1 { font-size: 15px; margin: 0 0 2px 0; color: #065f46; }
        .meta { font-size: 8px; color: #4b5563; margin-bottom: 2px; }
        .filtros { font-size: 8px; margin: 6px 0 8px 0; padding: 4px 6px; background: #f1f5f9; }
        .aviso { font-size: 8px; margin: 0 0 8px 0; padding: 4px 6px; background: #fef3c7; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #10b981; color: #ffffff; text-align: left; padding: 3px 4px; border: 1px solid #0f9f73; }
        td { padding: 3px 4px; border: 1px solid #d1d5db; vertical-align: top; word-wrap: break-word; }
        tr:nth-child(even) td { background: #f8fafc; }
        .cri-Alta, .cri-Crítica { color: #b91c1c; font-weight: bold; }
        .cri-Media { color: #b45309; }
    </style>
</head>
<body>
    <h1>Reporte de auditoría — Clínica CIES</h1>
    <div class="meta">Generado por <strong>{{ $generadoPor }}</strong> el {{ $generadoEn }} · {{ $registros->count() }} registro(s)</div>

    <div class="filtros">
        <strong>Filtros aplicados:</strong>
        {{ count($filtros) ? implode(' | ', $filtros) : 'ninguno (todos los registros)' }}
    </div>

    @if ($truncado)
        <div class="aviso">
            Se muestran solo los primeros {{ $limite }} registros más recientes. Para obtener el total
            use la exportación a Excel o acote los filtros.
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 8%">Fecha</th>
                <th style="width: 7%">Usuario</th>
                <th style="width: 8%">Rol</th>
                <th style="width: 8%">Acción</th>
                <th style="width: 6%">Crit.</th>
                <th style="width: 9%">Dominio</th>
                <th style="width: 11%">Paciente</th>
                <th style="width: 10%">Registro</th>
                <th style="width: 25%">Detalle</th>
                <th style="width: 8%">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($r->FEC_AUD)->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $r->USR_USR }}</td>
                    <td>{{ $r->DES_ROL }}</td>
                    <td>{{ $r->ACC_AUD }}</td>
                    <td class="cri-{{ $r->CRI_AUD }}">{{ $r->CRI_AUD }}</td>
                    <td>{{ $r->DES_DOM }}</td>
                    <td>
                        @if ($r->COD_PAC)
                            {{ $r->NOM_PAC }} {{ $r->APE_PAC }} (CI {{ $r->CI_PAC }})
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r->ENT_AUD }} · {{ $r->REG_AUD }}</td>
                    <td>{{ $r->DET_AUD }}</td>
                    <td>{{ $r->IP_AUD }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No hay registros con esos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>