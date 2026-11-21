@extends('layouts.admin')

@section('titulo', 'Recepción')

@section('contenido')
    <h1>Recepción · {{ \Carbon\Carbon::today()->format('d/m/Y') }}</h1>
    <p class="sub">Check-in de llegada y disponibilidad en tiempo real (RF037). Tolerancia de llegada: 15 minutos (RN23).</p>

    <div class="grid">
        <div class="card"><div class="num">{{ $citas->where('EST_CIT', 'Confirmada')->count() }}</div><div class="lbl">Citas confirmadas hoy</div></div>
        <div class="card"><div class="num">{{ $citas->filter(fn ($c) => $c->CHECKIN)->count() }}</div><div class="lbl">Pacientes que llegaron</div></div>
        <div class="card"><div class="num">{{ $consultorios->where('OCUPADO', false)->count() }} / {{ $consultorios->count() }}</div><div class="lbl">Consultorios libres ahora</div></div>
        <div class="card"><div class="num">{{ $camas['Disponible'] ?? 0 }}</div><div class="lbl">Camas disponibles ({{ $camas->sum() }} en total)</div></div>
    </div>

    <h2>Citas de hoy</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
        @if ($citas->isEmpty())
            <p class="sub">No hay citas para hoy.</p>
        @else
            <table>
                <thead><tr><th>Hora</th><th>Paciente</th><th>Médico</th><th>Servicio</th><th>Estado</th><th>Llegada</th><th></th></tr></thead>
                <tbody>
                    @foreach ($citas as $c)
                        <tr>
                            <td><strong>{{ substr($c->HOR_CIT, 0, 5) }}</strong></td>
                            <td><a href="{{ route('recepcion.paciente', $c->COD_PAC) }}">{{ $c->NOM_PAC }} {{ $c->APE_PAC }}</a><br><small>CI {{ $c->CI_PAC }}</small></td>
                            <td>{{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }}</td>
                            <td>{{ $c->NOM_SRV }}<br><small>{{ $c->NOM_CON }}</small></td>
                            <td>{{ $c->EST_CIT }}</td>
                            <td>
                                @if ($c->CHECKIN)
                                    <span style="color:#3b6449; font-weight:700;"><i class="fas fa-check"></i> {{ \Carbon\Carbon::parse($c->CHECKIN)->format('H:i') }}</span>
                                @elseif ($c->EST_CIT === 'Confirmada' && $c->FUERA_TOLERANCIA)
                                    <span style="color:#b91c1c; font-weight:600;">Fuera de tolerancia</span>
                                @else
                                    <small>—</small>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="{{ route('ordenes.pago', $c->COD_CIT) }}" class="btn sm" title="Orden de pago"><i class="fas fa-file-invoice-dollar"></i> OP</a>
                                @if ($c->EST_CIT === 'Confirmada' && !$c->CHECKIN)
                                    @if (!$c->FUERA_TOLERANCIA)
                                        <form method="POST" action="{{ route('recepcion.checkin', $c->COD_CIT) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn principal"><i class="fas fa-sign-in-alt"></i> Check-in</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('recepcion.inasistencia', $c->COD_CIT) }}" style="display:inline;"
                                              onsubmit="return confirm('La cita pasará a No asistio y el anticipo se pierde (RN23). ¿Continuar?');">
                                            @csrf
                                            <button type="submit" class="btn peligro">Marcar inasistencia</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Disponibilidad de médicos hoy</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
        <table>
            <thead><tr><th>Médico</th><th>Especialidad</th><th>Horas libres hoy</th><th>Próxima hora libre</th></tr></thead>
            <tbody>
                @forelse ($medicos as $m)
                    <tr>
                        <td>{{ $m->TIT_EMP }} {{ $m->NOM_EMP }} {{ $m->APE_EMP }}</td>
                        <td>{{ $m->DES_ESP }}</td>
                        <td>{{ $m->TIENE_HORARIO ? $m->LIBRES : 'Sin horario registrado' }}</td>
                        <td>{{ $m->PROXIMA ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay médicos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Consultorios y camas</h2>
    <div class="grid">
        @foreach ($consultorios as $k)
            <div class="card">
                <div class="num" style="font-size: 20px; color: {{ $k->OCUPADO ? '#b91c1c' : '#3b6449' }};">{{ $k->OCUPADO ? 'Ocupado' : 'Libre' }}</div>
                <div class="lbl">{{ $k->NOM_CON }} · {{ $k->UBI_CON }}</div>
            </div>
        @endforeach
        @foreach (['Disponible' => '#3b6449', 'Ocupada' => '#b91c1c', 'Limpieza' => '#92400e'] as $est => $color)
            <div class="card">
                <div class="num" style="font-size: 20px; color: {{ $color }};">{{ $camas[$est] ?? 0 }}</div>
                <div class="lbl">Camas en estado «{{ $est }}»</div>
            </div>
        @endforeach
    </div>
@endsection
