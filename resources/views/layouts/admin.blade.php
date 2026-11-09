<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cies: #10b981;
            --cies-dark: #059669;
            --cies-bg: #ecfdf5;
            --bg: #f8fafc;
            --panel: #ffffff;
            --borde: #e2e8f0;
            --borde-input: #cbd5e1;
            --titulo: #0f172a;
            --texto: #1e293b;
            --suave: #64748b;
            --tenue: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--texto);
            display: flex;
            min-height: 100vh;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: var(--borde-input); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--tenue); }

        /* ---------- Sidebar ---------- */
        aside {
            width: 256px;
            background: var(--panel);
            border-right: 1px solid var(--borde);
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            position: fixed;
            top: 0; bottom: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 10;
        }
        .marca {
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid var(--borde);
            font-size: 20px;
            font-weight: 700;
            color: var(--titulo);
            letter-spacing: .02em;
        }
        .marca span { color: var(--cies); margin-left: 6px; }
        nav { flex: 1; padding: 16px 0; overflow-y: auto; }
        nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            border-left: 4px solid transparent;
            color: #475569;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: background .15s, color .15s;
        }
        nav a i { width: 18px; text-align: center; color: var(--tenue); }
        nav a:hover { background: var(--bg); color: var(--cies); }
        nav a:hover i { color: var(--cies); }
        nav a.activo {
            background: var(--cies-bg);
            color: var(--cies);
            border-left-color: var(--cies);
            font-weight: 600;
        }
        nav a.activo i { color: var(--cies); }
        .nav-titulo {
            padding: 24px 24px 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--tenue);
        }

        /* ---------- Contenido ---------- */
        .contenido { margin-left: 256px; flex: 1; min-width: 0; }
        header {
            height: 64px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            background: var(--panel);
            border-bottom: 1px solid var(--borde);
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            position: sticky;
            top: 0;
            z-index: 5;
        }
        header .titulo { font-size: 18px; font-weight: 700; color: var(--texto); }
        header .quien { display: flex; align-items: center; gap: 16px; font-size: 14px; color: #475569; font-weight: 500; }
        header .quien i { color: var(--cies); margin-right: 4px; }
        header button {
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid var(--borde-input);
            background: var(--panel);
            color: #334155;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            transition: background .15s;
        }
        header button:hover { background: var(--bg); }
        main { padding: 32px; max-width: 1400px; }
        h1 { font-size: 24px; font-weight: 700; color: var(--titulo); margin-bottom: 4px; }
        h2 { font-size: 17px; font-weight: 700; color: var(--titulo); margin-bottom: 14px; }
        .sub { color: var(--suave); margin-bottom: 24px; font-size: 14px; }

        /* ---------- Componentes ---------- */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .card {
            background: var(--panel);
            border: 1px solid var(--borde);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .card .num { font-size: 34px; font-weight: 800; color: var(--cies); line-height: 1.1; }
        .card .lbl { color: var(--suave); font-size: 14px; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th {
            text-align: left;
            padding: 12px;
            background: var(--bg);
            border-bottom: 1px solid var(--borde);
            color: var(--suave);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        td { padding: 12px; border-bottom: 1px solid var(--borde); color: var(--texto); }
        tbody tr:hover td { background: #f8fafc; }
        tr:last-child td { border-bottom: 0; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #f1f5f9;
            color: #475569;
        }
        .badge.activo { background: #d1fae5; color: #047857; }
        .badge.bloqueado, .badge.inactivo, .badge.critica { background: #fee2e2; color: #b91c1c; }
        .badge.media { background: #fef3c7; color: #b45309; }
        .badge.alta { background: #ffedd5; color: #c2410c; }
        .badge.baja { background: #dbeafe; color: #1d4ed8; }

        .barra { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .barra input, .barra select {
            padding: 9px 12px;
            background: var(--panel);
            border: 1px solid var(--borde-input);
            border-radius: 8px;
            color: var(--texto);
            font-family: inherit;
            font-size: 14px;
        }
        .barra input { flex: 1; max-width: 340px; }
        .barra input:focus, .barra select:focus { outline: none; border-color: var(--cies); box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

        .btn {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid var(--borde-input);
            background: var(--panel);
            color: #334155;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            transition: background .15s, border-color .15s;
        }
        .btn:hover { background: var(--bg); border-color: var(--cies); }
        .btn.peligro:hover { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }
        .btn.principal {
            background: var(--cies);
            border-color: var(--cies);
            color: #fff;
            box-shadow: 0 2px 6px rgba(16,185,129,.3);
        }
        .btn.principal:hover { background: var(--cies-dark); border-color: var(--cies-dark); }

        .alerta { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid transparent; }
        .alerta.ok { background: var(--cies-bg); border-color: #a7f3d0; color: #047857; }
        .alerta.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

        .formulario { max-width: 520px; }
        .formulario.ancho { max-width: 760px; }
        .formulario label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .formulario input, .formulario select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 16px;
            background: var(--bg);
            border: 1px solid var(--borde-input);
            border-radius: 8px;
            color: var(--titulo);
            font-family: inherit;
            font-size: 14px;
        }
        .formulario input:focus, .formulario select:focus {
            outline: none;
            border-color: var(--cies);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(16,185,129,.15);
        }
        .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }

        @media (max-width: 800px) {
            body { flex-direction: column; }
            aside { position: static; width: 100%; flex-direction: row; overflow-x: auto; border-right: 0; border-bottom: 1px solid var(--borde); }
            .marca, .nav-titulo { display: none; }
            nav { display: flex; padding: 0; overflow: visible; }
            nav a { border-left: 0; border-bottom: 3px solid transparent; white-space: nowrap; padding: 14px 16px; }
            nav a.activo { border-bottom-color: var(--cies); }
            .contenido { margin-left: 0; }
            header { padding: 0 16px; }
            main { padding: 20px 16px; }
            .fila { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside>
        <div class="marca">Clínica <span>CIES</span></div>
        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'activo' : '' }}">
                <i class="fas fa-home"></i> Dashboard
            </a>

            <div class="nav-titulo">Gestión</div>
            <a href="{{ route('admin.usuarios') }}" class="{{ request()->routeIs('admin.usuarios*') ? 'activo' : '' }}">
                <i class="fas fa-users"></i> Usuarios
            </a>
            <a href="{{ route('admin.empleados') }}" class="{{ request()->routeIs('admin.empleados*') ? 'activo' : '' }}">
                <i class="fas fa-id-badge"></i> Empleados
            </a>
            <a href="{{ route('admin.pacientes') }}" class="{{ request()->routeIs('admin.pacientes*') ? 'activo' : '' }}">
                <i class="fas fa-user-injured"></i> Pacientes
            </a>
            <a href="{{ route('admin.citas') }}" class="{{ request()->routeIs('admin.citas*') ? 'activo' : '' }}">
                <i class="fas fa-calendar-check"></i> Citas
            </a>
            <a href="{{ route('admin.caja') }}" class="{{ request()->routeIs('admin.caja*') ? 'activo' : '' }}">
                <i class="fas fa-cash-register"></i> Caja
            </a>
            <a href="{{ route('admin.internacion') }}" class="{{ request()->routeIs('admin.internacion*') ? 'activo' : '' }}">
                <i class="fas fa-bed"></i> Internación
            </a>

            <div class="nav-titulo">Control</div>
            <a href="{{ route('admin.auditoria') }}" class="{{ request()->routeIs('admin.auditoria*') ? 'activo' : '' }}">
                <i class="fas fa-clipboard-list"></i> Auditoría
            </a>
        </nav>
    </aside>

    <div class="contenido">
        <header>
            <span class="titulo">Panel de Administración</span>
            <div class="quien">
                <span><i class="fas fa-user-shield"></i> {{ auth()->user()->USR_USR }} · {{ config('roles.' . auth()->user()->COD_ROL . '.nombre') }}</span>
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
                <div class="alerta error">
                    @foreach ($errors->all() as $mensaje)
                        <div>{{ $mensaje }}</div>
                    @endforeach
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>
</body>
</html>