@extends('layouts.admin')

@section('titulo', 'Auditoría')

@section('contenido')
    <h1>Auditoría</h1>
    <p class="sub">Registro de acciones del sistema. Solo lectura: no se puede editar ni eliminar.</p>

    <form method="GET" action="{{ route('admin.auditoria') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Usuario, detalle o código">
        <select name="criticidad">
            <option value="">Toda criticidad</option>
            @foreach (['Baja', 'Media', 'Alta', 'Crítica'] as $c)
                <option value="{{ $c }}" @selected($criticidad === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <select name="dominio">
            <option value="">Todo dominio</option>
            @foreach ($dominios as $d)
                <option value="{{ $d->COD_DOM }}" @selected($dominio === $d->COD_DOM)>{{ $d->DES_DOM }}</option>
            @endforeach
        </select>
        <input type="date" name="desde" value="{{ $desde }}" title="Desde">
        <input type="date" name="hasta" value="{{ $hasta }}" title="Hasta">
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $criticidad !== '' || $dominio !== '' || $desde !== '' || $hasta !== '')
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
                        <td>{{ $r->ENT_AUD }} · {{ $r->REG_AUD }}</td>
                        <td>{{ $r->DET_AUD }}</td>
                        <td>{{ $r->IP_AUD }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No hay registros con esos filtros.</td></tr>
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