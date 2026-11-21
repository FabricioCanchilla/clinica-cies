<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica CIES — Portal Institucional y Acceso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ----- PALETA DE COLORES CIES (VERDE) ----- */
        :root {
            --cies-green: #059669; /* Verde principal */
            --cies-green-dark: #047857; /* Verde hover */
            --cies-green-light: #ecfdf5; /* Fondo suave verde */
            --cies-text: #0f172a;
            --cies-text-muted: #64748b;
        }

        /* ----- BARRA SUPERIOR (HEADER) ----- */
        .header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo-container { display: flex; align-items: center; gap: 10px; }
        .logo-badge { 
            background: var(--cies-green); 
            color: #ffffff; 
            font-weight: 800; 
            padding: 8px 12px; 
            border-radius: 8px; 
            font-size: 16px; 
        }
        .logo-title { font-size: 18px; font-weight: 700; color: #0f172a; line-height: 1.1; }
        .logo-subtitle { font-size: 11px; color: #64748b; font-weight: 500; }
        
        .nav-links { display: flex; gap: 28px; list-style: none; }
        .nav-links a { color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--cies-green); }

        .header-actions { display: flex; align-items: center; gap: 16px; }
        
        .emergency-contact { display: flex; flex-direction: column; text-align: right; }
        .emergency-label { font-size: 11px; color: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 4px; justify-content: flex-end; }
        .emergency-number { font-size: 14px; color: #0f172a; font-weight: 700; text-decoration: none; }
        
        /* Botones superiores */
        .btn-appointment {
            background: var(--cies-green);
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            transition: background 0.2s;
        }
        .btn-appointment:hover { background: var(--cies-green-dark); }

        .btn-login-nav {
            background: #0f172a;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login-nav:hover { background: #334155; }

        /* ----- HERO SECTION (CORREGIDO PARA LA IMAGEN) ----- */
        .hero {
            position: relative;
            min-height: 85vh;
            /* Ajuste: El gradiente va de blanco a transparente para que la imagen se vea a la derecha */
            background: linear-gradient(90deg, rgba(255,255,255,1) 0%, rgba(255,255,255,0.9) 45%, rgba(255,255,255,0.2) 70%, rgba(255,255,255,0) 100%), 
                url("/images/clinica-cies.png") no-repeat right center/cover;
            display: flex;
            align-items: center;
            padding: 140px 40px 80px;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-text {
            max-width: 550px;
        }

        .hero-text h1 {
            font-size: 56px;
            line-height: 1.1;
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 20px;
        }
        .hero-text h1 span { color: var(--cies-green); }
        .hero-text p {
            font-size: 18px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .hero-buttons { display: flex; gap: 16px; align-items: center; }
        
        .btn-primary-action {
            background: var(--cies-green);
            color: white;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px -5px rgba(5, 150, 105, 0.4);
            transition: transform 0.2s, background 0.2s;
        }
        .btn-primary-action:hover { 
            transform: translateY(-2px); 
            background: var(--cies-green-dark);
        }

        .btn-secondary-action {
            background: #ffffff;
            color: #0f172a;
            padding: 14px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #cbd5e1;
            transition: background 0.2s, border-color 0.2s;
        }
        .btn-secondary-action:hover { 
            background: #f8fafc; 
            border-color: var(--cies-green);
        }

        /* ----- BARRA FLOTANTE DE CARACTERÍSTICAS ----- */
        .features-bar-container {
            max-width: 1200px;
            margin: -50px auto 60px;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }
        .features-bar {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            padding: 28px;
            gap: 20px;
            border: 1px solid #e2e8f0;
        }
        .feature-item { display: flex; gap: 16px; align-items: flex-start; }
        .feature-icon {
            font-size: 24px;
            color: var(--cies-green);
            background: var(--cies-green-light);
            padding: 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feature-texts h4 { font-size: 15px; color: #0f172a; font-weight: 700; margin-bottom: 2px; }
        .feature-texts p { font-size: 12px; color: #64748b; line-height: 1.4; }

        /* ----- SECCIONES GENERALES ----- */
        .section { max-width: 1200px; margin: 0 auto; padding: 60px 24px; }
        .section-title { font-size: 28px; font-weight: 800; color: #0f172a; text-align: center; margin-bottom: 8px; }
        .section-subtitle { text-align: center; color: #64748b; font-size: 15px; margin-bottom: 40px; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 24px; }
        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .info-card h3 { font-size: 18px; color: #0f172a; font-weight: 700; margin-bottom: 10px; }
        .info-card p { font-size: 14px; color: #64748b; line-height: 1.6; }

        .tag { display: inline-block; font-size: 11px; font-weight: 700; color: var(--cies-green); background: var(--cies-green-light); padding: 4px 10px; border-radius: 6px; margin-bottom: 12px; text-transform: uppercase; }

        /* ----- FOOTER ----- */
        footer { background: #0f172a; color: #94a3b8; padding: 60px 24px 24px; margin-top: 60px; font-size: 14px; }
        .footer-content { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 40px; margin-bottom: 40px; }
        .footer-content h4 { color: white; font-size: 16px; font-weight: 700; margin-bottom: 16px; }
        .footer-content p, .footer-content li { margin-bottom: 10px; list-style: none; }
        .footer-content a { color: #94a3b8; text-decoration: none; }
        .footer-content a:hover { color: #ffffff; }
        .footer-bottom { text-align: center; border-top: 1px solid #1e293b; padding-top: 24px; font-size: 13px; color: #64748b; }

        /* ----- MODALES ----- */
        .modal { display: none; position: fixed; inset: 0; z-index: 200; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 16px; }
        .modal-box { background: white; border-radius: 20px; width: 100%; max-width: 440px; padding: 36px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .close-btn { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b; }

        .modal-box label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .modal-box input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 14px; font-size: 14px; background: #f8fafc; color: #0f172a; }
        .modal-box .reg-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 14px; font-size: 14px; background: #f8fafc; color: #0f172a; }
        .modal-box input:focus { outline: none; border-color: var(--cies-green); background: #fff; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15); }

        .btn-submit-main {
            width: 100%;
            padding: 12px;
            background: var(--cies-green);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit-main:hover { background: var(--cies-green-dark); }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 14px;
        }
        
        .section a { color: var(--cies-green-dark); } .info-card p + p { margin-top: 14px; }
        .nav-links a[aria-current="page"] { color: var(--cies-green-dark); font-weight: 700; }
        @media(max-width: 1024px) {
            .features-bar { grid-template-columns: repeat(2, 1fr); }
            .header { position: relative; flex-wrap: wrap; gap: 12px; padding: 16px 20px; } .nav-links { display: flex; order: 3; width: 100%; overflow-x: auto; gap: 20px; padding: 8px 0; } .hero { padding-top: 70px; } .section[id] { padding-top: 48px !important; }
        }
        @media(max-width: 640px) {
            .features-bar { grid-template-columns: 1fr; }
            .hero-text h1 { font-size: 40px; }
            .hero {
                background: linear-gradient(90deg, rgba(255,255,255,1) 0%, rgba(255,255,255,0.9) 60%, rgba(255,255,255,0.6) 100%), 
                url("/images/clinica-cies.png") no-repeat center center/cover;
            }
        }
    </style>
</head>
<body>
    @php
        $seccionesPublicas = ['inicio', 'nosotros', 'servicios', 'investigacion', 'ubicacion', 'contacto'];
        $paginaPublica = request('seccion', 'inicio');
        if (!in_array($paginaPublica, $seccionesPublicas, true)) { $paginaPublica = 'inicio'; }
    @endphp

    <!-- BARRA SUPERIOR -->
    <header class="header">
        <div class="logo-container">
            <x-brand-logo :width="170" />
        </div>

        <ul class="nav-links">
            <li><a href="{{ route('login', ['seccion' => 'inicio']) }}" @if($paginaPublica === 'inicio') aria-current="page" @endif>Inicio</a></li>
            <li><a href="{{ route('login', ['seccion' => 'nosotros']) }}" @if($paginaPublica === 'nosotros') aria-current="page" @endif>Nosotros</a></li>
            <li><a href="{{ route('login', ['seccion' => 'servicios']) }}" @if($paginaPublica === 'servicios') aria-current="page" @endif>Servicios</a></li>
            <li><a href="{{ route('login', ['seccion' => 'investigacion']) }}" @if($paginaPublica === 'investigacion') aria-current="page" @endif>Investigación</a></li>
            <li><a href="{{ route('login', ['seccion' => 'ubicacion']) }}" @if($paginaPublica === 'ubicacion') aria-current="page" @endif>Ubicación</a></li>
            <li><a href="{{ route('login', ['seccion' => 'contacto']) }}" @if($paginaPublica === 'contacto') aria-current="page" @endif>Contacto</a></li>
        </ul>

        <div class="header-actions">
            <button type="button" onclick="openLoginModal()" class="btn-login-nav"><i class="fas fa-user"></i> Iniciar Sesión</button>
            <button type="button" onclick="openLoginModal()" class="btn-appointment" style="border:0;cursor:pointer">Agendar cita</button>
        </div>
    </header>

@if ($paginaPublica === 'inicio')
    <!-- HERO PRINCIPAL -->
    <section class="hero" id="inicio">
        <div class="hero-content">
            <div class="hero-text">
                <span class="tag">Salud integral</span>
                <h1>Tu Salud, <br><span>Nuestra Prioridad</span></h1>
                <p>Consulta tus citas y encuentra información para acompañar el cuidado de tu salud.</p>
                
                <div class="hero-buttons">
                    <button type="button" onclick="openLoginModal()" class="btn-primary-action"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</button>
                    <a href="{{ route('login', ['seccion' => 'servicios']) }}" class="btn-secondary-action"><i class="fas fa-stethoscope"></i> Ver Especialidades</a>
                </div>
            </div>
        </div>
    </section>

    <!-- BARRA FLOTANTE DE CARACTERÍSTICAS -->
    <div class="features-bar-container">
        <div class="features-bar">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-user-md"></i></div>
                <div class="feature-texts">
                    <h4>Especialistas Expertos</h4>
                    <p>Profesionales altamente calificados y con experiencia.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="feature-texts">
                    <h4>Cuidado Avanzado</h4>
                    <p>Tecnología de última generación para diagnósticos precisos.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-texts">
                    <h4>Consulta tus citas</h4>
                    <p>Revisa tus reservas y su estado desde tu cuenta.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-hands-helping"></i></div>
                <div class="feature-texts">
                    <h4>Paciente Primero</h4>
                    <p>Enfoque humano y compasivo centrado en tu persona.</p>
                </div>
            </div>
        </div>
    </div>

@endif
@if ($paginaPublica === 'nosotros')
    <!-- SECCIÓN: NOSOTROS (RF001: reseña y misión) -->
    <section class="section" id="nosotros" style="padding-top:145px; min-height:65vh; width:100%">
        <h2 class="section-title">Quiénes Somos</h2>
        <p class="section-subtitle">Centro de Investigación, Educación y Servicios</p>
        <div class="cards-grid">
            <div class="info-card">
                <span class="tag">Reseña</span>
                <h3>Clínica CIES</h3>
                <p>El CIES es una institución privada sin fines de lucro enfocada en el desarrollo social y la salud integral en Bolivia, con una reconocida trayectoria en salud sexual y reproductiva. Su red nacional cuenta con clínicas y policonsultorios en el país, incluida la ciudad de La Paz.</p>
            </div>
            <div class="info-card">
                <span class="tag">Misión</span>
                <h3>Salud integral para la comunidad</h3>
                <p>Brindar servicios de salud integral, humanizados y de calidad, con atención en consulta externa, laboratorio, farmacia e internación, coordinando de forma segura y trazable el trabajo de todo el personal en beneficio del paciente.</p>
            </div>
            <div class="info-card"><span class="tag">Nuestro enfoque</span><h3>Una atención centrada en ti</h3><p>La orientación, la confidencialidad y el seguimiento son parte de una atención que respeta las necesidades de cada persona.</p></div>
        </div>
    </section>

@endif
@if ($paginaPublica === 'servicios')
    <!-- SECCIÓN: SERVICIOS Y ESPECIALIDADES (RF001) -->
    <section class="section" id="servicios" style="padding-top:145px; min-height:65vh; width:100%">
        <h2 class="section-title">Servicios y Especialidades Médicas</h2>
        <p class="section-subtitle">Consulta general, orientación y servicios de apoyo para tu atención</p>

        <div class="cards-grid">
            <div class="info-card">
                <h3>Medicina General e Integral</h3>
                <p>Valoración primaria, control preventivo de salud y seguimiento médico oportuno para toda la familia.</p>
            </div>
            <div class="info-card">
                <h3>Salud Sexual y Reproductiva</h3>
                <p>Orientación especializada, ginecología, urología y planificación con absoluta confidencialidad.</p>
            </div>
            <div class="info-card">
                <h3>Laboratorio e Imagenología</h3>
                <p>Análisis clínicos rápidos, ecografías y pruebas especializadas con máxima confiabilidad.</p>
            </div>
        </div>

        <div class="info-card" style="margin-top:24px"><span class="tag">Cómo empezar</span><h3>De la consulta al seguimiento</h3><p>La consulta general es el primer paso para la atención. Cuando el médico indique una derivación, podrás solicitar la especialidad correspondiente. Ingresa al portal para revisar tus citas y resultados publicados.</p></div>
        @if (!empty($especialidades) && count($especialidades))
            <h3 style="text-align:center; margin: 32px 0 14px; font-size: 18px; color:#0f172a;">Especialidades registradas</h3>
            <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">
                @foreach ($especialidades as $esp)
                    <span class="tag" style="font-size:13px; padding:8px 14px;">{{ $esp->DES_ESP }}</span>
                @endforeach
            </div>
        @endif
    </section>

@endif
@if ($paginaPublica === 'investigacion')
    <!-- SECCIÓN: INVESTIGACIÓN E IMPACTO SOCIAL -->
    <section class="section" id="investigacion" style="padding-top:145px; min-height:65vh; background: #f1f5f9; max-width: 100%; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div class="cards-grid">
                <div class="info-card" style="background: white;">
                    <span class="tag">Unidad Científica</span>
                    <h3>Investigación Clínica</h3>
                    <p>La investigación y la educación en salud contribuyen a comprender las necesidades de la población y a fortalecer la prevención y la atención informada.</p>
                </div>
                <div class="info-card" id="impacto" style="background: white;">
                    <span class="tag" style="background: rgba(16, 185, 129, 0.1); color: #059669;">Impacto Social</span>
                    <h3>Programas y Atención Solidaria</h3>
                    <p>La orientación y la educación ayudan a las personas a conocer sus opciones de cuidado y tomar decisiones informadas sobre su salud.</p>
                </div>
            </div>
        </div>
    </section>

@endif
@if ($paginaPublica === 'ubicacion')
    <!-- SECCIÓN: UBICACIÓN (RF002 / RF004: mapa interactivo sin iniciar sesión) -->
    <section class="section" id="ubicacion" style="padding-top:145px; min-height:65vh; width:100%">
        <h1 class="section-title">Ubicación</h1>
        <p class="section-subtitle">Planifica tu visita con información confirmada.</p>
        <div class="cards-grid"><article class="info-card"><span class="tag">Antes de acudir</span><h3>Confirma tu sede</h3><p>Consulta con la clínica la dirección y el horario vigentes de la sede donde recibirás atención.</p></article><article class="info-card"><span class="tag">Tu cita</span><h3>Revisa los datos de tu reserva</h3><p>Ingresa al portal del paciente para consultar la fecha, la hora y el profesional asignado.</p><button type="button" onclick="openLoginModal()" class="btn-login-nav" style="margin-top:16px">Iniciar sesión</button></article></div>
    </section>

@endif
    @if ($paginaPublica === 'contacto')
    <section class="section" style="padding-top:145px;min-height:65vh;width:100%">
        <h1 class="section-title">Contacto y orientación</h1>
        <p class="section-subtitle">Encuentra orientación para tus consultas y gestiones.</p>
        <div class="cards-grid">
            <article class="info-card"><span class="tag">Atención</span><h3>Consultas y citas</h3><p>Ingresa a tu cuenta para consultar horarios, solicitar una cita y revisar el estado de tus reservas. Si necesitas actualizar tus datos, acude a recepción.</p></article>
            <article class="info-card"><span class="tag">Resultados</span><h3>Tu información de salud</h3><p>En el portal del paciente puedes consultar recetas, resultados publicados y comprobantes disponibles para tu cuenta.</p></article>
            <article class="info-card"><span class="tag">Ubicación</span><h3>Antes de tu visita</h3><p>Confirma con la clínica la dirección, los horarios y los medios de contacto vigentes antes de acudir.</p><p><a href="{{ route('login', ['seccion' => 'ubicacion']) }}">Ver información de ubicación →</a></p></article>
        </div>
    </section>
    @endif
    <footer>
        <div class="footer-content">
            <div><h4>Clínica CIES</h4><p>Información y servicios para tu atención integral.</p></div>
            <div><h4>Explora</h4><p><a href="{{ route('login', ['seccion' => 'servicios']) }}">Servicios</a></p><p><a href="{{ route('login', ['seccion' => 'contacto']) }}">Contacto</a></p></div>
            <div><h4>Privacidad</h4><p><a href="#" onclick="openPrivacyModal(); return false;">Aviso de privacidad y términos</a></p></div>
        </div>
        <div class="footer-bottom">&copy; {{ date('Y') }} Clínica CIES.</div>
    </footer>

    <!-- MODAL DE INICIO DE SESIÓN -->
    <div class="modal" id="loginModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeLoginModal()">&times;</button>
            <h2 style="font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700;">Iniciar Sesión</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Accede a tus citas e historial médico institucional</p>

            @if (session('ok'))
                <div class="error-box" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0;">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <label>Correo, CI o Código de Paciente</label>
                <input type="text" name="usuario" value="{{ old('usuario') }}" placeholder="Ej. usuario@correo.com o CI" required autofocus>

                <label>Contraseña</label>
                <div style="position:relative"><input id="contrasena-login" type="password" name="contrasena" autocomplete="current-password" style="padding-right:48px" required><button type="button" id="alternar-contrasena" aria-label="Mostrar contraseña" title="Mostrar contraseña" aria-controls="contrasena-login" aria-pressed="false" style="position:absolute;right:5px;top:50%;transform:translateY(-50%);cursor:pointer;padding:8px;border:0;background:transparent;color:#64748b"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path id="ojo-tachado" d="M3 3 21 21" style="display:none"/></svg></button></div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 12px;">
                    <a href="{{ route('password.request') }}" style="color: var(--cies-green); text-decoration: none;">¿Olvidaste tu contraseña?</a>
                    <a href="#" style="color: var(--cies-green); text-decoration: none;">¿Olvidaste tu usuario?</a>
                </div>

                <button type="submit" class="btn-submit-main">Ingresar al Sistema</button>
            </form>

            <hr style="border:0; border-top:1px solid #e2e8f0; margin:18px 0;">

            <div style="text-align: center;">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 6px;">¿Eres paciente nuevo?</p>
                <button type="button" onclick="switchToRegister()" style="background: #f1f5f9; color: var(--cies-green); border: 1px solid #cbd5e1; padding: 10px; width: 100%; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">Crear Cuenta (Registro de Paciente)</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE REGISTRO DE PACIENTE NUEVO (RF003 / RN07) -->
    @php $rerr = $errors->getBag('registro'); @endphp
    <div class="modal" id="registerModal">
        <div class="modal-box" style="max-width: 620px; max-height: 92vh; overflow-y: auto;">
            <button class="close-btn" onclick="closeRegisterModal()">&times;</button>
            <h2 style="font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700;">Crear Cuenta de Paciente</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Regístrate para gestionar tus citas médicas. Tu usuario será tu número de CI.</p>

            @if ($rerr->any())
                <div class="error-box">{{ $rerr->first() }}</div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0 14px;">
                    <div><label>Número de CI</label>
                        <input type="text" name="ci" value="{{ old('ci') }}" required maxlength="10" inputmode="numeric" placeholder="Ej. 1234567"></div>
                    <div><label>Fecha de nacimiento</label>
                        <input type="date" name="fecnac" value="{{ old('fecnac') }}" required max="{{ now()->subDay()->toDateString() }}"></div>
                    <div><label>Nombres</label>
                        <input type="text" name="nombres" value="{{ old('nombres') }}" required maxlength="40" placeholder="Ej. Juan Carlos"></div>
                    <div><label>Apellidos</label>
                        <input type="text" name="apellidos" value="{{ old('apellidos') }}" required maxlength="40" placeholder="Ej. Pérez Mamani"></div>
                    <div><label>Sexo</label>
                        <select name="sexo" required class="reg-select">
                            <option value="">Selecciona</option>
                            <option value="F" @selected(old('sexo')==='F')>Femenino</option>
                            <option value="M" @selected(old('sexo')==='M')>Masculino</option>
                        </select></div>
                    <div><label>Estado civil</label>
                        <select name="estciv" required class="reg-select">
                            <option value="">Selecciona</option>
                            @foreach (['Soltero','Casado','Divorciado','Viudo','Concubino'] as $ec)
                                <option value="{{ $ec }}" @selected(old('estciv')===$ec)>{{ $ec }}</option>
                            @endforeach
                        </select></div>
                    <div><label>Teléfono / celular</label>
                        <input type="text" name="telefono" value="{{ old('telefono') }}" required maxlength="8" inputmode="numeric" placeholder="Ej. 71234567"></div>
                    <div><label>Correo electrónico</label>
                        <input type="email" name="correo" value="{{ old('correo') }}" required maxlength="60" placeholder="correo@ejemplo.com"></div>
                    <div style="grid-column: 1 / -1;"><label>Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion') }}" required maxlength="100" placeholder="Zona, calle y número"></div>
                    <div><label>Contraseña</label>
                        <input type="password" name="clave" required minlength="8" placeholder="Mínimo 8 caracteres"></div>
                    <div><label>Confirmar contraseña</label>
                        <input type="password" name="clave_confirmation" required minlength="8"></div>
                </div>

                <div style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; font-size: 12px; color: #475569;">
                    <input type="checkbox" name="privacidad" value="1" required style="width:auto; margin-top:2px;" @checked(old('privacidad'))>
                    <span>Acepto el <a href="#" onclick="openPrivacyModal(); return false;" style="color: var(--cies-green); font-weight:600;">Aviso de Privacidad</a> y autorizo el tratamiento confidencial de mis datos de salud.</span>
                </div>
                <div style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 16px; font-size: 12px; color: #475569;">
                    <input type="checkbox" name="terminos" value="1" required style="width:auto; margin-top:2px;" @checked(old('terminos'))>
                    <span>Acepto los <a href="#" onclick="openPrivacyModal(); return false;" style="color: var(--cies-green); font-weight:600;">Términos y Condiciones de Uso</a>.</span>
                </div>

                <button type="submit" class="btn-submit-main">Registrar Paciente</button>
            </form>
        </div>
    </div>

    <!-- AVISO DE PRIVACIDAD Y TÉRMINOS DE USO -->
    <div class="modal" id="privacyModal" style="z-index: 300;">
        <div class="modal-box" style="max-width: 560px; max-height: 85vh; overflow-y: auto; font-size: 13px; color: #334155; line-height: 1.6;">
            <button class="close-btn" onclick="closePrivacyModal()">&times;</button>
            <h2 style="font-size: 20px; color: #0f172a; margin-bottom: 10px; font-weight: 700;">Aviso de Privacidad y Términos de Uso</h2>
            <p><strong>Tratamiento de datos de salud.</strong> La Clínica CIES registra tus datos personales y de salud solo para la atención médica, el agendamiento de citas, los cobros y las notificaciones del servicio. Los datos clínicos son confidenciales y solo los consulta el personal autorizado según su rol.</p>
            <p style="margin-top:8px;"><strong>Trazabilidad.</strong> Los accesos a tu expediente y las operaciones sobre tu cuenta quedan registrados en un historial de auditoría de solo lectura.</p>
            <p style="margin-top:8px;"><strong>Verificación.</strong> Los pagos y las entregas de insumos se confirman con firma digital y un código OTP enviado a tu correo.</p>
            <p style="margin-top:8px;"><strong>Uso de la cuenta.</strong> Tu cuenta es personal. Eres responsable de mantener tu contraseña en reserva. La sesión se cierra tras 15 minutos de inactividad.</p>
            <button type="button" class="btn-submit-main" style="margin-top:16px;" onclick="closePrivacyModal()">Entendido</button>
        </div>
    </div>

    <script>
        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
        }
        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }
        function openRegisterModal() {
            document.getElementById('registerModal').style.display = 'flex';
        }
        function closeRegisterModal() {
            document.getElementById('registerModal').style.display = 'none';
        }
        function openPrivacyModal() { document.getElementById('privacyModal').style.display = 'flex'; }
        function closePrivacyModal() { document.getElementById('privacyModal').style.display = 'none'; }
        function switchToRegister() {
            closeLoginModal();
            openRegisterModal();
        }
        window.onclick = function(event) {
            const loginModal = document.getElementById('loginModal');
            const registerModal = document.getElementById('registerModal');
            if (event.target === loginModal) { closeLoginModal(); }
            if (event.target === registerModal) { closeRegisterModal(); }
            if (event.target === document.getElementById('privacyModal')) { closePrivacyModal(); }
        }

        // Si el servidor devolvió errores o un aviso, se reabre el modal que corresponde
        @if ($errors->getBag('registro')->any())
            openRegisterModal();
        @elseif ($errors->any() || session('ok'))
            openLoginModal();
        @endif
    </script>
<script>
const botonClave = document.getElementById('alternar-contrasena');
botonClave.addEventListener('click', () => { const campo = document.getElementById('contrasena-login'); const visible = campo.type === 'password'; campo.type = visible ? 'text' : 'password'; botonClave.setAttribute('aria-pressed', String(visible)); botonClave.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña'); botonClave.title = visible ? 'Ocultar contraseña' : 'Mostrar contraseña'; document.getElementById('ojo-tachado').style.display = visible ? '' : 'none'; });
</script></body>
</html>
