{{-- RF002 / RNF010: botón de emergencia siempre visible + pie del portal público --}}
@php $c = config('clinica'); @endphp
<div style="position:fixed; right:18px; bottom:18px; z-index:150; display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
    <a href="tel:{{ $c['clinica_la_paz']['tel_link'] }}" style="background:#dc2626; color:#fff; padding:12px 18px; border-radius:999px; font-weight:700; font-size:14px; text-decoration:none; box-shadow:0 8px 20px rgba(220,38,38,.35);">
        <i class="fas fa-ambulance"></i> Emergencias 24 h: (2) 2485111
    </a>
    <a href="{{ $c['whatsapp_link'] }}" target="_blank" rel="noopener" style="background:#16a34a; color:#fff; padding:8px 14px; border-radius:999px; font-weight:600; font-size:12px; text-decoration:none;">
        <i class="fab fa-whatsapp"></i> WhatsApp {{ $c['whatsapp'] }}
    </a>
    <a href="{{ route('publico.ubicacion') }}" style="background:#fff; color:#dc2626; border:1px solid #fecaca; padding:8px 14px; border-radius:999px; font-weight:600; font-size:12px; text-decoration:none;">
        <i class="fas fa-map-marker-alt"></i> Cómo llegar
    </a>
</div>

<footer id="contacto">
    <div class="footer-content">
        <div>
            <div class="footer-logo"><img src="{{ asset('images/logo-cies.png') }}" alt="Clínica CIES"></div>
            <h4>{{ $c['sede']['nombre'] }}</h4>
            <p>{{ $c['sede']['direccion'] }}</p>
            <p>Tel. {{ $c['sede']['telefono'] }} · Cel. {{ $c['sede']['celular'] }}</p>
            <p><a href="{{ route('publico.ubicacion') }}" style="color: var(--cies-green);">📍 Ver mapa y toda la red en La Paz</a></p>
        </div>
        <div>
            <h4>Horarios · Clínica CIES La Paz</h4>
            @foreach ($c['clinica_la_paz']['horario'] as $dia => $hora)
                <p @if ($dia === 'Emergencias') style="color: #f87171; font-weight: 600;" @endif>{{ $dia }}: {{ $hora }}</p>
            @endforeach
        </div>
        <div>
            <h4>Contacto</h4>
            <p>Línea gratuita: {{ $c['linea_gratuita'] }}</p>
            <p>WhatsApp: {{ $c['whatsapp'] }}</p>
            <p>Emergencias: {{ $c['clinica_la_paz']['telefono'] }}</p>
            <p>{{ $c['correo'] }}</p>
        </div>
        <div>
            <h4>Información</h4>
            <ul>
                <li><a href="{{ route('publico.servicios') }}">Especialidades, horarios y tarifas</a></li>
                <li><a href="{{ route('agendar') }}">Agendar una cita en línea</a></li>
                <li><a href="{{ $c['sitio'] }}" target="_blank" rel="noopener">Sitio oficial cies.org.bo</a></li>
                <li><a href="{{ route('login') }}?privacidad=1">Aviso de privacidad y términos de uso</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} {{ $c['razon'] }} · {{ $c['lema'] }}
    </div>
</footer>
