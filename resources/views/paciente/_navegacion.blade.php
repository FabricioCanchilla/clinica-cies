<nav class="paciente-nav" aria-label="Navegación del paciente">
    <a href="{{ route('paciente.dashboard') }}" @class(['activo'=>request()->routeIs('paciente.dashboard') && request('seccion', 'inicio') === 'inicio'])>⌂ &nbsp; Inicio</a>
    <span>MI ESPACIO DE SALUD</span>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'citas']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'citas'])>▣ &nbsp; Mis citas</a>
    <a href="{{ route('paciente.citas.crear') }}" @class(['activo'=>request()->routeIs('paciente.citas.crear')])>＋ &nbsp; Agendar cita</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'resultados']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'resultados'])>◇ &nbsp; Resultados</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'recetas']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'recetas'])>℞ &nbsp; Mis recetas</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'historial']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'historial'])>▤ &nbsp; Historial médico</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'pagos']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'pagos'])>▧ &nbsp; Pagos y comprobantes</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'perfil']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'perfil'])>♙ &nbsp; Mi perfil</a>
    <a href="{{ route('paciente.dashboard', ['seccion' => 'avisos']) }}" @class(['activo' => request()->routeIs('paciente.dashboard') && request('seccion') === 'avisos'])>♧ &nbsp; Notificaciones</a>
</nav>
