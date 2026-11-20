{{-- Barra superior del portal público. $portada = true en la portada (el botón abre el modal de acceso). --}}
@php
    $c = config('clinica');
    $inicio = auth()->check() ? route('panel') : route('login');
    $portada = $portada ?? false;
@endphp
<header class="header">
    <a href="{{ route('login') }}" class="logo-container" title="Clínica CIES">
        <img src="{{ asset('images/logo-cies.png') }}" alt="CIES · Salud sexual, salud reproductiva, salud integral" class="logo-img">
    </a>

    <ul class="nav-links">
        <li><a href="{{ $portada ? '#inicio' : route('login') }}" class="{{ $portada ? 'activo' : '' }}">Inicio</a></li>
        <li><a href="{{ route('publico.nosotros') }}" class="{{ request()->routeIs('publico.nosotros') ? 'activo' : '' }}">Nosotros</a></li>
        <li><a href="{{ route('publico.servicios') }}" class="{{ request()->routeIs('publico.servicios') ? 'activo' : '' }}">Servicios</a></li>
        <li><a href="{{ route('publico.investigacion') }}" class="{{ request()->routeIs('publico.investigacion') ? 'activo' : '' }}">Investigación</a></li>
        <li><a href="{{ route('publico.ubicacion') }}" class="{{ request()->routeIs('publico.ubicacion') ? 'activo' : '' }}">Ubicación</a></li>
        <li><a href="{{ route('publico.contacto') }}" class="{{ request()->routeIs('publico.contacto') ? 'activo' : '' }}">Contacto</a></li>
    </ul>

    <div class="header-actions">
        <div class="emergency-contact">
            <span class="emergency-label"><i class="fas fa-phone-alt"></i> Emergencias 24 h</span>
            <a href="tel:{{ $c['clinica_la_paz']['tel_link'] }}" class="emergency-number">(2) 2485111</a>
        </div>
        @auth
            <a href="{{ route('panel') }}" class="btn-login-nav" style="text-decoration: none;"><i class="fas fa-gauge"></i> Mi panel</a>
        @else
            @if ($portada)
                <button type="button" onclick="openLoginModal()" class="btn-login-nav"><i class="fas fa-user"></i> Iniciar Sesión</button>
            @else
                <a href="{{ route('login') }}?acceso=1" class="btn-login-nav" style="text-decoration: none;"><i class="fas fa-user"></i> Iniciar Sesión</a>
            @endif
        @endauth
        <a href="{{ route('agendar') }}" class="btn-appointment">Agendar Cita</a>
    </div>
</header>
