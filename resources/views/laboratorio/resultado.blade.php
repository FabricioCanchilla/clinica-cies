@extends('layouts.admin')

@section('titulo', 'Cargar resultado')

@section('contenido')
    <h1>Cargar resultado</h1>
    <p class="sub">
        Orden {{ $orden->COD_ORD }} · {{ $orden->NOM_SRV }} · {{ $orden->NOM_PAC }} {{ $orden->APE_PAC }} (CI {{ $orden->CI_PAC }})
        @if ($orden->PRI_ORD === 'Urgente')
            <span class="badge critica">Urgente</span>
        @endif
    </p>

    @if ($orden->IND_ORD)
        <p class="sub">Indicación del médico: {{ $orden->IND_ORD }}</p>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('laboratorio.resultado.guardar', $orden->COD_ORD) }}" class="formulario ancho">
        @csrf

        <label for="descripcion">Resultado (máx. 500 caracteres)</label>
        <textarea id="descripcion" name="descripcion" rows="6" maxlength="500" required
                  style="width: 100%; padding: 10px 12px; margin-bottom: 16px; background: var(--bg); border: 1px solid var(--borde-input); border-radius: 8px; font-family: inherit; font-size: 14px;">{{ old('descripcion') }}</textarea>
        <label for="archivo">Archivo adjunto opcional (PDF, JPG o PNG, hasta 10 MB)</label>
        <input id="archivo" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">

        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 20px;">
            <input type="checkbox" name="critico" value="1" style="width: auto; margin: 0;" @checked(old('critico'))>
            Hay un valor crítico (se envía una alerta preliminar al médico al guardar)
        </label>

        <button type="submit" class="btn principal">Guardar resultado</button>
        <a href="{{ route('laboratorio.dashboard') }}" class="btn">Volver</a>
    </form>
@endsection
