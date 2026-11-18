<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #eef5f0, #fbf1e9); color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .caja { background: #fff; border: 1px solid #c9ddd0; border-top: 5px solid #cc783c; border-radius: 16px; padding: 32px; width: 100%; max-width: 440px; box-shadow: 0 10px 30px rgba(76,125,95,.12); }
        .marca { text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
        .marca img { max-width: 220px; height: auto; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .sub { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin: 12px 0 4px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; background: #f6f8f7; }
        input:focus { outline: none; border-color: #4c7d5f; background: #fff; box-shadow: 0 0 0 3px rgba(76,125,95,.18); }
        button { margin-top: 18px; width: 100%; padding: 11px; background: #4c7d5f; color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; }
        button:hover { background: #3b6449; }
        button.secundario { background: none; color: #64748b; font-weight: 500; font-size: 13px; text-decoration: underline; margin-top: 10px; }
        button.secundario:hover { background: none; color: #b91c1c; }
        .alerta { padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .ok { background: #eef5f0; color: #2d4c39; border: 1px solid #c9ddd0; }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .aviso { background: #fbf1e9; color: #7a4219; border: 1px solid #f1d3bd; }
        .volver { display: inline-block; margin-top: 18px; color: #3b6449; font-size: 14px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="caja">
        <div class="marca"><img src="{{ asset('images/logo-cies.png') }}" alt="Clínica CIES"></div>
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
