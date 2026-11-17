<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #ecfdf5; color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .caja { background: #fff; border: 1px solid #d1fae5; border-radius: 16px; padding: 32px; width: 100%; max-width: 440px; box-shadow: 0 10px 30px rgba(5,150,105,.08); }
        .marca { color: #059669; font-weight: 800; margin-bottom: 18px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .sub { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin: 12px 0 4px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; }
        input:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,.15); }
        button { margin-top: 18px; width: 100%; padding: 11px; background: #059669; color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; }
        button:hover { background: #047857; }
        .alerta { padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .volver { display: inline-block; margin-top: 18px; color: #059669; font-size: 14px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="caja">
        <div class="marca"><x-brand-logo :width="170" /></div>
        @if (session('ok'))
            <div class="alerta ok">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="alerta error">{{ $errors->first() }}</div>
        @endif
        @yield('contenido')
    </div>
</body>
</html>
