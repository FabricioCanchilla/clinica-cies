@extends('layouts.admin')

@section('titulo', 'Auditoría')

@section('contenido')
    <h1>Auditoría</h1>
    <p class="sub">Registro de acciones del sistema. Solo lectura: no se puede editar ni eliminar.</p>

    @if ($errors->any())
        <div class="card" style="margin-bottom: 12px;">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.auditoria') }}" class="barra" style="flex-wrap: wrap;">
        <input type="text" name="buscar" value="{{ $f['buscar'] }}" placeholder="Detalle, registro o código">
        <input type="text" name="usuario" value="{{ $f['usuario'] }}" placeholder="Usuario">
        <input type="text" name="paciente" value="{{ $f['paciente'] }}" placeholder="Paciente / CI / expediente">

        <select name="rol">
            <option value="">Todo rol</option>
            @foreach ($roles as $r)
                <option value="{{ $r->COD_ROL }}" @selected($f['rol'] === $r->COD_ROL)>{{ $r->DES_ROL }}</option>
            @endforeach
        </select>

        <select name="accion">
            <option value="">Toda acción</option>
            @foreach ($acciones as $a)
                <option value="{{ $a }}" @selected($f['accion'] === $a)>{{ $a }}</option>
            @endforeach
        </select>

        <select name="criticidad">
            <option value="">Toda criticidad</option>
            @foreach (['Baja', 'Media', 'Alta', 'Crítica'] as $c)
                <option value="{{ $c }}" @selected($f['criticidad'] === $c)>{{ $c }}</option>
            @endforeach
        </select>

        <select name="dominio">
            <option value="">Todo dominio</option>
            @foreach ($dominios as $d)
                <option value="{{ $d->COD_DOM }}" @selected($f['dominio'] === $d->COD_DOM)>{{ $d->DES_DOM }}</option>
            @endforeach
        </select>

        <input type="date" name="desde" value="{{ $f['desde'] }}" title="Desde">
        <input type="date" name="hasta" value="{{ $f['hasta'] }}" title="Hasta">

        <button type="submit" class="btn">Filtrar</button>
        @if (collect($f)->filter(fn ($v) => $v !== '')->isNotEmpty())
            <a href="{{ route('admin.auditoria') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acción</th>
                    <th>Criticidad</th>
                    <th>Dominio</th>
                    <th>Paciente</th>
                    <th>Registro</th>
                    <th>Detalle</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $r)
                    @php
                        $claseCri = match ($r->CRI_AUD) {
                            'Alta', 'Crítica' => 'bloqueado',
                            'Media'           => 'media',
                            default           => 'activo',
                        };
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($r->FEC_AUD)->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->USR_USR }}</td>
                        <td>{{ $r->DES_ROL }}</td>
                        <td>{{ $r->ACC_AUD }}</td>
                        <td><span class="badge {{ $claseCri }}">{{ $r->CRI_AUD }}</span></td>
                        <td>{{ $r->DES_DOM }}</td>
                        <td>
                            @if ($r->COD_PAC)
                                {{ $r->NOM_PAC }} {{ $r->APE_PAC }} <small>(CI {{ $r->CI_PAC }})</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $r->ENT_AUD }} · {{ $r->REG_AUD }}</td>
                        <td>{{ $r->DET_AUD }}</td>
                        <td>{{ $r->IP_AUD }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">No hay registros con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 16px;">
        @if ($registros->previousPageUrl())
            <a href="{{ $registros->previousPageUrl() }}" class="btn">← Anterior</a>
        @endif
        @if ($registros->nextPageUrl())
            <a href="{{ $registros->nextPageUrl() }}" class="btn">Siguiente →</a>
        @endif
    </div>
@endsection