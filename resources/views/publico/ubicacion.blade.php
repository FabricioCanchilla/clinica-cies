@extends('layouts.publico')
@php $c = config('clinica'); @endphp

@section('titulo', 'Ubicación')
@section('encabezado', 'Ubicación y red de atención')
@section('bajada', $c['sede']['nombre'] . ': ' . $c['sede']['direccion'] . '. Para emergencias las 24 horas, la Clínica CIES La Paz atiende en ' . $c['clinica_la_paz']['direccion'] . '.')

@section('contenido')
    <section class="bloque">
        <div class="rejilla" style="grid-template-columns: 2fr 1fr; align-items: start;">
            <div style="border-radius:16px; overflow:hidden; border:1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,.06);">
                <iframe title="Mapa de {{ $c['sede']['nombre'] }}" loading="lazy" style="width:100%; height:420px; border:0; display:block;"
                        src="{{ $c['sede']['mapa'] }}" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <div class="tarjeta">
                <div class="ico"><i class="fas fa-hospital"></i></div>
                <h3>{{ $c['sede']['nombre'] }}</h3>
                <p>{{ $c['sede']['direccion'] }}</p>
                <p style="margin-top: 8px;"><i class="fas fa-phone"></i> <a href="tel:{{ $c['sede']['tel_link'] }}" style="color: var(--cies-green);">{{ $c['sede']['telefono'] }}</a> · Cel. {{ $c['sede']['celular'] }}</p>
                <a class="ver-mas" href="{{ $c['sede']['mapa_link'] }}" target="_blank" rel="noopener"><i class="fas fa-route"></i> Cómo llegar (Google Maps)</a>
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                <h3 style="color: #b91c1c;"><i class="fas fa-truck-medical"></i> Emergencias 24 horas</h3>
                <p>Clínica CIES La Paz<br>{{ $c['clinica_la_paz']['direccion'] }}</p>
                <p style="margin-top: 6px;"><a href="tel:{{ $c['clinica_la_paz']['tel_link'] }}" style="color: #b91c1c; font-weight: 700;">{{ $c['clinica_la_paz']['telefono'] }}</a></p>
                <table class="tabla-pub" style="margin-top: 12px;">
                    @foreach ($c['clinica_la_paz']['horario'] as $dia => $hora)
                        <tr><td>{{ $dia }}</td><td class="der">{{ $hora }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </section>

    <section class="bloque gris">
        <h2>Red CIES en La Paz y El Alto</h2>
        <p class="lead">Puntos de atención publicados por CIES. Llama antes de ir para confirmar los servicios y horarios de cada uno.</p>
        <div class="rejilla">
            @foreach ($c['red_la_paz'] as [$nombre, $dir, $tel, $nota])
                <div class="sede {{ $nombre === $c['sede']['nombre'] ? 'destacada' : '' }}">
                    <i class="fas fa-location-dot"></i>
                    <div>
                        <h4>{{ $nombre }}</h4>
                        <p>{{ $dir }}</p>
                        @if ($tel)<p><i class="fas fa-phone" style="color: var(--cies-green);"></i> {{ $tel }}</p>@endif
                        @if ($nota)<p style="color: var(--cies-green); font-weight: 600;">{{ $nota }}</p>@endif
                        <a class="ver-mas" style="margin-top: 6px;" target="_blank" rel="noopener"
                           href="https://www.google.com/maps/search/?api=1&query={{ urlencode($dir . ', La Paz, Bolivia') }}">Ver en el mapa <i class="fas fa-arrow-up-right-from-square"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bloque">
        <h2>Red nacional</h2>
        <p class="lead">CIES está en los {{ $c['red_nacional']['departamentos'] }} departamentos de Bolivia con {{ $c['red_nacional']['clinicas'] }} clínicas, {{ $c['red_nacional']['policonsultorios'] }} policonsultorios y {{ $c['red_nacional']['umosas'] }} unidades móviles de salud.</p>
        <div class="chips">@foreach ($c['red_nacional']['ciudades'] as $ciudad)<span class="chip"><i class="fas fa-location-dot"></i> CIES {{ $ciudad }}</span>@endforeach</div>
    </section>
@endsection
