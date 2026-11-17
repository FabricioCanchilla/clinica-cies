@extends('layouts.admin')

@section('titulo', 'Caja')

@php $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ','); @endphp

@section('contenido')
    <h1>Caja</h1>
    <p class="sub">Supervisión de aperturas, cierres y diferencias (RN26) y comprobantes emitidos hoy (solo lectura).</p>

    <h2>Cajas</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
        @if ($cajas->isEmpty())
            <p class="sub">Todavía no se abrió ninguna caja.</p>
        @else
            <table>
                <thead><tr><th>Caja</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th>Cobrado</th><th>Esperado en efectivo</th><th>Contado</th><th>Diferencia</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($cajas as $k)
                        <tr>
                            <td>{{ $k->COD_CAJ }}</td>
                            <td>{{ $k->NOM_EMP }} {{ $k->APE_EMP }}</td>
                            <td>{{ \Carbon\Carbon::parse($k->FECAPE_CAJ)->format('d/m/Y H:i') }}<br><small>{{ $bs($k->MONAPE_CAJ) }}</small></td>
                            <td>{{ $k->FECCIE_CAJ ? \Carbon\Carbon::parse($k->FECCIE_CAJ)->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $bs($k->TOTAL) }}@if ($k->ANULADOS)<br><small style="color:#b91c1c;">{{ $k->ANULADOS }} anulado(s)</small>@endif</td>
                            <td>{{ $bs($k->ESPERADO) }}</td>
                            <td>{{ $k->MONCIE_CAJ !== null ? $bs($k->MONCIE_CAJ) : '—' }}</td>
                            <td>
                                @if ($k->DIFERENCIA === null)
                                    —
                                @else
                                    <strong style="color: {{ abs($k->DIFERENCIA) < 0.005 ? '#047857' : '#b91c1c' }};">{{ $bs($k->DIFERENCIA) }}</strong>
                                @endif
                            </td>
                            <td>{{ $k->EST_CAJ }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Comprobantes de hoy</h2>
    <div class="card" style="overflow-x: auto;">
        @if ($comprobantes->isEmpty())
            <p class="sub">No se emitieron comprobantes hoy.</p>
        @else
            <table>
                <thead><tr><th>N.º</th><th>Hora</th><th>Paciente</th><th>Cita</th><th>Origen</th><th>Monto</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($comprobantes as $c)
                        <tr>
                            <td>{{ $c->NRO_COM }}</td>
                            <td>{{ \Carbon\Carbon::parse($c->FEC_COM)->format('H:i') }}</td>
                            <td>{{ $c->NOM_PAC }} {{ $c->APE_PAC }}</td>
                            <td>{{ $c->COD_CIT }}</td>
                            <td>{{ $c->COD_CAJ ? 'Caja ' . $c->COD_CAJ : 'Anticipo en línea' }}</td>
                            <td>{{ $bs($c->MONTO_COM) }}</td>
                            <td>{{ $c->EST_COM }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
