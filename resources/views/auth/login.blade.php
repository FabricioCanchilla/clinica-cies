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

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; }
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
        
        @media(max-width: 1024px) {
            .features-bar { grid-template-columns: repeat(2, 1fr); }
            .nav-links { display: none; }
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

    <!-- BARRA SUPERIOR -->
    <header class="header">
        <div class="logo-container">
            <div class="logo-badge"><i class="fas fa-plus"></i></div>
            <div>
                <div class="logo-title">Clínica CIES</div>
                <div class="logo-subtitle">Salud Integral y Especializada</div>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="#inicio">Inicio</a></li>
            <li><a href="#nosotros">Nosotros</a></li>
            <li><a href="#servicios">Servicios</a></li>
            <li><a href="#investigacion">Investigación</a></li>
            <li><a href="#contacto">Contacto</a></li>
        </ul>

        <div class="header-actions">
            <div class="emergency-contact">
                <span class="emergency-label"><i class="fas fa-phone-alt"></i> 24/7 Urgencias</span>
                <a href="tel:160" class="emergency-number">+591 (2) 244-0000</a>
            </div>
            <button type="button" onclick="openLoginModal()" class="btn-login-nav"><i class="fas fa-user"></i> Iniciar Sesión</button>
            <a href="#servicios" class="btn-appointment">Agendar Cita</a>
        </div>
    </header>

    <!-- HERO PRINCIPAL -->
    <section class="hero" id="inicio">
        <div class="hero-content">
            <div class="hero-text">
                <span class="tag">Centro Médico Certificado</span>
                <h1>Tu Salud, <br><span>Nuestra Prioridad</span></h1>
                <p>Atención médica humanizada y tecnología de vanguardia. Cuidamos de tu bienestar para construir un mejor mañana.</p>
                
                <div class="hero-buttons">
                    <button type="button" onclick="openLoginModal()" class="btn-primary-action"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</button>
                    <a href="#servicios" class="btn-secondary-action"><i class="fas fa-stethoscope"></i> Ver Especialidades</a>
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
                    <h4>Servicios 24/7</h4>
                    <p>Atención continua de emergencias cuando lo necesites.</p>
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

    <!-- SECCIÓN: SERVICIOS Y ESPECIALIDADES -->
    <section class="section" id="servicios">
        <h2 class="section-title">Servicios y Especialidades Médicas</h2>
        <p class="section-subtitle">Catálogo completo para orientar tu atención y agendamiento de citas</p>

        <div class="cards-grid">
            <div class="info-card" id="nosotros">
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
    </section>

    <!-- SECCIÓN: INVESTIGACIÓN E IMPACTO SOCIAL -->
    <section class="section" id="investigacion" style="background: #f1f5f9; max-width: 100%; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div class="cards-grid">
                <div class="info-card" style="background: white;">
                    <span class="tag">Unidad Científica</span>
                    <h3>Investigación Clínica</h3>
                    <p>Desarrollamos proyectos de investigación médica y estudios epidemiológicos enfocados en elevar la calidad asistencial y los protocolos de salud pública.</p>
                </div>
                <div class="info-card" id="impacto" style="background: white;">
                    <span class="tag" style="background: rgba(16, 185, 129, 0.1); color: #059669;">Impacto Social</span>
                    <h3>Programas y Atención Solidaria</h3>
                    <p>Llevamos brigadas móviles y campañas de salud preventiva gratuita a comunidades y sectores vulnerables de la población.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="contacto">
        <div class="footer-content">
            <div>
                <h4>Clínica CIES - Sedes</h4>
                <p>Sede Central: Av. Principal #123</p>
                <p>Sucursal Norte: Av. Las Américas #45</p>
                <p><a href="#" style="color: var(--cies-green);">📍 Ver mapas y sucursales</a></p>
            </div>
            <div>
                <h4>Horarios de Atención</h4>
                <p>Lunes a Viernes: 07:30 - 20:00</p>
                <p>Sábados: 08:00 - 14:00</p>
                <p style="color: #f87171; font-weight: 600;">Emergencias: 24 Horas</p>
            </div>
            <div>
                <h4>Teléfonos de Contacto</h4>
                <p>Central: +591 (2) 244-0000</p>
                <p>Urgencias: 160</p>
                <p>Correo: contacto@cies.org.bo</p>
            </div>
            <div>
                <h4>Aviso de Privacidad</h4>
                <ul>
                    <li><a href="#">Tratamiento de Datos de Salud Sensibles</a></li>
                    <li><a href="#">Términos y Condiciones de Uso</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; {{ date('Y') }} Clínica CIES. Todos los derechos reservados.
        </div>
    </footer>

    <!-- MODAL DE INICIO DE SESIÓN -->
    <div class="modal" id="loginModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeLoginModal()">&times;</button>
            <h2 style="font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700;">Iniciar Sesión</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Accede a tus citas e historial médico institucional</p>

            @if ($errors->any())
                <div class="error-box">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <label>Correo, CI o Código de Paciente</label>
                <input type="text" name="usuario" value="{{ old('usuario') }}" placeholder="Ej. usuario@correo.com o CI" required autofocus>

                <label>Contraseña</label>
                <input type="password" name="contrasena" placeholder="••••••••" required>

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

    <!-- MODAL DE REGISTRO DE PACIENTE NUEVO -->
    <div class="modal" id="registerModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeRegisterModal()">&times;</button>
            <h2 style="font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700;">Crear Cuenta de Paciente</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Regístrate para gestionar tus citas médicas</p>

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <label>Nombre Completo</label>
                <input type="text" name="name" required placeholder="Ej. Juan Pérez">

                <label>Número de CI</label>
                <input type="text" name="ci" required placeholder="Ej. 1234567">

                <label>Correo Electrónico</label>
                <input type="email" name="email" required placeholder="correo@ejemplo.com">

                <label>Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••">

                <div style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 16px; font-size: 12px; color: #475569;">
                    <input type="checkbox" name="privacy_terms" required style="margin-top:2px;">
                    <span>Acepto el <strong>Aviso de Privacidad</strong> y autorizo el tratamiento confidencial de mis datos médicos sensibles.</span>
                </div>

                <button type="submit" class="btn-submit-main">Registrar Paciente</button>
            </form>
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
        function switchToRegister() {
            closeLoginModal();
            openRegisterModal();
        }
        window.onclick = function(event) {
            const loginModal = document.getElementById('loginModal');
            const registerModal = document.getElementById('registerModal');
            if (event.target === loginModal) { closeLoginModal(); }
            if (event.target === registerModal) { closeRegisterModal(); }
        }
    </script>
</body>
</html>