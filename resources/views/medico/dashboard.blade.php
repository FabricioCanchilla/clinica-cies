@extends('layouts.admin')

@section('titulo', 'Mi agenda')
@section('encabezado', 'Portal Médico')

@push('head')
    @include('medico._tailwind')
@endpush

@section('contenido')
<div class="vista-medico">
            
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Mi Agenda</h2>
                <p class="text-slate-500 text-sm mt-1">Gestione sus citas y consultas del mes.</p>
            </div>

            <!-- RF021: programación del día -->
            <div id="citas-hoy" class="bg-white rounded-2xl shadow-sm border border-borderlight p-5 mb-6">
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
                                                <a href="{{ route('medico.atender', $c['codigo']) }}" class="bg-cies text-white text-xs font-bold px-3 py-1.5 rounded-lg whitespace-nowrap">
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
            <div id="agenda" x-data="calendarioApp({{ json_encode($citas) }})" class="bg-white rounded-2xl shadow-sm border border-borderlight overflow-hidden flex flex-col mb-8">
                
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
</div>
@endsection

@push('scripts')
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
@endpush
