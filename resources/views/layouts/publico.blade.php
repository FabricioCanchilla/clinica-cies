<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — Clínica CIES</title>
    <meta name="description" content="@yield('descripcion', 'CIES · Salud sexual, salud reproductiva y salud integral en La Paz, Bolivia.')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('publico._estilos')
</head>
<body>
    @include('publico._cabecera')

    <section class="pagina-hero">
        <div>
            <div class="migas"><a href="{{ route('login') }}">Inicio</a> › @yield('titulo')</div>
            <h1>@yield('encabezado')</h1>
            <p>@yield('bajada')</p>
            @yield('hero-extra')
        </div>
    </section>

    @yield('contenido')

    <section class="bloque">
        <div class="cta">
            <div>
                <h3>¿Necesitas una consulta?</h3>
                <p>Agenda en línea: eliges especialidad, médico y horario, y confirmas con el anticipo.</p>
            </div>
            <a href="{{ route('agendar') }}"><i class="fas fa-calendar-plus"></i> Agendar cita</a>
        </div>
    </section>

    @include('publico._pie')
</body>
</html>
