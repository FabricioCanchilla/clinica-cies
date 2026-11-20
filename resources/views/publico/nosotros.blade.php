@extends('layouts.publico')
@php $c = config('clinica'); $r = $c['red_nacional']; @endphp

@section('titulo', 'Nosotros')
@section('encabezado', 'Quiénes somos')
@section('bajada', 'CIES — Centro de Investigación, Educación y Servicios — es una organización boliviana de desarrollo social que promueve los derechos sexuales y derechos reproductivos con un modelo de salud integral.')

@section('hero-extra')
    <div class="cifras">
        <div class="cifra"><b>{{ $r['departamentos'] }}</b><span>departamentos de Bolivia con presencia de CIES</span></div>
        <div class="cifra"><b>{{ $r['clinicas'] }}</b><span>clínicas en la red nacional</span></div>
        <div class="cifra"><b>{{ $r['policonsultorios'] }}</b><span>policonsultorios en capitales y ciudades intermedias</span></div>
        <div class="cifra"><b>{{ $r['umosas'] }}</b><span>unidades móviles (UMOSAS) en La Paz, Santa Cruz y Potosí</span></div>
        <div class="cifra"><b>+{{ $r['comunidades'] }}</b><span>comunidades rurales visitadas por las UMOSAS</span></div>
    </div>
@endsection

@section('contenido')
    <section class="bloque">
        <h2>Nuestra historia</h2>
        <p class="lead">{{ $c['historia'] }}</p>
        <div class="rejilla">
            <div class="tarjeta">
                <div class="ico"><i class="fas fa-bullseye"></i></div>
                <h3>Misión</h3>
                <p>{{ $c['mision'] }}</p>
            </div>
            <div class="tarjeta">
                <div class="ico"><i class="fas fa-eye"></i></div>
                <h3>Visión</h3>
                <p>{{ $c['vision'] }}</p>
            </div>
            <div class="tarjeta">
                <div class="ico"><i class="fas fa-award"></i></div>
                <h3>Política de calidad</h3>
                <p>{{ $c['calidad'] }}</p>
            </div>
        </div>
    </section>

    <section class="bloque gris">
        <h2>Nuestros valores</h2>
        <p class="lead">Compartidos por todas y todos quienes trabajan en CIES, en el marco de los derechos humanos y, en especial, de los derechos sexuales y derechos reproductivos.</p>
        <div class="rejilla">
            @foreach ($c['valores'] as $i => $v)
                <div class="tarjeta"><div class="ico"><i class="fas {{ ['fa-scale-balanced', 'fa-people-group', 'fa-hand-holding-heart', 'fa-users', 'fa-star', 'fa-circle-nodes', 'fa-magnifying-glass'][$i] ?? 'fa-check' }}"></i></div><h3>{{ $v }}</h3></div>
            @endforeach
        </div>
    </section>

    <section class="bloque">
        <h2>Nuestro compromiso contigo</h2>
        <p class="lead">Atención en salud y educación en directa relación con el ejercicio pleno de los Derechos Humanos, Derechos Sexuales y Derechos Reproductivos.</p>
        <div class="rejilla">
            @foreach ($c['compromisos'] as [$ico, $t])
                <div class="tarjeta"><div class="ico"><i class="fas {{ $ico }}"></i></div><h3>{{ $t }}</h3></div>
            @endforeach
        </div>
    </section>

    <section class="bloque gris">
        <h2>{{ $c['sede']['nombre'] }} en números</h2>
        <p class="lead">Datos de esta sede registrados en el sistema durante los últimos 12 meses.</p>
        <div class="cifras" style="margin-top: 0;">
            <div class="cifra"><b>{{ $cifras['especialidades'] }}</b><span>especialidades médicas</span></div>
            <div class="cifra"><b>{{ $cifras['medicos'] }}</b><span>médicas y médicos especialistas</span></div>
            <div class="cifra"><b>{{ number_format($cifras['atenciones'], 0, ',', '.') }}</b><span>consultas atendidas</span></div>
            <div class="cifra"><b>{{ number_format($cifras['pacientes'], 0, ',', '.') }}</b><span>pacientes atendidos</span></div>
            <div class="cifra"><b>{{ $cifras['consultorios'] }}</b><span>consultorios</span></div>
            <div class="cifra"><b>{{ $cifras['camas'] }}</b><span>camas de internación</span></div>
        </div>
        <p style="margin-top: 18px; font-size: 13px; color: #64748b;">La red nacional atiende en: {{ implode(', ', $r['ciudades']) }}. Fuente institucional: <a href="{{ $c['sitio'] }}/sobre-cies/" target="_blank" rel="noopener" style="color: var(--cies-green);">cies.org.bo</a>.</p>
    </section>
@endsection
