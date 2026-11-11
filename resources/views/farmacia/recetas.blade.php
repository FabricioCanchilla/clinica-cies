@extends('layouts.admin')

@section('titulo', 'Farmacia')

@php
    $claseEstado = ['Activa' => 'media', 'Completada' => 'activo', 'Anulada' => 'inactivo'];
@endphp

@section('contenido')
    <h1>Recetas</h1>
    <p class="sub">Despacho de recetas electrónicas con descuento de stock por lote (PEPS).</p>

    @if ($alertas['vencidos'] > 0 || $alertas['por_vencer'] > 0)
        <div class="alerta" style="background:#fffbeb; border-color:#fde68a; color:#92400e;">
            Alerta de vencimiento:
            @if ($alertas['vencidos'] > 0) <strong>{{ $alertas['vencidos'] }}</strong> lote(s) vencido(s) con stock. @endif
            @if ($alertas['por_vencer'] > 0) <strong>{{ $alertas['por_vencer'] }}</strong> lote(s) por vencer. @endif
            <a href="{{ route('farmacia.lotes') }}" style="color:inherit; font-weight:700;">Ver lotes</a>
        </div>
    @endif

    <div class="grid">
        @foreach ($estados as $e)
            <div class="card">
                <div class="num">{{ $contadores[$e] }}</div>
                <div class="lbl">{{ $e }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('farmacia.dashboard') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por receta, paciente o CI">
        <select name="estado">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $estado !== '')
            <a href="{{ route('farmacia.dashboard') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Receta</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Estado</th>
                    <th>Entregado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recetas as $r)
                    <tr>
                        <td>
                            {{ $r->COD_REC }}
                            <div style="color: var(--suave); font-size: 12px;">{{ $r->FEC_REC }}</div>
                        </td>
                        <td>
                            {{ $r->NOM_PAC }} {{ $r->APE_PAC }}
                            <div style="color: var(--suave); font-size: 12px;">CI {{ $r->CI_PAC }}</div>
                        </td>
                        <td>{{ $r->NOM_EMP }} {{ $r->APE_EMP }}</td>
                        <td><span class="badge {{ $claseEstado[$r->EST_REC] ?? '' }}">{{ $r->EST_REC }}</span></td>
                        <td>{{ (int) $r->entregadas }} / {{ (int) $r->requeridas }} uds</td>
                        <td>
                            <a href="{{ route('farmacia.receta', $r->COD_REC) }}"
                               class="btn {{ $r->EST_REC === 'Activa' ? 'principal' : '' }}">
                                {{ $r->EST_REC === 'Activa' ? 'Despachar' : 'Ver' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay recetas con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection