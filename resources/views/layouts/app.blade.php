<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #0d1117; color: #e6edf3; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 14px 28px; background: #161b22; border-bottom: 1px solid #263041; }
        header strong { color: #2dd4bf; }
        .user { display: flex; align-items: center; gap: 14px; font-size: 14px; color: #8b98a8; }
        .user button { padding: 7px 14px; border-radius: 8px; border: 1px solid #263041; background: transparent; color: #e6edf3; cursor: pointer; }
        .user button:hover { border-color: #2dd4bf; }
        main { padding: 32px 28px; max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 24px; margin-bottom: 6px; }
        .sub { color: #8b98a8; margin-bottom: 28px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .card { background: #161b22; border: 1px solid #263041; border-radius: 12px; padding: 20px; }
        .card .num { font-size: 32px; font-weight: 700; color: #2dd4bf; }
        .card .lbl { color: #8b98a8; font-size: 14px; margin-top: 4px; }
    </style>
</head>
<body>
    <header>
        <strong>Clínica CIES</strong>
        <div class="user">
            <span>{{ auth()->user()->USR_USR }} · {{ config('roles.' . auth()->user()->COD_ROL . '.nombre') }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </div>
    </header>
    <main>
        @yield('contenido')
    </main>
</body>
</html>