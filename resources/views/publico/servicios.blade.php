@extends('layouts.publico')
@php $c = config('clinica'); $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, ',', '.'); @endphp

@section('titulo', 'Servicios')
@section('encabezado', 'Servicios y especialidades')
@section('bajada', 'Especialidades con sus médicos, días de atención y tarifa de consulta en ' . $c['sede']['nombre'] . ', más los exámenes de laboratorio e imagenología.')

@section('hero-extra')
    <div class="cifras">
        <div class="cifra"><b>{{ $cifras['especialidades'] }}</b><span>especialidades para agendar en línea</span></div>
        <div class="cifra"><b>{{ $cifras['medicos'] }}</b><span>médicas y médicos</span></div>
        <div class="cifra"><b>{{ $anticipo }} %</b><span>de anticipo para reservar tu horario</span></div>
        <div class="cifra"><b>30 min</b><span>por consulta, hasta 30 días de anticipación</span></div>
    </div>
@endsection

@section('contenido')
    <section class="bloque">
        <h2>Especialidades en {{ $c['sede']['nombre'] }}</h2>
        <p class="lead">Agenda en línea eligiendo la especialidad, el médico y un horario libre. El horario queda reservado 15 minutos mientras pagas el anticipo; el saldo se cancela en caja el día de la consulta.</p>
        <div class="rejilla">
            @foreach ($especialidades as $e)
                <div class="esp">
                    <div class="esp-cab">
                        <h3>{{ $e->DES_ESP }}</h3>
                        @if ($e->PRECIO)<span class="precio">{{ $bs($e->PRECIO) }}</span>@endif
                    </div>
                    @forelse ($e->MEDICOS as $m)
                        <div class="medico">
                            <strong>{{ $m->TIT_EMP }} {{ $m->NOM_EMP }} {{ $m->APE_EMP }}</strong>
                            @if ($m->CONSULTORIO)<small><i class="fas fa-door-open"></i> {{ $m->CONSULTORIO }}</small>@endif
                            <div class="dias">
                                @foreach (['Lunes' => 'Lu', 'Martes' => 'Ma', 'Miércoles' => 'Mi', 'Jueves' => 'Ju', 'Viernes' => 'Vi', 'Sábado' => 'Sá'] as $dia => $abr)
                                    <span class="dia {{ in_array($dia, $m->DIAS, true) ? 'si' : '' }}" title="{{ $m->TURNOS[$dia] ?? 'No atiende' }}">{{ $abr }}</span>
                                @endforeach
                            </div>
                            @if ($m->TURNOS)<small>{{ collect($m->TURNOS)->map(fn ($t, $d) => mb_substr($d, 0, 3) . ' ' . $t)->implode(' · ') }}</small>@endif
                        </div>
                    @empty
                        <p style="font-size: 13px; color: #64748b;">Próximamente.</p>
                    @endforelse
                    <a class="ver-mas" href="{{ route('agendar') }}">Agendar en {{ $e->DES_ESP }} <i class="fas fa-arrow-right"></i></a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bloque gris">
        <h2>Laboratorio e imagenología</h2>
        <p class="lead">Exámenes que solicita tu médico. Los resultados validados se publican en tu cuenta y puedes descargarlos; si un valor es crítico, tu médico recibe una alerta inmediata.</p>
        <div class="rejilla">
            @foreach ($examenes as $tipo => $lista)
                <div>
                    <table class="tabla-pub">
                        <thead><tr><th>{{ $tipo }}</th><th style="text-align: right;">Precio</th></tr></thead>
                        <tbody>
                        @foreach ($lista as $x)
                            <tr><td>{{ $x->NOM_SRV }}</td><td class="der">{{ $bs($x->PRE_SRV) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bloque">
        <h2>Además en esta sede</h2>
        <div class="rejilla">
            <div class="tarjeta"><div class="ico"><i class="fas fa-bed"></i></div><h3>Internación y observación</h3>
                <p>{{ $cifras['camas'] }} camas con control de limpieza y desinfección después de cada alta; limpieza terminal cuando el paciente estuvo en aislamiento.</p></div>
            <div class="tarjeta"><div class="ico"><i class="fas fa-prescription-bottle-medical"></i></div><h3>Farmacia</h3>
                <p>Despacho de tu receta electrónica y venta en mostrador. Los medicamentos salen por fecha de vencimiento (primero el que vence antes).</p></div>
            <div class="tarjeta"><div class="ico"><i class="fas fa-user-nurse"></i></div><h3>Enfermería</h3>
                <p>Si tu familiar trae un medicamento, se registra con firma y un código de verificación, y recibes un comprobante.</p></div>
            <div class="tarjeta"><div class="ico"><i class="fas fa-mobile-screen"></i></div><h3>Tu cuenta de paciente</h3>
                <p>Citas, historial, recetas en PDF, resultados, pagos y comprobantes en un solo lugar.</p></div>
        </div>
    </section>

    <section class="bloque gris">
        <h2>Servicios de la red CIES La Paz</h2>
        <p class="lead">Atención disponible en la red de clínicas y policonsultorios de La Paz y El Alto, según publica CIES.</p>
        @foreach ($c['servicios_red'] as $grupo => $lista)
            <h3 style="font-size: 16px; color: #0f172a; margin: 18px 0 10px;">{{ $grupo }}</h3>
            <div class="chips">@foreach ($lista as $s)<span class="chip">{{ $s }}</span>@endforeach</div>
        @endforeach
    </section>
@endsection
