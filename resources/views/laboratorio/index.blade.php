@extends('layouts.admin')

@section('titulo', 'Laboratorio')

@php
    $claseEstado = [
        'Pendiente' => 'media', 'Muestra tomada' => 'baja', 'En proceso' => 'alta',
        'Listo' => 'activo', 'Cancelado' => 'inactivo',
    ];
    $claseResultado = ['Cargado' => 'baja', 'Validado' => 'media', 'Publicado' => 'activo'];
@endphp

@section('contenido')
    <h1>Órdenes de examen</h1>
    <p class="sub">Toma de muestra, carga, validación y publicación de resultados.</p>

    <div class="grid">
        @foreach ($estados as $e)
            <div class="card">
                <div class="num">{{ $contadores[$e] }}</div>
                <div class="lbl">{{ $e }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('laboratorio.dashboard') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por orden, paciente, CI o examen">
        <select name="estado">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
            @endforeach
        </select>
        <select name="prioridad">
            <option value="">Cualquier prioridad</option>
            <option value="Urgente" @selected($prioridad === 'Urgente')>Urgente</option>
            <option value="Normal" @selected($prioridad === 'Normal')>Normal</option>
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $estado !== '' || $prioridad !== '')
            <a href="{{ route('laboratorio.dashboard') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Paciente</th>
                    <th>Examen</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Resultado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ordenes as $o)
                    <tr>
                        <td>
                            {{ $o->COD_ORD }}
                            <div style="color: var(--suave); font-size: 12px;">{{ $o->FEC_ORD }}</div>
                        </td>
                        <td>
                            {{ $o->NOM_PAC }} {{ $o->APE_PAC }}
                            <div style="color: var(--suave); font-size: 12px;">CI {{ $o->CI_PAC }}</div>
                        </td>
                        <td>
                            {{ $o->NOM_SRV }}
                            @if ($o->IND_ORD)
                                <div style="color: var(--suave); font-size: 12px;">{{ $o->IND_ORD }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($o->PRI_ORD === 'Urgente')
                                <span class="badge critica">Urgente</span>
                            @else
                                <span class="badge">Normal</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $claseEstado[$o->EST_ORD] ?? '' }}">{{ $o->EST_ORD }}</span></td>
                        <td>
                            @if ($o->COD_RES)
                                <span class="badge {{ $claseResultado[$o->EST_RES] ?? '' }}">{{ $o->EST_RES }}</span>
                                @if ($o->CRI_RES)
                                    <span class="badge critica">Crítico</span>
                                @endif
                                <div style="color: var(--suave); font-size: 12px; margin-top: 4px;">{{ \Illuminate\Support\Str::limit($o->DES_RES, 90) }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td style="white-space: nowrap;">
                            @if ($o->EST_ORD === 'Pendiente')
                                <form method="POST" action="{{ route('laboratorio.avanzar', $o->COD_ORD) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn principal">Tomar muestra</button>
                                </form>
                                <form method="POST" action="{{ route('laboratorio.cancelar', $o->COD_ORD) }}" style="display: inline;"
                                      onsubmit="return confirm('¿Cancelar la orden {{ $o->COD_ORD }}?');">
                                    @csrf
                                    <button type="submit" class="btn peligro">Cancelar</button>
                                </form>
                            @elseif ($o->EST_ORD === 'Muestra tomada')
                                <form method="POST" action="{{ route('laboratorio.avanzar', $o->COD_ORD) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn principal">Iniciar proceso</button>
                                </form>
                            @elseif ($o->EST_ORD === 'En proceso')
                                @if (!$o->COD_RES)
                                    <a href="{{ route('laboratorio.resultado', $o->COD_ORD) }}" class="btn principal">Cargar resultado</a>
                                @elseif ($o->EST_RES === 'Cargado')
                                    <form method="POST" action="{{ route('laboratorio.validar', $o->COD_ORD) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn principal">Validar</button>
                                    </form>
                                @elseif ($o->EST_RES === 'Validado')
                                    <form method="POST" action="{{ route('laboratorio.publicar', $o->COD_ORD) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn principal">Publicar</button>
                                    </form>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay órdenes con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection