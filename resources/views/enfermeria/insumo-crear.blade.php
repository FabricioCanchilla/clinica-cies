@extends('layouts.admin')

@section('titulo', 'Registrar insumo')

@section('contenido')
    <h1>Registrar insumo entregado por un familiar</h1>
    <p class="sub">RF031. Si el insumo está vencido o no tiene número de lote, se registra como Rechazado (RN36).</p>

    <div class="card formulario ancho">
        <form method="POST" action="{{ route('enfermeria.insumos.guardar') }}">
            @csrf

            <h2>Paciente</h2>
            <label for="ci_pac">CI del paciente</label>
            <input id="ci_pac" type="text" name="ci_pac" maxlength="10" value="{{ old('ci_pac') }}" required>

            <h2 style="margin-top: 18px;">Familiar que entrega</h2>
            <div class="fila">
                <div>
                    <label for="ci_ent">CI</label>
                    <input id="ci_ent" type="text" name="ci_ent" maxlength="10" value="{{ old('ci_ent') }}" required>
                </div>
                <div>
                    <label for="tel_ent">Celular (8 dígitos, para el OTP por SMS)</label>
                    <input id="tel_ent" type="text" name="tel_ent" maxlength="8" pattern="\d{8}" value="{{ old('tel_ent') }}" required>
                </div>
            </div>
            <div class="fila">
                <div>
                    <label for="nom_ent">Nombres</label>
                    <input id="nom_ent" type="text" name="nom_ent" maxlength="40" value="{{ old('nom_ent') }}" required>
                </div>
                <div>
                    <label for="ape_ent">Apellidos</label>
                    <input id="ape_ent" type="text" name="ape_ent" maxlength="40" value="{{ old('ape_ent') }}" required>
                </div>
            </div>
            <label for="cor_ent">Correo (para el OTP por correo)</label>
            <input id="cor_ent" type="email" name="cor_ent" maxlength="60" value="{{ old('cor_ent') }}" required>

            <h2 style="margin-top: 18px;">Insumo</h2>
            <label for="cod_med">Medicamento</label>
            <select id="cod_med" name="cod_med" required>
                <option value="">Elige un medicamento</option>
                @foreach ($medicamentos as $m)
                    <option value="{{ $m->COD_MED }}" @selected(old('cod_med') === $m->COD_MED)>{{ $m->DES_MED }}</option>
                @endforeach
            </select>
            <div class="fila">
                <div>
                    <label for="dosis">Dosis / concentración</label>
                    <input id="dosis" type="text" name="dosis" maxlength="20" value="{{ old('dosis') }}" placeholder="500 mg" required>
                </div>
                <div>
                    <label for="cantidad">Cantidad</label>
                    <input id="cantidad" type="number" name="cantidad" min="1" max="1000" value="{{ old('cantidad') }}" required>
                </div>
            </div>
            <div class="fila">
                <div>
                    <label for="lote">N.º de lote (vacío si no tiene)</label>
                    <input id="lote" type="text" name="lote" maxlength="15" value="{{ old('lote') }}">
                </div>
                <div>
                    <label for="fecven">Fecha de vencimiento</label>
                    <input id="fecven" type="date" name="fecven" value="{{ old('fecven') }}" required>
                </div>
            </div>

            <button type="submit" class="btn principal">Registrar insumo</button>
            <a href="{{ route('enfermeria.dashboard') }}" class="btn">Volver</a>
        </form>
    </div>
@endsection
