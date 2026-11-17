<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Médico - Clínica CIES</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cies: '#10b981',       // Verde Esmeralda
                        ciesdark: '#059669',   // Esmeralda oscuro
                        bglight: '#f8fafc',    // Fondo general claro
                        borderlight: '#e2e8f0' // Bordes suaves
                    }
                }
            }
        }
    </script>
    <style>
        /* Scrollbar personalizado para modo claro */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    <style>
        /* RNF011: etiquetas por estado real de la cita */
        .estado-pendiente-de-pago { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .estado-confirmada       { background: #d1fae5; color: #047857; border-color: #10b981; }
        .estado-atendida         { background: #dbeafe; color: #1d4ed8; border-color: #3b82f6; }
        .estado-cancelada        { background: #fee2e2; color: #b91c1c; border-color: #ef4444; }
        .estado-expirada         { background: #f1f5f9; color: #64748b; border-color: #94a3b8; }
        .estado-no-asistio       { background: #fce7f3; color: #9d174d; border-color: #ec4899; }
    </style>
</head>
<body class="bg-bglight font-sans text-slate-800 min-h-screen flex">

    <!-- Sidebar (Menú Lateral) - Blanco con acentos verdes -->
    <aside class="w-64 bg-white border-r border-borderlight flex flex-col hidden md:flex shadow-sm z-10">
        <div class="h-16 flex items-center px-6 border-b border-borderlight">
            <span class="text-xl font-bold text-slate-900 tracking-wide">Clínica <span class="text-cies">CIES</span></span>
        </div>
        <nav class="flex-1 py-4 flex flex-col gap-1">
            <a href="{{ route('medico.dashboard') }}" class="flex items-center gap-3 px-6 py-3 bg-emerald-50 text-cies border-l-4 border-cies font-semibold transition">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <div class="px-6 mt-6 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Gestión Clínica</div>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-slate-600 hover:text-cies hover:bg-slate-50 transition font-medium">
                <i class="fas fa-folder-open text-slate-400"></i> Historias Clínicas
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-slate-600 hover:text-cies hover:bg-slate-50 transition font-medium">
                <i class="fas fa-prescription text-slate-400"></i> Recetas
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-slate-600 hover:text-cies hover:bg-slate-50 transition font-medium">
                <i class="fas fa-vial text-slate-400"></i> Exámenes
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-slate-600 hover:text-cies hover:bg-slate-50 transition font-medium">
                <i class="fas fa-notes-medical text-slate-400"></i> Notas Evolución
            </a>
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        
        <!-- Header - Blanco -->
        <header class="h-16 bg-white border-b border-borderlight flex items-center justify-between px-6 shadow-sm z-10 shrink-0">
            <h1 class="text-lg font-bold text-slate-800">Portal Médico</h1>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-600 font-medium"><i class="fas fa-user-md text-cies mr-1"></i> Dr(a). {{ auth()->user()->USR_USR }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="border border-slate-300 hover:bg-slate-50 text-slate-700 px-4 py-1.5 rounded-lg transition text-xs font-semibold shadow-sm">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </header>

        <!-- Contenido Central con Scroll -->
        <main class="flex-1 p-8 overflow-y-auto">
            
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Mi Agenda</h2>
                <p class="text-slate-500 text-sm mt-1">Gestione sus citas y consultas del mes.</p>
            </div>

            @if (session('ok'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm font-semibold">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm font-semibold">{{ $errors->first() }}</div>
            @endif

            <!-- RF021: programación del día -->
            <div class="bg-white rounded-2xl shadow-sm border border-borderlight p-5 mb-6">
                <h3 class="text-base font-bold text-slate-800 mb-3"><i class="fas fa-calendar-day text-cies mr-1"></i> Citas de hoy ({{ \Carbon\Carbon::today()->format('d/m/Y') }})</h3>
                @if ($hoy->isEmpty())
                    <p class="text-sm text-slate-500">No tiene citas programadas para hoy.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-borderlight">
                                    <th class="py-2 pr-3">Hora</th><th class="py-2 pr-3">Paciente</th><th class="py-2 pr-3">Motivo</th><th class="py-2 pr-3">Servicio</th><th class="py-2 pr-3">Estado</th><th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($hoy as $c)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 pr-3 font-bold">{{ $c['hora'] }}</td>
                                        <td class="py-2 pr-3">{{ $c['paciente'] }} <span class="text-xs text-slate-400">CI {{ $c['ci'] }}</span></td>
                                        <td class="py-2 pr-3">{{ $c['motivo'] }}</td>
                                        <td class="py-2 pr-3">{{ $c['servicio'] }} · {{ $c['consultorio'] }}</td>
                                        <td class="py-2"><span class="estado-{{ \Illuminate\Support\Str::slug($c['estado']) }} text-[11px] px-2.5 py-1 rounded-full font-bold">{{ $c['estado'] }}</span></td>
                                        <td class="py-2">
                                            @if ($c['estado'] === 'Confirmada')
                                                <a href="{{ route('medico.atender', $c['codigo']) }}" class="bg-cies text-white text-xs font-bold px-3 py-1.5 rounded-lg whitespace-nowrap" style="background:#059669;">
                                                    <i class="fas fa-stethoscope"></i> Atender
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Calendario - ELIMINADA LA ALTURA FIJA -->
            <div x-data="calendarioApp({{ json_encode($citas) }})" class="bg-white rounded-2xl shadow-sm border border-borderlight overflow-hidden flex flex-col mb-8">
                
                <!-- Controles del Calendario -->
                <div class="p-5 border-b border-borderlight flex justify-between items-center bg-white">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center bg-slate-100 rounded-lg p-1">
                            <button @click="cambiarMes(-1)" class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition"><i class="fas fa-chevron-left"></i></button>
                            <button @click="irHoy()" class="px-4 py-1 text-sm font-semibold text-slate-700 hover:text-cies transition">Hoy</button>
                            <button @click="cambiarMes(1)" class="w-8 h-8 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 capitalize" x-text="nombreMes"></h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="buscar" placeholder="Paciente o CI"
                               class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:outline-none focus:border-cies">
                        <select x-model="estado" class="text-sm border border-slate-300 rounded-lg px-2 py-1.5 focus:outline-none focus:border-cies">
                            <option value="">Todos los estados</option>
                            @foreach ($estados as $e)
                                <option value="{{ $e }}">{{ $e }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Cabecera de Días -->
                <div class="grid grid-cols-7 border-b border-borderlight bg-slate-50 text-center py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
                </div>

                <!-- Días del Mes - ELIMINADO flex-1 PARA QUE CREZCA -->
                <div class="grid grid-cols-7 bg-slate-200 gap-px">
                    <template x-for="(dia, index) in diasDelMes" :key="index">
                        <!-- Celda del día (Aseguramos una altura mínima para que no se aplasten) -->
                        <div class="bg-white p-2 min-h-[110px] flex flex-col hover:bg-slate-50 transition cursor-pointer group"
                             :class="{'opacity-40 bg-slate-50': !dia.esDelMes}"
                             @click="abrirDia(dia)">
                            
                            <!-- Número de día -->
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-sm font-bold w-7 h-7 flex items-center justify-center rounded-full transition"
                                      :class="dia.esHoy ? 'bg-cies text-white shadow-md shadow-emerald-200' : 'text-slate-700 group-hover:text-cies group-hover:bg-emerald-50'"
                                      x-text="dia.numero"></span>
                                
                                <!-- Badge si hay muchas citas -->
                                <span x-show="citasPorDia(dia.fecha).length > 3" class="text-[10px] font-bold text-white bg-cies px-1.5 py-0.5 rounded-full shadow-sm" x-text="'+' + (citasPorDia(dia.fecha).length - 3)"></span>
                            </div>

                            <!-- Vista previa de Citas (Máximo 3) -->
                            <div class="flex flex-col gap-1.5 overflow-hidden mt-1">
                                <template x-for="(cita, cIndex) in citasPorDia(dia.fecha).slice(0, 3)" :key="cIndex">
                                    <div class="text-[10px] leading-tight px-2 py-1 rounded-md truncate border-l-2 shadow-sm"
                                         :class="claseEstado(cita.estado)">
                                        <span class="font-bold" x-text="cita.hora"></span> - <span x-text="cita.paciente"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Modal / Panel lateral para ver todas las citas del día -->
                <div x-show="diaSeleccionado" 
                     class="fixed inset-0 bg-slate-900/40 z-50 flex items-center justify-center backdrop-blur-sm p-4"
                     x-transition.opacity
                     style="display: none;">
                    
                    <div class="bg-white border border-borderlight rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]"
                         @click.away="diaSeleccionado = null">
                        
                        <!-- Header Modal -->
                        <div class="p-5 border-b border-borderlight flex justify-between items-center bg-slate-50">
                            <h3 class="text-lg font-bold text-slate-800">
                                Citas del <span x-text="diaSeleccionado?.numero"></span> de <span x-text="nombreMes"></span>
                            </h3>
                            <button @click="diaSeleccionado = null" class="text-slate-400 hover:text-slate-700 bg-white hover:bg-slate-100 rounded-full w-8 h-8 flex items-center justify-center transition shadow-sm border border-slate-200"><i class="fas fa-times"></i></button>
                        </div>

                        <!-- Lista completa de citas -->
                        <div class="p-5 overflow-y-auto flex flex-col gap-3 bg-white">
                            <template x-if="citasPorDia(diaSeleccionado?.fecha).length === 0">
                                <div class="text-center py-8">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 mb-3 text-slate-400">
                                        <i class="fas fa-calendar-times text-xl"></i>
                                    </div>
                                    <p class="text-slate-500 font-medium text-sm">No hay citas programadas.</p>
                                </div>
                            </template>

                            <template x-for="(cita, index) in citasPorDia(diaSeleccionado?.fecha)" :key="index">
                                <div class="bg-white border border-slate-200 p-4 rounded-xl flex items-start gap-4 hover:border-cies hover:shadow-md transition group">
                                    <div class="bg-slate-50 text-slate-700 border border-slate-200 px-3 py-2 rounded-lg text-sm font-bold text-center w-16 shadow-sm">
                                        <span x-text="cita.hora"></span>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-base font-bold text-slate-900"><span x-text="cita.paciente"></span> <span class="text-xs font-normal text-slate-400" x-text="'CI ' + cita.ci"></span></h4>
                                        <p class="text-sm text-slate-500 mt-0.5" x-text="cita.motivo"></p>
                                        
                                        <div class="mt-3 flex items-center justify-between">
                                            <span class="text-[11px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wide"
                                                  :class="claseEstado(cita.estado)" x-text="cita.estado"></span>
                                            <span class="text-xs text-slate-400" x-text="cita.servicio + ' · ' + cita.consultorio"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script de Alpine.js para el Calendario -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('calendarioApp', (citas) => ({
                citas: citas,
                buscar: '',
                estado: '',
                mesActual: new Date().getMonth(),
                anioActual: new Date().getFullYear(),
                diaSeleccionado: null,

                get nombreMes() {
                    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    return `${meses[this.mesActual]} ${this.anioActual}`;
                },

                get diasDelMes() {
                    const primerDia = new Date(this.anioActual, this.mesActual, 1);
                    const ultimoDia = new Date(this.anioActual, this.mesActual + 1, 0);
                    
                    let diaSemanaInicio = primerDia.getDay() === 0 ? 6 : primerDia.getDay() - 1;
                    
                    let dias = [];
                    
                    const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

                    for (let i = diaSemanaInicio - 1; i >= 0; i--) {
                        const d = new Date(this.anioActual, this.mesActual, 0 - i);
                        dias.push({ numero: d.getDate(), fecha: iso(d), esDelMes: false, esHoy: false });
                    }
                    
                    const hoy = new Date();
                    for (let i = 1; i <= ultimoDia.getDate(); i++) {
                        const d = new Date(this.anioActual, this.mesActual, i);
                        let esHoy = (i === hoy.getDate() && this.mesActual === hoy.getMonth() && this.anioActual === hoy.getFullYear());
                        dias.push({ numero: i, fecha: iso(d), esDelMes: true, esHoy: esHoy });
                    }
                    
                    const diasRestantes = 42 - dias.length;
                    for (let i = 1; i <= diasRestantes; i++) {
                        const d = new Date(this.anioActual, this.mesActual + 1, i);
                        dias.push({ numero: i, fecha: iso(d), esDelMes: false, esHoy: false });
                    }
                    
                    return dias;
                },

                citasPorDia(fecha) {
                    if (!fecha) return [];
                    const b = this.buscar.trim().toLowerCase();
                    return this.citas.filter(c => c.fecha === fecha
                        && (this.estado === '' || c.estado === this.estado)
                        && (b === '' || c.paciente.toLowerCase().includes(b) || String(c.ci).startsWith(b)));
                },

                claseEstado(estado) {
                    return 'estado-' + estado.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '-');
                },

                cambiarMes(delta) {
                    this.mesActual += delta;
                    if (this.mesActual > 11) { this.mesActual = 0; this.anioActual++; }
                    if (this.mesActual < 0) { this.mesActual = 11; this.anioActual--; }
                },

                irHoy() {
                    const hoy = new Date();
                    this.mesActual = hoy.getMonth();
                    this.anioActual = hoy.getFullYear();
                },

                abrirDia(dia) {
                    if (!dia.esDelMes) return;
                    this.diaSeleccionado = dia;
                }
            }))
        })
    </script>
</body>
</html>