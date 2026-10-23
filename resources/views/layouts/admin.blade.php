<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0d1117;
            --panel: #161b22;
            --borde: #263041;
            --texto: #e6edf3;
            --suave: #8b98a8;
            --acento: #2dd4bf;
            --acento-bg: rgba(45, 212, 191, 0.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--texto);
            display: flex;
            min-height: 100vh;
        }
        h1, h2, .marca { font-family: 'Space Grotesk', 'Inter', sans-serif; }

        /* Sidebar */
        aside {
            width: 240px;
            background: var(--panel);
            border-right: 1px solid var(--borde);
            padding: 24px 14px;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            display: flex;
            flex-direction: column;
        }
        .marca { font-size: 20px; font-weight: 700; padding: 0 10px 24px; }
        .marca span { color: var(--acento); }
        nav a {
            display: block;
            padding: 10px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            color: var(--suave);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        nav a:hover { background: rgba(255,255,255,0.04); color: var(--texto); }
        nav a.activo { background: var(--acento-bg); color: var(--acento); }
        .nav-titulo { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--suave); padding: 16px 12px 8px; }

        /* Contenido */
        .contenido { margin-left: 240px; flex: 1; min-width: 0; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 32px;
            border-bottom: 1px solid var(--borde);
            background: var(--panel);
        }
        header .quien { font-size: 14px; color: var(--suave); display: flex; align-items: center; gap: 14px; }
        header button {
            padding: 7px 14px; border-radius: 8px;
            border: 1px solid var(--borde); background: transparent;
            color: var(--texto); cursor: pointer; font-family: inherit;
        }
        header button:hover { border-color: var(--acento); }
        main { padding: 32px; max-width: 1200px; }
        h1 { font-size: 26px; margin-bottom: 4px; }
        .sub { color: var(--suave); margin-bottom: 28px; font-size: 14px; }

        /* Componentes */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .card { background: var(--panel); border: 1px solid var(--borde); border-radius: 12px; padding: 20px; }
        .card .num { font-family: 'Space Grotesk', sans-serif; font-size: 34px; font-weight: 700; color: var(--acento); }
        .card .lbl { color: var(--suave); font-size: 14px; margin-top: 4px; }
        h2 { font-size: 17px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; color: var(--suave); font-weight: 500; padding: 10px 12px; border-bottom: 1px solid var(--borde); }
        td { padding: 12px; border-bottom: 1px solid var(--borde); }
        tr:last-child td { border-bottom: 0; }
        .badge { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 500; }
        .badge.activo { background: rgba(45,212,191,.15); color: var(--acento); }
        .badge.bloqueado, .badge.inactivo { background: rgba(244,63,94,.15); color: #fda4af; }
        .barra { display: flex; gap: 10px; margin-bottom: 18px; }
        .barra input {
            flex: 1; max-width: 340px; padding: 9px 12px;
            background: var(--panel); border: 1px solid var(--borde);
            border-radius: 8px; color: var(--texto); font-family: inherit;
        }
        .btn {
            padding: 7px 14px; border-radius: 8px; border: 1px solid var(--borde);
            background: transparent; color: var(--texto); cursor: pointer;
            font-family: inherit; font-size: 13px; text-decoration: none; display: inline-block;
        }
        .btn:hover { border-color: var(--acento); }
        .btn.peligro:hover { border-color: #fda4af; color: #fda4af; }
        .alerta { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alerta.ok { background: rgba(45,212,191,.12); color: var(--acento); }
        .alerta.error { background: rgba(244,63,94,.12); color: #fda4af; }
        @media (max-width: 800px) {
            aside { position: static; width: 100%; flex-direction: row; overflow-x: auto; }
            body { flex-direction: column; }
            .contenido { margin-left: 0; }
            .marca, .nav-titulo { display: none; }
            nav { display: flex; gap: 6px; }
        }
    </style>
</head>
<body>
    <aside>
        <div class="marca">Clínica <span>CIES</span></div>
        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'activo' : '' }}">Dashboard</a>

            <div class="nav-titulo">Gestión</div>
            <a href="{{ route('admin.usuarios') }}" class="{{ request()->routeIs('admin.usuarios*') ? 'activo' : '' }}">Usuarios</a>
            <a href="{{ route('admin.pacientes') }}" class="{{ request()->routeIs('admin.pacientes') ? 'activo' : '' }}">Pacientes</a>
            <a href="{{ route('admin.citas') }}" class="{{ request()->routeIs('admin.citas') ? 'activo' : '' }}">Citas</a>
            <a href="{{ route('admin.caja') }}" class="{{ request()->routeIs('admin.caja') ? 'activo' : '' }}">Caja</a>
            <a href="{{ route('admin.internacion') }}" class="{{ request()->routeIs('admin.internacion') ? 'activo' : '' }}">Internación</a>

            <div class="nav-titulo">Control</div>
            <a href="{{ route('admin.auditoria') }}" class="{{ request()->routeIs('admin.auditoria') ? 'activo' : '' }}">Auditoría</a>
        </nav>
    </aside>

    <div class="contenido">
        <header>
            <span></span>
            <div class="quien">
                <span>{{ auth()->user()->USR_USR }} · {{ config('roles.' . auth()->user()->COD_ROL . '.nombre') }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Cerrar sesión</button>
                </form>
            </div>
        </header>
        <main>
            @if (session('ok'))
                <div class="alerta ok">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="alerta error">{{ $errors->first() }}</div>
            @endif

            @yield('contenido')
        </main>
    </div>
</body>
</html>