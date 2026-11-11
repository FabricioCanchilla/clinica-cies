@extends('layouts.admin')

@section('titulo', 'Lotes y stock')

@php
    $claseLote = ['Vigente' => 'activo', 'Por vencer' => 'media', 'Vencido' => 'critica', 'Agotado' => 'inactivo'];
@endphp

@section('contenido')
    <h1>Lotes y stock</h1>
    <p class="sub">Existencias, entradas, bajas y alertas de vencimiento (PEPS: sale primero el lote que vence antes).</p>

    <div class="grid">
        <div class="card"><div class="num">{{ $contadores['vigentes'] }}</div><div class="lbl">Lotes vigentes</div></div>
        <div class="card"><div class="num" style="color:#b45309;">{{ $contadores['por_vencer'] }}</div><div class="lbl">Por vencer (≤ {{ $diasAlerta }} días)</div></div>
        <div class="card"><div class="num" style="color:#b91c1c;">{{ $contadores['vencidos'] }}</div><div class="lbl">Vencidos con stock</div></div>
    </div>

    <h2>Existencias por medicamento</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
        <table>
            <thead><tr><th>Medicamento</th><th>Disponible (vigente)</th><th>Vencido con stock</th></tr></thead>
            <tbody>
                @foreach ($existencias as $e)
                    <tr>
                        <td>{{ $e->DES_MED }}</td>
                        <td>{{ (int) $e->vigente }}</td>
                        <td>
                            @if ((int) $e->vencido > 0)
                                <span class="badge critica">{{ (int) $e->vencido }}</span>
                            @else
                                0
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Registrar entrada de lote</h2>
    <div class="card formulario ancho" style="margin-bottom: 28px;">
        <form method="POST" action="{{ route('farmacia.lotes.entrada') }}">
            @csrf
            <label for="cod_med">Medicamento</label>
            <select id="cod_med" name="cod_med" required>
                <option value="">Elige un medicamento</option>
                @foreach ($medicamentos as $m)
                    <option value="{{ $m->COD_MED }}" @selected(old('cod_med') === $m->COD_MED)>{{ $m->DES_MED }}</option>
                @endforeach
            </select>
            <div class="fila">
                <div>
                    <label for="nro_lot">N.º de lote</label>
                    <input id="nro_lot" type="text" name="nro_lot" maxlength="20" value="{{ old('nro_lot') }}" required>
                </div>
                <div>
                    <label for="cantidad">Cantidad (unidades)</label>
                    <input id="cantidad" type="number" name="cantidad" min="1" value="{{ old('cantidad') }}" required>
                </div>
            </div>
            <label for="fecven">Fecha de vencimiento</label>
            <input id="fecven" type="date" name="fecven" value="{{ old('fecven') }}" required>
            <button type="submit" class="btn principal">Registrar entrada</button>
        </form>
    </div>

    <h2>Lotes</h2>
    <form method="GET" action="{{ route('farmacia.lotes') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por lote o medicamento">
        <select name="estado">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $estado !== '')
            <a href="{{ route('farmacia.lotes') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Medicamento</th>
                    <th>Ingreso</th>
                    <th>Vence</th>
                    <th>Disponible / Ingresado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lotes as $l)
                    <tr>
                        <td>
                            {{ $l->COD_LOT }}
                            <div style="color: var(--suave); font-size: 12px;">{{ $l->NRO_LOT }}</div>
                        </td>
                        <td>{{ $l->DES_MED }}</td>
                        <td>{{ $l->FECING_LOT }}</td>
                        <td>{{ $l->FECVEN_LOT }}</td>
                        <td>{{ $l->CANDIS_LOT }} / {{ $l->CANING_LOT }}</td>
                        <td><span class="badge {{ $claseLote[$l->ESTADO] ?? '' }}">{{ $l->ESTADO }}</span></td>
                        <td>
                            @if ((int) $l->CANDIS_LOT > 0)
                                <a href="{{ route('farmacia.lotes.baja', $l->COD_LOT) }}" class="btn peligro">Dar de baja</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay lotes con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection