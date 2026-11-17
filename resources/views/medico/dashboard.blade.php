@extends('medico.layout')
@section('titulo', 'Mi agenda')
@section('body-class', 'agenda-page')

@section('contenido')
<h1>Mi agenda</h1><p class="muted agenda-subtitle">Gestione sus citas y consultas del mes. Seleccione un día para ver a sus pacientes.</p>
<section class="card calendar-card" aria-label="Calendario de citas">
    <div class="calendar-toolbar">
        <div class="calendar-title">
            <div class="calendar-navigation">
                <a aria-label="Mes anterior" href="{{ route('medico.dashboard', array_merge(request()->except('dia'), ['fecha'=>$fecha->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),'vista'=>'mes'])) }}">❮</a>
                <a href="{{ route('medico.dashboard') }}">Hoy</a>
                <a aria-label="Mes siguiente" href="{{ route('medico.dashboard', array_merge(request()->except('dia'), ['fecha'=>$fecha->copy()->addMonthNoOverflow()->startOfMonth()->toDateString(),'vista'=>'mes'])) }}">❯</a>
            </div>
            <h2>{{ $fecha->copy()->locale('es')->translatedFormat('F Y') }}</h2>
        </div>
        <form method="get" class="calendar-filters" aria-label="Filtrar citas">
            <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}"><input type="hidden" name="vista" value="mes">
            <input type="search" name="buscar" aria-label="Paciente o CI" placeholder="Paciente o CI" maxlength="100" value="{{ request('buscar') }}">
            <select name="estado" aria-label="Estado de la cita"><option value="">Todos los estados</option>@foreach($estados as $e)<option @selected(request('estado')===$e)>{{ $e }}</option>@endforeach</select>
            <select name="servicio" aria-label="Servicio"><option value="">Todos los servicios</option>@foreach($servicios as $s)<option value="{{ $s->COD_SRV }}" @selected(request('servicio')===$s->COD_SRV)>{{ $s->NOM_SRV }}</option>@endforeach</select>
            <button>Filtrar</button>
        </form>
    </div>
    <div class="calendar-weekdays" aria-hidden="true"><span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span></div>
    <div class="calendar-grid">
        @foreach($diasCalendario as $dia)
            @php($citasDia = $citas->where('FEC_CIT',$dia->toDateString()))
            <a href="{{ route('medico.dashboard',array_merge(request()->query(),['fecha'=>$fecha->toDateString(),'vista'=>'mes','dia'=>$dia->toDateString()])) }}#citas-del-dia"
               data-day="{{ $dia->toDateString() }}" aria-controls="citas-del-dia" aria-label="{{ $dia->copy()->locale('es')->translatedFormat('j \d\e F') }}: {{ $citasDia->count() }} citas"
               @if($dia->toDateString()===$diaSeleccionado) aria-current="date" @endif
               @class(['calendar-day','other-month'=>!$dia->isSameMonth($fecha),'is-today'=>$dia->isToday(),'is-selected'=>$dia->toDateString()===$diaSeleccionado])>
                <span class="day-number">{{ $dia->day }}</span>
                @foreach($citasDia->take(2) as $c)<span class="day-event {{ \Illuminate\Support\Str::slug($c->estado) }}">{{ substr($c->HOR_CIT,0,5) }} · {{ $c->NOM_PAC }} {{ $c->APE_PAC }}</span>@endforeach
                @if($citasDia->count()>2)<span class="day-more">+{{ $citasDia->count()-2 }} más</span>@endif
            </a>
        @endforeach
    </div>
    <div class="calendar-help"><span><i class="legend-dot"></i> Confirmada / en consulta</span><span><i class="legend-dot" style="background:#f59e0b"></i> En espera</span><span><i class="legend-dot" style="background:#3b82f6"></i> Atendida</span><span>Haz clic en un día para ver sus citas ↓</span></div>
</section>
<section class="card" id="citas-del-dia" aria-live="polite" aria-atomic="false" tabindex="-1">
    @include('medico.partials.citas-dia',['dia'=>\Carbon\Carbon::parse($diaSeleccionado),'citasDia'=>$citas->where('FEC_CIT',$diaSeleccionado)])
</section>
@foreach($diasCalendario as $dia)<template id="citas-{{ $dia->toDateString() }}">@include('medico.partials.citas-dia',['dia'=>$dia,'citasDia'=>$citas->where('FEC_CIT',$dia->toDateString())])</template>@endforeach
<div class="grid"><section class="card"><h2>Interconsultas recibidas</h2>@forelse($interconsultas as $i)<p><strong>{{ $i->NOM_PAC }} {{ $i->APE_PAC }}</strong> <span class="badge">{{ $i->PRIORIDAD }}</span><br>{{ $i->MOTIVO }}<br><a href="{{ route('medico.consultas.ver',$i->COD_CNS) }}">Abrir y responder →</a></p>@empty<p class="muted">Sin interconsultas pendientes.</p>@endforelse</section><section class="card"><h2>Avisos recientes</h2>@forelse($notificaciones as $n)<p><strong>{{ $n->TIP_NOT }}</strong><br>{{ $n->MSG_NOT }}</p>@empty<p class="muted">Sin avisos nuevos.</p>@endforelse<a href="{{ route('notificaciones') }}">Ver todas las notificaciones →</a></section></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-day]').forEach(enlace=>enlace.addEventListener('click',evento=>{
    if(evento.ctrlKey||evento.metaKey||evento.shiftKey||evento.altKey)return;
    const plantilla=document.getElementById('citas-'+enlace.dataset.day),panel=document.getElementById('citas-del-dia');
    if(!plantilla||!panel)return;
    evento.preventDefault();panel.replaceChildren(plantilla.content.cloneNode(true));
    document.querySelectorAll('[data-day]').forEach(d=>{d.classList.remove('is-selected');d.removeAttribute('aria-current');});
    enlace.classList.add('is-selected');enlace.setAttribute('aria-current','date');
    history.replaceState(null,'',enlace.href);
    panel.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'nearest'});
}));
</script>
@endpush
