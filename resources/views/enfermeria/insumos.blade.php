@extends('layouts.admin')

@section('titulo', 'Insumos de familiares')

@php
    $claseEstado = ['Pendiente de verificación' => 'media', 'Verificado' => 'activo', 'Rechazado' => 'critica'];
@endphp

@section('contenido')
    <h1>Insumos entregados por familiares</h1>
    <p class="sub">RF031 y RF032: el insumo se cierra solo con la firma del familiar, el código OTP y el comprobante de entrega (RN36).</p>

    <form method="GET" action="{{ route('enfermeria.dashboard') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Código, CI o apellido del paciente, CI del familiar">
        <select name="estado">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $estado !== '')
            <a href="{{ route('enfermeria.dashboard') }}" class="btn">Limpiar</a>
        @endif
        <a href="{{ route('enfermeria.insumos.crear') }}" class="btn principal">Registrar insumo</a>
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr><th>Insumo</th><th>Paciente</th><th>Medicamento</th><th>Lote / vence</th><th>Entregó</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                @forelse ($insumos as $i)
                    <tr>
                        <td>{{ $i->COD_INS }} <div style="color: var(--suave); font-size: 12px;">{{ \Carbon\Carbon::parse($i->FEC_INS)->format('d/m/Y H:i') }}</div></td>
                        <td>{{ $i->NOM_PAC }} {{ $i->APE_PAC }} <div style="color: var(--suave); font-size: 12px;">CI {{ $i->CI_PAC }}</div></td>
                        <td>{{ $i->DES_MED }} <div style="color: var(--suave); font-size: 12px;">{{ $i->DOS_INS }} · {{ $i->CAN_INS }} u.</div></td>
                        <td>{{ $i->LOT_INS }} <div style="color: var(--suave); font-size: 12px;">{{ \Carbon\Carbon::parse($i->FECVEN_INS)->format('d/m/Y') }}</div></td>
                        <td>{{ $i->NOM_ENT }} {{ $i->APE_ENT }} <div style="color: var(--suave); font-size: 12px;">CI {{ $i->CI_ENT }}</div></td>
                        <td><span class="badge {{ $claseEstado[$i->EST_INS] ?? '' }}">{{ $i->EST_INS }}</span></td>
                        <td>
                            @if ($i->EST_INS === 'Pendiente de verificación')
                                <a href="{{ route('enfermeria.insumos.verificar', $i->COD_INS) }}" class="btn principal">Firma y OTP</a>
                            @elseif ($i->EST_INS === 'Verificado')
                                @if ($i->NRO_CEN)
                                    <a href="{{ route('enfermeria.insumos.pdf', $i->NRO_CEN) }}" target="_blank" class="btn">{{ $i->NRO_CEN }}</a>
                                @else
                                    <form method="POST" action="{{ route('enfermeria.insumos.comprobante', $i->COD_INS) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn principal">Reintentar comprobante</button>
                                    </form>
                                @endif
                                <a href="{{ route('enfermeria.insumos.firma', $i->COD_INS) }}" target="_blank" class="btn">Firma</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay insumos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
