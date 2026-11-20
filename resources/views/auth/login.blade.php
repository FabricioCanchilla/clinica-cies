<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica CIES — Portal Institucional y Acceso</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('publico._estilos')
</head>
<body>

    @include('publico._cabecera', ['portada' => true])

    @php $c = config('clinica'); $bs = fn ($n) => 'Bs ' . number_format((float) $n, 0, ',', '.'); @endphp
    <!-- HERO PRINCIPAL -->
    <section class="hero" id="inicio">
        <div class="hero-content">
            <div class="hero-text">
                <span class="tag">Salud sexual · Salud reproductiva · Salud integral</span>
                <h1>Tu salud, <br><span>{{ $c['lema'] }}</span></h1>
                <p>{{ $c['sede']['nombre'] }} es parte de la red nacional de CIES: {{ $c['red_nacional']['clinicas'] }} clínicas y {{ $c['red_nacional']['policonsultorios'] }} policonsultorios en los {{ $c['red_nacional']['departamentos'] }} departamentos de Bolivia. Agenda tu consulta en línea y paga solo el anticipo.</p>

                <div class="hero-buttons">
                    <a href="{{ route('agendar') }}" class="btn-primary-action" style="text-decoration: none;"><i class="fas fa-calendar-plus"></i> Agendar cita</a>
                    <a href="{{ route('publico.servicios') }}" class="btn-secondary-action"><i class="fas fa-stethoscope"></i> Ver especialidades y tarifas</a>
                </div>
            </div>
        </div>
    </section>

    <!-- BARRA FLOTANTE: compromisos de CIES -->
    <div class="features-bar-container">
        <div class="features-bar">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-user-md"></i></div>
                <div class="feature-texts">
                    <h4>{{ $cifras['medicos'] }} especialistas</h4>
                    <p>{{ $cifras['especialidades'] }} especialidades con agenda en línea.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-user-shield"></i></div>
                <div class="feature-texts">
                    <h4>Confidencialidad</h4>
                    <p>Tu historia clínica solo la ve quien te atiende.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-texts">
                    <h4>Emergencias 24 h</h4>
                    <p>Clínica CIES La Paz · {{ $c['clinica_la_paz']['telefono'] }}</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-hand-holding-dollar"></i></div>
                <div class="feature-texts">
                    <h4>Precios accesibles</h4>
                    <p>Atención con calidad y calidez.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN: NOSOTROS (RF001: reseña y misión) -->
    <section class="section" id="nosotros">
        <h2 class="section-title">Quiénes Somos</h2>
        <p class="section-subtitle">{{ $c['razon'] }}</p>
        <div class="cards-grid">
            <div class="info-card">
                <span class="tag">Historia</span>
                <h3>Nacimos de los Consultorios Populares de Salud</h3>
                <p>Un grupo de mujeres comprometidas con los derechos y la salud sexual y salud reproductiva inició CIES en La Paz, El Alto, Oruro y Potosí. Hoy la red llega a los {{ $c['red_nacional']['departamentos'] }} departamentos, con {{ $c['red_nacional']['umosas'] }} unidades móviles que recorren más de {{ $c['red_nacional']['comunidades'] }} comunidades rurales.</p>
                <a class="ver-mas" href="{{ route('publico.nosotros') }}">Conoce nuestra historia <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="info-card">
                <span class="tag">Misión</span>
                <h3>Derechos y salud integral</h3>
                <p>{{ $c['mision'] }}</p>
                <a class="ver-mas" href="{{ route('publico.nosotros') }}">Misión, visión y valores <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- SECCIÓN: SERVICIOS Y ESPECIALIDADES (RF001) -->
    <section class="section" id="servicios" style="padding-top: 20px;">
        <h2 class="section-title">Especialidades en {{ $c['sede']['nombre'] }}</h2>
        <p class="section-subtitle">Precio de la consulta y número de especialistas. Agenda en línea con un anticipo del {{ (int) config('cies.anticipo_porcentaje') }} %.</p>
        <div class="rejilla">
            @foreach ($especialidades as $e)
                <div class="esp" style="padding: 16px;">
                    <div class="esp-cab">
                        <h3 style="font-size: 15px;">{{ $e->DES_ESP }}</h3>
                        @if ($e->PRECIO)<span class="precio">{{ $bs($e->PRECIO) }}</span>@endif
                    </div>
                    <small style="color: #64748b;"><i class="fas fa-user-doctor"></i> {{ $e->MEDICOS->count() }} {{ $e->MEDICOS->count() === 1 ? 'especialista' : 'especialistas' }}
                        @if ($e->MEDICOS->isNotEmpty()) · {{ $e->MEDICOS->map(fn ($m) => $m->TIT_EMP . ' ' . $m->APE_EMP)->implode(', ') }} @endif</small>
                </div>
            @endforeach
        </div>
        <p style="text-align: center;"><a class="ver-mas" href="{{ route('publico.servicios') }}">Ver médicos, horarios, laboratorio e imagenología <i class="fas fa-arrow-right"></i></a></p>
    </section>

    <!-- SECCIÓN: INVESTIGACIÓN E IMPACTO SOCIAL -->
    <section class="section" id="investigacion" style="background: #f1f5f9; max-width: 100%; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 class="section-title">Investigación e impacto social</h2>
            <p class="section-subtitle">{{ count($c['proyectos']) }} proyectos vigentes con instituciones aliadas</p>
            <div class="cards-grid">
                @foreach (array_slice($c['proyectos'], 0, 3) as [$inst, $proy, $ubi, $pub])
                    <div class="info-card" style="background: white;">
                        <span class="tag">{{ $inst }}</span>
                        <h3>{{ $proy }}</h3>
                        <p><i class="fas fa-location-dot" style="color: #cc783c;"></i> {{ $ubi }}<br><i class="fas fa-users" style="color: var(--cies-green);"></i> {{ $pub }}</p>
                    </div>
                @endforeach
            </div>
            <p style="text-align: center;"><a class="ver-mas" href="{{ route('publico.investigacion') }}">Ver todos los proyectos y el perfil de atención <i class="fas fa-arrow-right"></i></a></p>
        </div>
    </section>

    <!-- SECCIÓN: UBICACIÓN (RF002 / RF004: mapa sin iniciar sesión) -->
    <section class="section" id="ubicacion">
        <h2 class="section-title">Ubicación y contacto</h2>
        <p class="section-subtitle">{{ $c['sede']['direccion'] }} · Tel. {{ $c['sede']['telefono'] }} · Cel. {{ $c['sede']['celular'] }}</p>
        <div style="border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,.06);">
            <iframe title="Mapa de {{ $c['sede']['nombre'] }}" loading="lazy" style="width:100%; height:360px; border:0; display:block;"
                src="{{ $c['sede']['mapa'] }}" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <p style="text-align:center; margin-top:10px; font-size:13px;">
            <a href="{{ route('publico.ubicacion') }}" style="color: var(--cies-green); font-weight:600;">Ver los {{ count($c['red_la_paz']) }} puntos de atención de la red en La Paz y El Alto</a>
        </p>
    </section>

    @include('publico._pie')

    <!-- MODAL DE INICIO DE SESIÓN -->
    <div class="modal" id="loginModal">
        <div class="modal-box">
            <button class="close-btn" onclick="closeLoginModal()">&times;</button>
            <img src="{{ asset('images/logo-cies.png') }}" alt="Clínica CIES" class="modal-logo">
            <h2 style="font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700;">Iniciar Sesión</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Accede a tus citas e historial médico institucional</p>

            @if (request('acceso') === 'agendar')
                <div class="aviso-acceso"><i class="fas fa-calendar-plus"></i> Para agendar tu cita inicia sesión con tu cuenta de paciente. ¿Primera vez? Crea tu cuenta con tu CI y te llevamos directo al formulario de la cita.</div>
            @endif
            @if (session('ok'))
                <div class="error-box" style="background: #eef5f0; color: #3b6449; border-color: #c9ddd0;">{{ session('ok') }}</div>
            @endif
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

    <!-- MODAL DE REGISTRO DE PACIENTE NUEVO (RF003 / RN07) -->
    @php $rerr = $errors->getBag('registro'); @endphp
    <div class="modal" id="registerModal">
        <div class="modal-box" style="max-width: 620px; max-height: 92vh; overflow-y: auto;">
            <button class="close-btn" onclick="closeRegisterModal()">&times;</button>
            <img src="{{ asset('images/logo-cies.png') }}" alt="Clínica CIES" class="modal-logo">
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
                        <input type="password" name="clave_confirmation" required minlength="8" placeholder="••••••••"></div>
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
        @if (request('acceso'))
            openLoginModal();
        @elseif (request('privacidad'))
            openPrivacyModal();
        @endif
        @if ($errors->getBag('registro')->any())
            openRegisterModal();
        @elseif ($errors->any() || session('ok'))
            openLoginModal();
        @endif
    </script>
</body>
</html>