{{-- Estilos del portal público (portada y páginas institucionales) --}}
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
            --cies-green: #43704f; /* Verde principal */
            --cies-green-dark: #3b6449; /* Verde hover */
            --cies-green-light: #eef5f0; /* Fondo suave verde */
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
        .logo-container { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-img { height: 52px; width: auto; display: block; }
        .modal-logo { display: block; height: 46px; margin: 0 auto 14px; }
        .footer-logo { display: inline-block; background: #fff; border-radius: 10px; padding: 8px 12px; margin-bottom: 14px; }
        .footer-logo img { height: 40px; display: block; }
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
            box-shadow: 0 4px 12px rgba(76, 125, 95, 0.25);
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
            box-shadow: 0 10px 20px -5px rgba(76, 125, 95, 0.4);
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
        .modal-box .reg-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 14px; font-size: 14px; background: #f8fafc; color: #0f172a; }
        .modal-box input:focus { outline: none; border-color: var(--cies-green); background: #fff; box-shadow: 0 0 0 3px rgba(76, 125, 95, 0.15); }

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

    <style>
        /* ----- Páginas públicas (Nosotros, Servicios, Investigación, Ubicación, Contacto) ----- */
        .nav-links a.activo { color: var(--cies-green); }
        .pagina-hero { background: linear-gradient(120deg, #eef5f0, #fff 60%); border-bottom: 1px solid #e2e8f0; padding: 54px 24px 40px; }
        .pagina-hero > div { max-width: 1200px; margin: 0 auto; }
        .pagina-hero h1 { font-size: 38px; font-weight: 800; color: #0f172a; margin: 8px 0 10px; }
        .pagina-hero p { color: #475569; font-size: 17px; max-width: 760px; line-height: 1.6; }
        .migas { font-size: 13px; color: #64748b; } .migas a { color: var(--cies-green); text-decoration: none; }
        .cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin: 28px 0 0; }
        .cifra { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; border-top: 4px solid var(--cies-green); }
        .cifra:nth-child(even) { border-top-color: #cc783c; }
        .cifra b { display: block; font-size: 30px; color: #0f172a; line-height: 1.1; }
        .cifra span { font-size: 13px; color: #64748b; }
        .bloque { max-width: 1200px; margin: 0 auto; padding: 48px 24px; }
        .bloque h2 { font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .bloque > p.lead { color: #64748b; margin-bottom: 26px; max-width: 760px; line-height: 1.6; }
        .bloque.gris { max-width: none; background: #f1f5f9; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .bloque.gris > * { max-width: 1152px; margin-left: auto; margin-right: auto; }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .tarjeta { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .tarjeta h3 { font-size: 17px; color: #0f172a; margin-bottom: 8px; }
        .tarjeta p, .tarjeta li { color: #475569; font-size: 14px; line-height: 1.6; }
        .tarjeta ul { padding-left: 18px; }
        .tarjeta .ico { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
                        background: var(--cies-green-light); color: var(--cies-green); font-size: 19px; margin-bottom: 12px; }
        .tarjeta:nth-child(even) .ico { background: #fbf1e9; color: #cc783c; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip { background: #fff; border: 1px solid #c9ddd0; color: #2d4c39; border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 600; }
        .esp { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .esp-cab { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .esp h3 { font-size: 17px; color: #0f172a; }
        .precio { background: var(--cies-green-light); color: #2d4c39; font-weight: 800; border-radius: 10px; padding: 6px 10px; font-size: 14px; white-space: nowrap; }
        .medico { border-top: 1px dashed #e2e8f0; padding-top: 10px; font-size: 14px; color: #334155; }
        .medico small { color: #64748b; display: block; margin-top: 2px; }
        .dias { display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap; }
        .dia { font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: #f1f5f9; color: #94a3b8; }
        .dia.si { background: #dcebe1; color: #2d4c39; }
        .tabla-pub { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 14px; }
        .tabla-pub th { background: #eef5f0; color: #2d4c39; text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .tabla-pub td { padding: 12px; border-top: 1px solid #e2e8f0; color: #334155; vertical-align: top; }
        .tabla-pub .der { text-align: right; white-space: nowrap; font-weight: 700; }
        .barra-h { display: flex; align-items: center; gap: 10px; font-size: 14px; margin: 8px 0; }
        .barra-h .et { width: 42%; color: #334155; }
        .barra-h .fondo { flex: 1; height: 12px; background: #eef5f0; border-radius: 99px; overflow: hidden; }
        .barra-h .fondo span { display: block; height: 100%; background: var(--cies-green); border-radius: 99px; }
        .barra-h .n { width: 64px; text-align: right; color: #64748b; font-weight: 600; }
        .sede { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px; display: flex; gap: 14px; }
        .sede.destacada { border: 2px solid var(--cies-green); }
        .sede i.fa-location-dot { color: #cc783c; font-size: 20px; margin-top: 3px; }
        .sede h4 { font-size: 15px; color: #0f172a; margin-bottom: 4px; }
        .sede p { font-size: 13px; color: #475569; line-height: 1.5; }
        .cta { background: linear-gradient(120deg, #3b6449, #4c7d5f); color: #fff; border-radius: 20px; padding: 32px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .cta h3 { font-size: 22px; } .cta p { opacity: .9; margin-top: 4px; }
        .cta a { background: #fff; color: #2d4c39; padding: 12px 22px; border-radius: 10px; font-weight: 800; text-decoration: none; }
        .contacto-item { display: flex; gap: 14px; align-items: flex-start; padding: 14px 0; border-bottom: 1px solid #e2e8f0; }
        .contacto-item:last-child { border-bottom: 0; }
        .contacto-item i { width: 40px; height: 40px; border-radius: 10px; background: var(--cies-green-light); color: var(--cies-green); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .contacto-item b { display: block; color: #0f172a; } .contacto-item a, .contacto-item span { color: #475569; text-decoration: none; font-size: 14px; }
        .redes { display: flex; gap: 10px; flex-wrap: wrap; }
        .redes a { display: inline-flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 12px; color: #334155; text-decoration: none; font-size: 13px; font-weight: 600; }
        .redes a i { color: var(--cies-green); font-size: 16px; }
        .ver-mas { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; color: var(--cies-green); font-weight: 700; text-decoration: none; font-size: 14px; }
        .aviso-acceso { background: #fbf1e9; border: 1px solid #f1d3bd; color: #7a4219; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
        @media (max-width: 1024px) { .nav-links { display: none; } }
        @media (max-width: 640px) { .pagina-hero h1 { font-size: 28px; } .barra-h .et { width: 50%; } .header-actions .emergency-contact { display: none; } }
    </style>
