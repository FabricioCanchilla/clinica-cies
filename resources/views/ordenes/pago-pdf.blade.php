<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de pago {{ $o->NRO }}</title>
<style>
    @page { margin: 22px 24px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; }
    table { width: 100%; border-collapse: collapse; }
    .op-cab { border-bottom: 3px solid #cc783c; margin-bottom: 10px; }
    .op-cab td { padding-bottom: 8px; vertical-align: top; }
    .op-marca { font-size: 15px; font-weight: bold; color: #3b6449; }
    .op-sub { font-size: 8px; color: #64748b; }
    .op-titulo { font-size: 13px; font-weight: bold; }
    .op-nro { font-size: 11px; color: #cc783c; font-weight: bold; }
    .op-datos td { padding: 3px; }
    .op-datos td.k { color: #64748b; width: 80px; }
    .op-detalle { margin-top: 10px; }
    .op-detalle th { background: #eef5f0; color: #3b6449; text-align: left; padding: 5px; font-size: 9px; }
    .op-detalle td { padding: 5px; border-bottom: 1px solid #e2e8f0; }
    .op-detalle .menos td { color: #64748b; }
    .op-detalle tfoot td { font-weight: bold; font-size: 12px; background: #eef5f0; color: #3b6449; border-top: 2px solid #4c7d5f; }
    .der { text-align: right; }
    .op-estado { margin: 10px 0; padding: 7px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .op-estado.pagada { background: #eef5f0; border-color: #c9ddd0; color: #2d4c39; }
    .op-nota { font-size: 8.5px; color: #64748b; }
</style>
</head>
<body>
    @include('ordenes._documento')
</body>
</html>
