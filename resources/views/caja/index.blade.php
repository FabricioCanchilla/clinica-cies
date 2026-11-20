@extends('layouts.admin')

@section('titulo', 'Caja')

@php
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
@endphp

@section('contenido')
    <h1>Caja</h1>

    @if (!$caja)
        <p class="sub">No tienes una caja abierta. Ábrela para registrar cobros (RN26).</p>

        <div class="card formulario">
            <form method="POST" action="{{ route('caja.abrir') }}">
                @csrf
                <label for="monto">Monto inicial en efectivo (Bs)</label>
                <input id="monto" type="number" name="monto" min="0" max="100000" step="0.01" value="{{ old('monto', '0.00') }}" required>
                <button type="submit" class="btn principal">Abrir caja</button>
            </form>
        </div>
    @else
        <p class="sub">
            Caja <strong>{{ $caja->COD_CAJ }}</strong> abierta el {{ \Carbon\Carbon::parse($caja->FECAPE_CAJ)->format('d/m/Y H:i') }}.
        </p>

        <div class="grid">
            <div class="card"><div class="num">{{ $bs($caja->MONAPE_CAJ) }}</div><div class="lbl">Apertura</div></div>
            <div class="card"><div class="num">{{ $bs($resumen['total']) }}</div><div class="lbl">Cobrado (confirmado)</div></div>
            <div class="card"><div class="num">{{ $bs($resumen['esperado']) }}</div><div class="lbl">Efectivo esperado en caja</div></div>
            <div class="card"><div class="num" style="color:#b91c1c;">{{ $resumen['anulados'] }}</div><div class="lbl">Cobros anulados</div></div>
        </div>

        @if ($pendientes->isNotEmpty())
            <h2>Cobros sin comprobante</h2>
            <div class="alerta aviso">El pago está registrado, pero Quarkus no generó el comprobante. Reintenta antes de cerrar la caja.</div>
            <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
                <table>
                    <thead><tr><th>Cobro</th><th>Fecha</th><th>Paciente</th><th>Cita</th><th>Monto</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $p)
                            <tr>
                                <td>{{ $p->COD_PAG }}</td>
                                <td>{{ \Carbon\Carbon::parse($p->FEC_PAG)->format('d/m H:i') }}</td>
                                <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</td>
                                <td>{{ $p->COD_CIT }}</td>
                                <td>{{ $bs($p->MON_PAG) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('caja.comprobante.reintentar', $p->COD_PAG) }}">
                                        @csrf
                                        <button type="submit" class="btn principal">Reintentar comprobante</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h2>Citas con saldo por cobrar</h2>
        <form method="GET" action="{{ route('caja.dashboard') }}" class="barra">
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="CI, nombre, apellido o código de cita">
            <button type="submit" class="btn">Buscar</button>
            @if ($buscar !== '')
                <a href="{{ route('caja.dashboard') }}" class="btn">Limpiar</a>
            @endif
        </form>
        <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
            <table>
                <thead><tr><th>Cita</th><th>Fecha</th><th>Paciente</th><th>Servicio</th><th>Precio</th><th>Pagado</th><th>Saldo</th><th></th></tr></thead>
                <tbody>
                    @forelse ($porCobrar as $c)
                        <tr>
                            <td>{{ $c->COD_CIT }} <div style="color: var(--suave); font-size: 12px;">{{ $c->EST_CIT }}</div></td>
                            <td>{{ \Carbon\Carbon::parse($c->FEC_CIT)->format('d/m/Y') }} {{ substr($c->HOR_CIT, 0, 5) }}</td>
                            <td>{{ $c->NOM_PAC }} {{ $c->APE_PAC }} <div style="color: var(--suave); font-size: 12px;">CI {{ $c->CI_PAC }}</div></td>
                            <td>{{ $c->NOM_SRV }} <div style="color: var(--suave); font-size: 12px;">{{ $c->TIT_EMP }} {{ $c->APE_EMP }}</div></td>
                            <td>{{ $bs($c->PRE_SRV) }}</td>
                            <td>{{ $bs($c->PAGADO) }}</td>
                            <td><strong>{{ $bs($c->SALDO) }}</strong></td>
                            <td class="acciones"><a href="{{ route('caja.cobrar', $c->COD_CIT) }}" class="btn principal">Cobrar</a>
                                <a href="{{ route('ordenes.pago', $c->COD_CIT) }}" class="btn sm" title="Orden de pago"><i class="fas fa-file-invoice-dollar"></i> OP</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No hay citas con saldo pendiente{{ $buscar !== '' ? ' para esa búsqueda' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2>Comprobantes de esta caja</h2>
        <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
            <table>
                <thead><tr><th>N.º</th><th>Fecha</th><th>Paciente</th><th>Cita</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($comprobantes as $k)
                        <tr>
                            <td>{{ $k->NRO_COM }}</td>
                            <td>{{ \Carbon\Carbon::parse($k->FEC_COM)->format('d/m H:i') }}</td>
                            <td>{{ $k->NOM_PAC }} {{ $k->APE_PAC }}</td>
                            <td>{{ $k->COD_CIT }}</td>
                            <td>{{ $bs($k->MONTO_COM) }}</td>
                            <td>
                                <span class="badge {{ $k->EST_COM === 'Emitido' ? 'activo' : 'critica' }}">{{ $k->EST_COM }}</span>
                                @if ($k->MOT_ANU)
                                    <div style="color: var(--suave); font-size: 12px; max-width: 220px;">{{ $k->MOT_ANU }}</div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('caja.pdf', $k->NRO_COM) }}" target="_blank" class="btn">PDF</a>
                                @if ($k->EST_COM === 'Emitido' && \Carbon\Carbon::parse($k->FEC_COM)->isToday())
                                    <form method="POST" action="{{ route('caja.anular', $k->NRO_COM) }}" style="display: flex; gap: 6px; margin-top: 6px;"
                                          onsubmit="return confirm('¿Anular el comprobante {{ $k->NRO_COM }}? El saldo volverá a quedar pendiente.');">
                                        @csrf
                                        <input type="text" name="motivo" minlength="10" maxlength="200" placeholder="Motivo (mín. 10 caracteres)" required
                                               style="padding: 6px 8px; border: 1px solid var(--borde); border-radius: 6px; font-size: 13px;">
                                        <button type="submit" class="btn peligro">Anular</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Todavía no hay comprobantes en esta caja.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2>Cierre de caja</h2>
        <div class="card formulario">
            @if ($resumen['porMetodo']->isNotEmpty())
                <table style="margin-bottom: 16px;">
                    <thead><tr><th>Método</th><th>Cobros</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($resumen['porMetodo'] as $m)
                            <tr><td>{{ $m->DES_MET }}</td><td>{{ $m->cantidad }}</td><td>{{ $bs($m->total) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <form method="POST" action="{{ route('caja.cerrar') }}"
                  onsubmit="return confirm('¿Cerrar la caja {{ $caja->COD_CAJ }}? No podrás registrar más cobros en ella.');">
                @csrf
                <label for="contado">Efectivo contado en caja (Bs). Esperado: {{ $bs($resumen['esperado']) }}</label>
                <input id="contado" type="number" name="contado" min="0" step="0.01" value="{{ old('contado') }}" required>
                <button type="submit" class="btn peligro">Cerrar caja</button>
            </form>
        </div>
    @endif

    @php $panel = app(\App\Services\IndicadoresRolService::class)->caja(); @endphp
    <h2 style="margin-top: 8px;"><i class="fas fa-chart-column"></i> Indicadores</h2>
    @include('partials.panel-graficos', ['panel' => $panel])
@endsection
