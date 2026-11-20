@extends('layouts.publico')
@php $c = config('clinica'); $maxDx = max(1, $diagnosticos->max('N') ?? 1); $maxEsp = max(1, $porEspecialidad->max('N') ?? 1); @endphp

@section('titulo', 'Investigación')
@section('encabezado', 'Investigación, educación e impacto social')
@section('bajada', 'Proyectos que CIES desarrolla con instituciones aliadas para llevar salud sexual, salud reproductiva y salud integral a quienes más lo necesitan, y el perfil de atención de nuestros pacientes.')

@section('hero-extra')
    <div class="cifras">
        <div class="cifra"><b>{{ count($c['proyectos']) }}</b><span>proyectos vigentes con aliados</span></div>
        <div class="cifra"><b>{{ $c['red_nacional']['umosas'] }}</b><span>unidades móviles de salud</span></div>
        <div class="cifra"><b>+{{ $c['red_nacional']['comunidades'] }}</b><span>comunidades rurales e indígenas alcanzadas</span></div>
        <div class="cifra"><b>{{ number_format($cifras['examenes'], 0, ',', '.') }}</b><span>exámenes procesados en esta sede (12 meses)</span></div>
    </div>
@endsection

@section('contenido')
    <section class="bloque">
        <h2>Proyectos vigentes</h2>
        <p class="lead">Proyectos orientados a mejorar la calidad de vida de las personas en situación de mayor vulnerabilidad, promoviendo el acceso equitativo a servicios de salud integrales y de calidad.</p>
        <div style="overflow-x: auto;">
            <table class="tabla-pub">
                <thead><tr><th>Instituciones</th><th>Proyecto</th><th>Ubicación</th><th>Público meta</th></tr></thead>
                <tbody>
                @foreach ($c['proyectos'] as [$inst, $proy, $ubi, $pub])
                    <tr><td><strong>{{ $inst }}</strong></td><td>{{ $proy }}</td><td>{{ $ubi }}</td><td>{{ $pub }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top: 10px; font-size: 13px; color: #64748b;">Fuente: <a href="{{ $c['sitio'] }}" target="_blank" rel="noopener" style="color: var(--cies-green);">cies.org.bo · Nuestros proyectos</a>.</p>
    </section>

    <section class="bloque gris">
        <h2>Perfil de atención de {{ $c['sede']['nombre'] }}</h2>
        <p class="lead">Información agregada y anónima de los últimos 12 meses, codificada con la Clasificación Internacional de Enfermedades (CIE-10). No incluye ningún dato que identifique a una persona.</p>
        <div class="rejilla">
            <div class="tarjeta">
                <h3><i class="fas fa-notes-medical" style="color: var(--cies-green);"></i> Diagnósticos más frecuentes</h3>
                @forelse ($diagnosticos as $d)
                    <div class="barra-h">
                        <span class="et"><strong>{{ $d->COD_CIE }}</strong> {{ \Illuminate\Support\Str::limit($d->DES_CIE, 48) }}</span>
                        <span class="fondo"><span style="width: {{ round(100 * $d->N / $maxDx) }}%;"></span></span>
                        <span class="n">{{ number_format($d->N, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p>Aún no hay datos suficientes.</p>
                @endforelse
            </div>
            <div class="tarjeta">
                <h3><i class="fas fa-stethoscope" style="color: var(--cies-green);"></i> Consultas por especialidad</h3>
                @forelse ($porEspecialidad as $e)
                    <div class="barra-h">
                        <span class="et">{{ $e->DES_ESP }}</span>
                        <span class="fondo"><span style="width: {{ round(100 * $e->N / $maxEsp) }}%; background: #cc783c;"></span></span>
                        <span class="n">{{ number_format($e->N, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p>Aún no hay datos suficientes.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bloque">
        <h2>Educación y prevención</h2>
        <div class="rejilla">
            <div class="tarjeta"><div class="ico"><i class="fas fa-chalkboard-user"></i></div><h3>Información y educación a la comunidad</h3>
                <p>Desde sus inicios, CIES trabaja en anticoncepción, prevención del embarazo en adolescentes y trabajo con diversidades sexuales.</p></div>
            <div class="tarjeta"><div class="ico"><i class="fas fa-truck-medical"></i></div><h3>Unidades móviles (UMOSAS)</h3>
                <p>Recorren comunidades rurales de La Paz, Santa Cruz y Potosí con servicios de salud sexual y salud reproductiva para adolescentes, mujeres y hombres.</p></div>
            <div class="tarjeta"><div class="ico"><i class="fas fa-laptop-medical"></i></div><h3>Salud digital</h3>
                <p>Plataforma digital de diabetes para mujeres y proyecto de salud digital para mujeres embarazadas en La Paz y Santa Cruz.</p></div>
        </div>
    </section>
@endsection
