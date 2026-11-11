@extends('layouts.admin')

@section('titulo', 'Baja de lote')

@section('contenido')
    <h1>Baja de stock</h1>
    <p class="sub">
        Lote {{ $lote->COD_LOT }} ({{ $lote->NRO_LOT }}) · {{ $lote->DES_MED }} ·
        vence {{ $lote->FECVEN_LOT }} · disponible: {{ $lote->CANDIS_LOT }} uds
    </p>

    <div class="card formulario">
        <form method="POST" action="{{ route('farmacia.lotes.baja.guardar', $lote->COD_LOT) }}">
            @csrf
            <label for="cantidad">Cantidad a dar de baja</label>
            <input id="cantidad" type="number" name="cantidad" min="1" max="{{ $lote->CANDIS_LOT }}"
                   value="{{ old('cantidad', $lote->CANDIS_LOT) }}" required>

            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo" required>
                <option value="">Elige un motivo</option>
                @foreach ($motivos as $m)
                    <option value="{{ $m }}" @selected(old('motivo') === $m)>{{ $m }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn principal">Confirmar baja</button>
            <a href="{{ route('farmacia.lotes') }}" class="btn">Cancelar</a>
        </form>
    </div>
@endsection