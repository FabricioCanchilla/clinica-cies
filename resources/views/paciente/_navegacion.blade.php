<nav class="paciente-nav" aria-label="Navegación del paciente">
    <a href="{{ route('paciente.dashboard') }}" @class(['activo'=>request()->routeIs('paciente.dashboard')])>⌂ &nbsp; Inicio</a>
    <span>MI ESPACIO DE SALUD</span>
    <a href="{{ route('paciente.dashboard') }}#citas">▣ &nbsp; Mis citas</a>
    <a href="{{ route('paciente.citas.crear') }}" @class(['activo'=>request()->routeIs('paciente.citas.crear')])>＋ &nbsp; Agendar cita</a>
    <a href="{{ route('paciente.dashboard') }}#resultados">◇ &nbsp; Resultados</a>
    <a href="{{ route('paciente.dashboard') }}#recetas">℞ &nbsp; Mis recetas</a>
    <a href="{{ route('paciente.dashboard') }}#historial">▤ &nbsp; Historial médico</a>
    <a href="{{ route('paciente.dashboard') }}#pagos">▧ &nbsp; Pagos y comprobantes</a>
    <a href="{{ route('paciente.dashboard') }}#perfil">♙ &nbsp; Mi perfil</a>
    <a href="{{ route('paciente.dashboard') }}#avisos">♧ &nbsp; Notificaciones</a>
</nav>
