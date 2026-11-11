@extends('layouts.admin')

@section('titulo', 'Despacho de receta')

@php
    $claseEstado = ['Activa' => 'media', 'Completada' => 'activo', 'Anulada' => 'inactivo'];
@endphp

@section('contenido')
    <h1>Receta {{ $receta->COD_REC }}
        <span class="badge {{ $claseEstado[$receta->EST_REC] ?? '' }}">{{ $receta->EST_REC }}</span>
    </h1>
    <p class="sub">
        {{ $receta->NOM_PAC }} {{ $receta->APE_PAC }} · CI {{ $receta->CI_PAC }} ·
        Médico: {{ $receta->NOM_EMP }} {{ $receta->APE_EMP }} · Emitida {{ $receta->FEC_REC }}
        @if ($receta->OBS_REC) · Obs.: {{ $receta->OBS_REC }} @endif
    </p>

    <div class="card" style="overflow-x: auto; margin-bottom: 16px;">
        <table>
            <thead>
                <tr>
                    <th>Medicamento</th>
                    <th>Indicación</th>
                    <th>Requeridas</th>
                    <th>Entregadas</th>
                    <th>Pendientes</th>
                    <th>Stock vigente</th>
                    <th>Despachar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $i)
                    <tr>
                        <td>{{ $i->DES_MED }}</td>
                        <td>
                            {{ $i->DOS_DRE }} · {{ $i->DES_VIA }}
                            <div style="color: var(--suave); font-size: 12px;">cada {{ $i->FRE_DRE }} h por {{ $i->DUR_DRE }} días</div>
                        </td>
                        <td>{{ $i->requeridas }}</td>
                        <td>{{ (int) $i->CANENT_DRE }}</td>
                        <td>
                            @if ($i->pendientes === 0)
                                <span class="badge activo">Completo</span>
                            @else
                                {{ $i->pendientes }}
                            @endif
                        </td>
                        <td>
                            @if ($i->stock === 0)
                                <span class="badge critica">Sin stock</span>
                            @else
                                {{ $i->stock }}
                            @endif
                        </td>
                        <td style="white-space: nowrap;">
                            @if ($receta->EST_REC === 'Activa' && $i->pendientes > 0 && $i->stock > 0)
                                <form method="POST" action="{{ route('farmacia.despachar', [$receta->COD_REC, $i->COD_DRE]) }}"
                                      style="display: inline-flex; gap: 8px; align-items: center;">
                                    @csrf
                                    <input type="number" name="cantidad" min="1" max="{{ $i->pendientes }}"
                                           value="{{ min($i->pendientes, $i->stock) }}"
                                           style="width: 90px; padding: 7px 10px; border: 1px solid var(--borde-input); border-radius: 8px;">
                                    <button type="submit" class="btn principal">Entregar</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="sub">Unidades requeridas = (24 ÷ frecuencia en horas) × días. El stock se descuenta del lote que vence primero (PEPS); los lotes vencidos no se usan.</p>
    <a href="{{ route('farmacia.dashboard') }}" class="btn">← Volver a recetas</a>
@endsection