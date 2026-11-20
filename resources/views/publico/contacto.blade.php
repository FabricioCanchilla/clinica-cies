@extends('layouts.publico')
@php $c = config('clinica'); @endphp

@section('titulo', 'Contacto')
@section('encabezado', 'Contacto')
@section('bajada', 'Escríbenos o llámanos. Para una emergencia llama directamente a la Clínica CIES La Paz, que atiende las 24 horas.')

@section('contenido')
    <section class="bloque">
        <div class="rejilla">
            <div class="tarjeta">
                <h3>Atención y citas</h3>
                <div class="contacto-item"><i class="fas fa-phone"></i><div><b>{{ $c['sede']['nombre'] }}</b>
                    <a href="tel:{{ $c['sede']['tel_link'] }}">{{ $c['sede']['telefono'] }}</a> <span>· Cel. {{ $c['sede']['celular'] }}</span></div></div>
                <div class="contacto-item"><i class="fas fa-phone-volume"></i><div><b>Línea gratuita nacional</b><a href="tel:800112437">{{ $c['linea_gratuita'] }}</a></div></div>
                <div class="contacto-item"><i class="fab fa-whatsapp"></i><div><b>WhatsApp</b><a href="{{ $c['whatsapp_link'] }}" target="_blank" rel="noopener">{{ $c['whatsapp'] }}</a></div></div>
                <div class="contacto-item"><i class="fas fa-calendar-plus"></i><div><b>Agenda en línea</b><a href="{{ route('agendar') }}">Reserva tu cita con tu cuenta de paciente</a></div></div>
            </div>
            <div class="tarjeta">
                <h3>Emergencias y oficinas</h3>
                <div class="contacto-item"><i class="fas fa-truck-medical" style="background: #fef2f2; color: #b91c1c;"></i><div><b>Emergencias 24 h · Clínica CIES La Paz</b>
                    <a href="tel:{{ $c['clinica_la_paz']['tel_link'] }}">{{ $c['clinica_la_paz']['telefono'] }}</a><br><span>{{ $c['clinica_la_paz']['direccion'] }}</span></div></div>
                <div class="contacto-item"><i class="fas fa-envelope"></i><div><b>Programas y proyectos</b><a href="mailto:{{ $c['correo'] }}">{{ $c['correo'] }}</a></div></div>
                <div class="contacto-item"><i class="fas fa-building"></i><div><b>Oficina nacional</b><span>{{ $c['oficina'] }}</span></div></div>
                <div class="contacto-item"><i class="fas fa-globe"></i><div><b>Sitio oficial</b><a href="{{ $c['sitio'] }}" target="_blank" rel="noopener">cies.org.bo</a></div></div>
            </div>
            <div class="tarjeta">
                <h3>Horario · Clínica CIES La Paz</h3>
                <table class="tabla-pub" style="margin-bottom: 18px;">
                    @foreach ($c['clinica_la_paz']['horario'] as $dia => $hora)
                        <tr><td>{{ $dia }}</td><td class="der">{{ $hora }}</td></tr>
                    @endforeach
                </table>
                <h3>Síguenos</h3>
                <div class="redes">
                    @foreach ($c['redes'] as [$ico, $txt, $url])
                        <a href="{{ $url }}" target="_blank" rel="noopener"><i class="{{ $ico }}"></i> {{ $txt }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bloque gris">
        <h2>Preguntas frecuentes</h2>
        <div class="rejilla">
            <div class="tarjeta"><h3>¿Cómo agendo una cita?</h3><p>Crea tu cuenta de paciente con tu CI, elige especialidad, médico y horario, y confirma el anticipo con tu firma y el código que llega a tu correo. El horario queda reservado 15 minutos.</p></div>
            <div class="tarjeta"><h3>¿Puedo cancelar o cambiar mi cita?</h3><p>Sí, hasta 24 horas antes: se devuelve el anticipo o se traslada a la nueva fecha (hasta 2 cambios por cita). Con menos de 24 horas el anticipo se pierde.</p></div>
            <div class="tarjeta"><h3>¿Mis datos son confidenciales?</h3><p>Sí. Tu historia clínica solo la ve el personal que te atiende y cada acceso queda registrado en auditoría.</p></div>
            <div class="tarjeta"><h3>¿Dónde veo mis resultados?</h3><p>En tu cuenta, en «Mis resultados», cuando el laboratorio los valida y publica. También descargas tus recetas y comprobantes en PDF.</p></div>
        </div>
    </section>
@endsection
