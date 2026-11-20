<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Paleta institucional tomada del logo CIES: verde #4C7D5F y naranja #CC783C */
        :root {
            --cies: #4c7d5f;
            --cies-dark: #3b6449;
            --cies-bg: #eef5f0;
            --cies-borde: #c9ddd0;
            --naranja: #cc783c;
            --naranja-dark: #b0622b;
            --naranja-bg: #fbf1e9;
            --bg: #f6f8f7;
            --panel: #ffffff;
            --borde: #e2e8f0;
            --borde-input: #cbd5e1;
            --titulo: #0f172a;
            --texto: #1e293b;
            --suave: #64748b;
            --tenue: #94a3b8;
            --foco: rgba(76,125,95,.18);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--texto);
            display: flex;
            min-height: 100vh;
        }
        a { color: var(--cies-dark); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
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
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-bottom: 3px solid var(--naranja);
            text-decoration: none;
        }
        .marca img { max-height: 54px; max-width: 100%; display: block; }
        nav { flex: 1; padding: 16px 0; overflow-y: auto; }
        nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 24px;
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
            color: var(--cies-dark);
            border-left-color: var(--naranja);
            font-weight: 600;
        }
        nav a.activo i { color: var(--cies); }
        .nav-titulo {
            padding: 22px 24px 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--tenue);
        }
        .pie-lateral {
            padding: 14px 24px;
            border-top: 1px solid var(--borde);
            font-size: 11px;
            color: var(--tenue);
            line-height: 1.5;
        }

        /* ---------- Contenido ---------- */
        .contenido { margin-left: 256px; flex: 1; min-width: 0; }
        header {
            height: 76px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 28px;
            background: var(--panel);
            border-bottom: 1px solid var(--borde);
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            position: sticky;
            top: 0;
            z-index: 5;
        }
        header .titulo { font-size: 18px; font-weight: 700; color: var(--texto); }
        header .titulo small { display: block; font-size: 12px; font-weight: 500; color: var(--suave); }
        header .quien { display: flex; align-items: center; gap: 18px; font-size: 14px; color: #475569; font-weight: 500; }
        header .quien i { color: var(--cies); margin-right: 4px; }
        header .campana { color: #475569; text-decoration: none; position: relative; font-size: 17px; }
        header .campana .cuenta {
            position: absolute; top: -8px; right: -12px;
            background: var(--naranja); color: #fff; border-radius: 999px;
            font-size: 10px; font-weight: 700; padding: 1px 6px;
        }
        header button {
            padding: 7px 16px;
            border-radius: 8px;
            border: 1px solid var(--borde-input);
            background: var(--panel);
            color: #334155;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            transition: background .15s, border-color .15s, color .15s;
        }
        header button:hover { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
        main { padding: 32px; max-width: 1400px; }
        h1 { font-size: 24px; font-weight: 700; color: var(--titulo); margin-bottom: 4px; }
        h2 { font-size: 17px; font-weight: 700; color: var(--titulo); margin-bottom: 14px; }
        h2 i, h3 i { color: var(--cies); margin-right: 6px; }
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
        .card-cabecera { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .card-cabecera h2 { margin-bottom: 0; }
        .tabla { overflow-x: auto; }

        /* Bienvenida y accesos rápidos */
        .bienvenida {
            display: flex; align-items: center; gap: 22px;
            background: linear-gradient(120deg, var(--cies-bg), #fff 70%);
            border: 1px solid var(--cies-borde);
            border-left: 6px solid var(--naranja);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 26px;
        }
        .bienvenida img { height: 64px; }
        .bienvenida h1 { color: var(--cies-dark); }
        .bienvenida p { color: var(--suave); font-size: 14px; line-height: 1.6; }
        .accesos { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
        a.acceso {
            display: flex; gap: 14px; align-items: flex-start;
            background: var(--panel); border: 1px solid var(--borde); border-radius: 16px;
            padding: 18px; text-decoration: none; color: inherit;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            transition: transform .15s, box-shadow .15s, border-color .15s;
        }
        a.acceso:hover { transform: translateY(-3px); border-color: var(--cies-borde); box-shadow: 0 8px 20px rgba(15,23,42,.08); }
        .acceso .ico {
            flex-shrink: 0; width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            background: var(--cies-bg); color: var(--cies); font-size: 20px;
        }
        .acceso:nth-child(even) .ico { background: var(--naranja-bg); color: var(--naranja); }
        .acceso strong { display: block; color: var(--titulo); font-size: 15px; margin-bottom: 3px; }
        .acceso span { color: var(--suave); font-size: 13px; line-height: 1.5; }
        .acceso .cifra { display: inline-block; margin-top: 6px; font-weight: 700; color: var(--cies-dark); font-size: 13px; }

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
        td small { color: var(--suave); }
        tbody tr:hover td { background: #f8faf9; }
        tr:last-child td { border-bottom: 0; }
        .derecha { text-align: right; }
        .nowrap { white-space: nowrap; }

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
            white-space: nowrap;
        }
        .badge.activo { background: #dcebe1; color: #2d4c39; }
        .badge.bloqueado, .badge.inactivo, .badge.critica { background: #fee2e2; color: #b91c1c; }
        .badge.media { background: #fef3c7; color: #b45309; }
        .badge.alta { background: #ffedd5; color: #c2410c; }
        .badge.baja { background: #dbeafe; color: #1d4ed8; }
        /* RNF011: estados de cita (mismos colores en todos los módulos) */
        .estado-confirmada        { background: #dcebe1; color: #2d4c39; border-color: var(--cies); }
        .estado-pendiente-de-pago { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .estado-atendida          { background: #dbeafe; color: #1d4ed8; border-color: #3b82f6; }
        .estado-cancelada         { background: #fee2e2; color: #b91c1c; border-color: #ef4444; }
        .estado-expirada          { background: #f1f5f9; color: #64748b; border-color: #94a3b8; }
        .estado-no-asistio        { background: #fce7f3; color: #9d174d; border-color: #ec4899; }
        .estado-en-espera         { background: #ede9fe; color: #5b21b6; border-color: #8b5cf6; }
        .estado-en-consulta       { background: #ccfbf1; color: #115e59; border-color: #14b8a6; }
        /* RN06: aviso de cierre por inactividad */
        #aviso-inactividad { position: fixed; right: 20px; bottom: 20px; z-index: 50; max-width: 340px; background: #fff;
            border: 1px solid #fde68a; border-left: 5px solid var(--naranja); border-radius: 12px; padding: 14px 16px;
            box-shadow: 0 10px 30px rgba(15,23,42,.18); font-size: 14px; display: none; }

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
        .barra input:focus, .barra select:focus { outline: none; border-color: var(--cies); box-shadow: 0 0 0 3px var(--foco); }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            transition: background .15s, border-color .15s, color .15s;
        }
        .btn:hover { background: var(--bg); border-color: var(--cies); color: var(--cies-dark); }
        .btn.sm { padding: 4px 10px; font-size: 12px; }
        .btn.peligro { color: #b91c1c; }
        .btn.peligro:hover { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }
        .btn.aviso { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .btn.principal {
            background: var(--cies);
            border-color: var(--cies);
            color: #fff;
            box-shadow: 0 2px 6px rgba(76,125,95,.3);
        }
        .btn.principal:hover { background: var(--cies-dark); border-color: var(--cies-dark); color: #fff; }
        .btn.acento {
            background: var(--naranja);
            border-color: var(--naranja);
            color: #fff;
            box-shadow: 0 2px 6px rgba(204,120,60,.3);
        }
        .btn.acento:hover { background: var(--naranja-dark); border-color: var(--naranja-dark); color: #fff; }
        .btn.bloque { width: 100%; justify-content: center; padding: 12px; font-size: 15px; }
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .acciones form { display: inline; }

        .alerta { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid transparent; }
        .alerta.ok { background: var(--cies-bg); border-color: var(--cies-borde); color: #2d4c39; }
        .alerta.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .alerta.aviso { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .alerta.info { background: var(--naranja-bg); border-color: #f1d3bd; color: #7a4219; }
        .ayuda { font-size: 12px; color: var(--suave); margin-top: -10px; margin-bottom: 16px; }
        .vacio { text-align: center; color: var(--suave); padding: 26px 10px; font-size: 14px; }
        .vacio i { display: block; font-size: 28px; color: var(--cies-borde); margin-bottom: 8px; }

        .formulario { max-width: 520px; }
        .formulario.ancho { max-width: 760px; }
        .formulario label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .formulario input, .formulario select, .formulario textarea {
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
        .formulario textarea { resize: vertical; }
        .formulario input:focus, .formulario select:focus, .formulario textarea:focus {
            outline: none;
            border-color: var(--cies);
            background: #fff;
            box-shadow: 0 0 0 3px var(--foco);
        }
        .formulario input:disabled, .formulario select:disabled { opacity: .6; cursor: not-allowed; }
        .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }

        /* Resumen clave–valor (detalle de cita, pago) */
        .resumen { border: 1px solid var(--borde); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        .resumen div { display: flex; justify-content: space-between; gap: 16px; padding: 11px 16px; border-bottom: 1px solid var(--borde); font-size: 14px; }
        .resumen div:last-child { border-bottom: 0; }
        .resumen span:first-child { color: var(--suave); }
        .resumen span:last-child { font-weight: 600; text-align: right; }
        .resumen .total { background: var(--cies-bg); color: var(--cies-dark); font-size: 17px; }

        @media (max-width: 800px) {
            body { flex-direction: column; }
            aside { position: static; width: 100%; flex-direction: row; overflow-x: auto; border-right: 0; border-bottom: 1px solid var(--borde); }
            .marca { height: auto; border-bottom: 0; padding: 8px 12px; }
            .marca img { max-height: 36px; }
            .nav-titulo, .pie-lateral { display: none; }
            nav { display: flex; padding: 0; overflow: visible; }
            nav a { border-left: 0; border-bottom: 3px solid transparent; white-space: nowrap; padding: 14px 16px; }
            nav a.activo { border-bottom-color: var(--naranja); }
            .contenido { margin-left: 0; }
            header { padding: 0 16px; height: 64px; }
            header .quien .usuario { display: none; }
            main { padding: 20px 16px; }
            .fila { grid-template-columns: 1fr; }
            .bienvenida { flex-direction: column; text-align: center; }
        }
    </style>
    @stack('head')
</head>
<body>
    @php
        $rol = auth()->user()->COD_ROL;
        $iconosRol = [
            'ROL001' => 'fa-user-shield', 'ROL002' => 'fa-clipboard-check', 'ROL003' => 'fa-user-doctor',
            'ROL004' => 'fa-user-nurse', 'ROL005' => 'fa-cash-register', 'ROL006' => 'fa-user',
            'ROL007' => 'fa-flask', 'ROL008' => 'fa-prescription-bottle-medical', 'ROL009' => 'fa-magnifying-glass-chart',
        ];
    @endphp
    <aside>
        <a class="marca" href="{{ route(config('roles.' . $rol . '.ruta')) }}" title="Clínica CIES">
            <img src="{{ asset('images/logo-cies.png') }}" alt="CIES · Salud sexual, salud reproductiva, salud integral">
        </a>
        <nav>
            @if ($rol === 'ROL001')
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
                <a href="{{ route('admin.recomendador') }}" class="{{ request()->routeIs('admin.recomendador*') ? 'activo' : '' }}">
                    <i class="fas fa-chart-line"></i> Planificación de camas
                </a>

                <div class="nav-titulo">Reportes</div>
                <a href="{{ route('admin.reportes') }}" class="{{ request()->routeIs('admin.reportes*') ? 'activo' : '' }}">
                    <i class="fas fa-chart-pie"></i> Reportes de atención
                </a>
                <a href="{{ route('reportes.pacientes') }}" class="{{ request()->routeIs('reportes.*') ? 'activo' : '' }}">
                    <i class="fas fa-file-medical"></i> Reporte por paciente
                </a>

                <div class="nav-titulo">Control</div>
                <a href="{{ route('admin.auditoria') }}" class="{{ request()->routeIs('admin.auditoria*') ? 'activo' : '' }}">
                    <i class="fas fa-clipboard-list"></i> Auditoría
                </a>
                <a href="{{ route('admin.configuracion') }}" class="{{ request()->routeIs('admin.configuracion*') ? 'activo' : '' }}">
                    <i class="fas fa-sliders"></i> Configuración
                </a>
            @elseif ($rol === 'ROL009')
                <div class="nav-titulo">Control</div>
                <a href="{{ route('admin.auditoria') }}" class="{{ request()->routeIs('admin.auditoria*') ? 'activo' : '' }}">
                    <i class="fas fa-clipboard-list"></i> Auditoría
                </a>
            @elseif ($rol === 'ROL007')
                <a href="{{ route('laboratorio.dashboard') }}" class="activo">
                    <i class="fas fa-flask"></i> Órdenes de examen
                </a>
            @elseif ($rol === 'ROL008')
                <a href="{{ route('farmacia.dashboard') }}" class="{{ request()->routeIs('farmacia.dashboard', 'farmacia.receta', 'farmacia.despachar') ? 'activo' : '' }}">
                    <i class="fas fa-prescription-bottle-medical"></i> Recetas
                </a>
                <a href="{{ route('farmacia.lotes') }}" class="{{ request()->routeIs('farmacia.lotes*') ? 'activo' : '' }}">
                    <i class="fas fa-boxes-stacked"></i> Lotes y stock
                </a>
                <a href="{{ route('farmacia.ventas') }}" class="{{ request()->routeIs('farmacia.ventas*') ? 'activo' : '' }}">
                    <i class="fas fa-cash-register"></i> Venta en mostrador
                </a>
            @elseif ($rol === 'ROL004')
                <a href="{{ route('enfermeria.internados') }}" class="{{ request()->routeIs('enfermeria.internados', 'enfermeria.internaciones.*') ? 'activo' : '' }}">
                    <i class="fas fa-bed-pulse"></i> Pacientes internados
                </a>
                <a href="{{ route('enfermeria.dashboard') }}" class="{{ request()->routeIs('enfermeria.dashboard') ? 'activo' : '' }}">
                    <i class="fas fa-hand-holding-medical"></i> Insumos de familiares
                </a>
                <a href="{{ route('enfermeria.insumos.crear') }}" class="{{ request()->routeIs('enfermeria.insumos.crear') ? 'activo' : '' }}">
                    <i class="fas fa-plus"></i> Registrar insumo
                </a>
                <a href="{{ route('enfermeria.camas') }}" class="{{ request()->routeIs('enfermeria.camas') ? 'activo' : '' }}">
                    <i class="fas fa-bed"></i> Camas e internación
                </a>
            @elseif ($rol === 'ROL002')
                <a href="{{ route('recepcion.dashboard') }}" class="{{ request()->routeIs('recepcion.dashboard') ? 'activo' : '' }}">
                    <i class="fas fa-clipboard-check"></i> Citas de hoy
                </a>
                <a href="{{ route('recepcion.pacientes') }}" class="{{ request()->routeIs('recepcion.pacientes*', 'recepcion.paciente', 'recepcion.reprogramar') ? 'activo' : '' }}">
                    <i class="fas fa-users"></i> Pacientes
                </a>
                <a href="{{ route('reportes.pacientes') }}" class="{{ request()->routeIs('reportes.*') ? 'activo' : '' }}">
                    <i class="fas fa-file-medical"></i> Reporte por paciente
                </a>
            @elseif ($rol === 'ROL005')
                <a href="{{ route('caja.dashboard') }}" class="activo">
                    <i class="fas fa-cash-register"></i> Caja
                </a>
            @elseif ($rol === 'ROL003')
                <a href="{{ route('medico.dashboard') }}#citas-hoy" class="{{ request()->routeIs('medico.dashboard') ? 'activo' : '' }}">
                    <i class="fas fa-calendar-day"></i> Citas de hoy
                </a>
                <a href="{{ route('medico.dashboard') }}#agenda">
                    <i class="fas fa-calendar-alt"></i> Mi agenda
                </a>
                <a href="{{ route('medico.historia.buscar') }}" class="{{ request()->routeIs('medico.historia*') ? 'activo' : '' }}">
                    <i class="fas fa-folder-open"></i> Historias clínicas
                </a>
                <a href="{{ route('medico.interconsultas') }}" class="{{ request()->routeIs('medico.interconsultas*') ? 'activo' : '' }}">
                    <i class="fas fa-share-from-square"></i> Interconsultas
                </a>
                <a href="{{ route('medico.estadisticas') }}" class="{{ request()->routeIs('medico.estadisticas') ? 'activo' : '' }}">
                    <i class="fas fa-chart-pie"></i> Mis estadísticas
                </a>
                <a href="{{ route('reportes.pacientes') }}" class="{{ request()->routeIs('reportes.*') ? 'activo' : '' }}">
                    <i class="fas fa-file-medical"></i> Reporte de mis pacientes
                </a>
                @if (request()->routeIs('medico.atender'))
                    <a href="{{ url()->current() }}" class="activo">
                        <i class="fas fa-stethoscope"></i> Atención en curso
                    </a>
                @endif
            @elseif ($rol === 'ROL006')
                @php $enPanel = request()->routeIs('paciente.dashboard'); @endphp
                <a href="{{ route('paciente.dashboard') }}#inicio" data-seccion="inicio" class="{{ $enPanel ? 'activo' : '' }}">
                    <i class="fas fa-home"></i> Inicio
                </a>

                <div class="nav-titulo">Mi atención</div>
                <a href="{{ route('paciente.dashboard') }}#mis-citas" data-seccion="mis-citas"
                   class="{{ request()->routeIs('paciente.citas.*') ? 'activo' : '' }}">
                    <i class="fas fa-calendar-check"></i> Mis citas
                </a>
                <a href="{{ route('paciente.dashboard') }}#mi-historial" data-seccion="mi-historial">
                    <i class="fas fa-file-medical"></i> Mi historial
                </a>
                <a href="{{ route('paciente.dashboard') }}#mis-recetas" data-seccion="mis-recetas">
                    <i class="fas fa-prescription"></i> Mis recetas
                </a>
                <a href="{{ route('paciente.dashboard') }}#mis-resultados" data-seccion="mis-resultados">
                    <i class="fas fa-vials"></i> Mis resultados
                </a>

                <div class="nav-titulo">Mi cuenta</div>
                <a href="{{ route('paciente.dashboard') }}#mis-pagos" data-seccion="mis-pagos">
                    <i class="fas fa-receipt"></i> Mis pagos
                </a>
                <a href="{{ route('paciente.dashboard') }}#mis-insumos" data-seccion="mis-insumos">
                    <i class="fas fa-hand-holding-medical"></i> Insumos entregados
                </a>
                <a href="{{ route('paciente.dashboard') }}#mi-perfil" data-seccion="mi-perfil">
                    <i class="fas fa-user-circle"></i> Mi perfil
                </a>
                <a href="{{ route('paciente.reporte') }}" class="{{ request()->routeIs('paciente.reporte') ? 'activo' : '' }}">
                    <i class="fas fa-file-medical"></i> Mi reporte
                </a>
            @endif

            <div class="nav-titulo">General</div>
            <a href="{{ route('notificaciones') }}" class="{{ request()->routeIs('notificaciones') ? 'activo' : '' }}">
                <i class="fas fa-bell"></i> Notificaciones
            </a>
        </nav>
        <div class="pie-lateral">
            CIES · Salud sexual, salud reproductiva, salud integral<br>
            &copy; {{ date('Y') }} Clínica CIES
        </div>
    </aside>

    <div class="contenido">
        <header>
            <span class="titulo">
                @hasSection('encabezado')
                    @yield('encabezado')
                @else
                    Panel de {{ config('roles.' . $rol . '.nombre') }}
                @endif
                <small>@yield('titulo')</small>
            </span>
            <div class="quien">
                @php
                    $nuevas = \Illuminate\Support\Facades\DB::table('NOTIFICACION')
                        ->where('COD_USR', auth()->user()->COD_USR)->where('CAN_NOT', 'Sistema')->where('EST_NOT', 'Enviada')->count();
                @endphp
                <a href="{{ route('notificaciones') }}" title="Notificaciones" class="campana">
                    <i class="fas fa-bell"></i>
                    @if ($nuevas > 0)
                        <span class="cuenta">{{ $nuevas }}</span>
                    @endif
                </a>
                <span class="usuario"><i class="fas {{ $iconosRol[$rol] ?? 'fa-user' }}"></i> {{ auth()->user()->USR_USR }} · {{ config('roles.' . $rol . '.nombre') }}</span>
                <form method="POST" action="{{ route('logout') }}" id="form-logout">
                    @csrf
                    <button type="submit"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                </form>
            </div>
        </header>
        <main>
            @if (session('ok'))
                <div class="alerta ok"><i class="fas fa-circle-check"></i> {{ session('ok') }}</div>
            @endif
            @if (session('aviso'))
                <div class="alerta aviso"><i class="fas fa-triangle-exclamation"></i> {{ session('aviso') }}</div>
            @endif
            @if ($errors->any())
                <div class="alerta error">
                    @foreach ($errors->all() as $mensaje)
                        <div><i class="fas fa-circle-exclamation"></i> {{ $mensaje }}</div>
                    @endforeach
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>
    <div id="aviso-inactividad" role="alert">
        <strong><i class="fas fa-clock"></i> Tu sesión se cerrará por inactividad</strong>
        <p style="margin: 6px 0 10px; color: var(--suave);">Quedan <span id="seg-inactividad">60</span> s. Mueve el mouse o pulsa una tecla para seguir.</p>
    </div>
    <script>
    // RN06 / RNF014: cierre de sesión por inactividad del usuario (minutos configurables por el administrador)
    (function () {
        const limite = {{ (int) config('session.lifetime') }} * 60 * 1000, aviso = 60 * 1000;
        const caja = document.getElementById('aviso-inactividad'), seg = document.getElementById('seg-inactividad');
        let ultimo = Date.now();
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev =>
            document.addEventListener(ev, () => { ultimo = Date.now(); caja.style.display = 'none'; }, { passive: true }));
        setInterval(() => {
            const resta = limite - (Date.now() - ultimo);
            if (resta <= 0) { document.getElementById('form-logout').submit(); return; }
            if (resta <= aviso) { caja.style.display = 'block'; seg.textContent = Math.ceil(resta / 1000); }
        }, 1000);
    })();

    // RNF008: las zonas marcadas con data-vivo se actualizan solas (camas, agenda, stock) cada pocos segundos,
    // sin recargar la página; se pausa si el usuario está escribiendo o tiene un formulario abierto.
    (function () {
        const zonas = () => document.querySelectorAll('[data-vivo]');
        if (!zonas().length) return;
        let sucio = false;
        document.addEventListener('input', e => { if (e.target.closest('[data-vivo]')) sucio = true; });
        document.addEventListener('submit', () => { sucio = true; });
        setInterval(async () => {
            if (document.hidden || sucio) return;
            const activo = document.activeElement;
            const ocupado = [...zonas()].some(z => (activo && activo !== document.body && z.contains(activo)
                && /INPUT|SELECT|TEXTAREA/.test(activo.tagName)) || z.querySelector('details[open]'));
            if (ocupado) return;
            try {
                const r = await fetch(location.href, { headers: { 'X-Vivo': '1' }, credentials: 'same-origin' });
                if (!r.ok || r.redirected) return;
                const nuevas = new DOMParser().parseFromString(await r.text(), 'text/html').querySelectorAll('[data-vivo]');
                zonas().forEach((z, i) => {
                    if (nuevas[i] && nuevas[i].innerHTML !== z.innerHTML) {
                        z.innerHTML = nuevas[i].innerHTML;
                        z.dispatchEvent(new CustomEvent('vivo:actualizado', { bubbles: true }));
                    }
                });
            } catch (e) { /* sin conexión: se reintenta en el próximo ciclo */ }
        }, {{ (int) config('cies.refresco_segundos', 5) }} * 1000);
    })();
    </script>
    @stack('scripts')
</body>
</html>
