@extends('layouts.admin')

@section('titulo', 'Empleados')

@section('contenido')
    <h1>Empleados</h1>
    <p class="sub">Personal de la clínica. Cada empleado puede tener una cuenta de acceso.</p>

    <p style="margin-bottom: 18px;">
        <a href="{{ route('admin.empleados.crear') }}" class="btn principal">+ Nuevo empleado</a>
    </p>

    <form method="GET" action="{{ route('admin.empleados') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por nombre o CI">
        <select name="cargo">
            <option value="">Todos los cargos</option>
            @foreach ($cargos as $c)
                <option value="{{ $c->COD_CAR }}" @selected($cargo === $c->COD_CAR)>{{ $c->DES_CAR }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Filtrar</button>
        @if ($buscar !== '' || $cargo !== '')
            <a href="{{ route('admin.empleados') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>CI</th>
                    <th>Cargo</th>
                    <th>Turno</th>
                    <th>Usuario</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($empleados as $e)
                    <tr>
                        <td>{{ $e->COD_EMP }}</td>
                        <td>
                            {{ $e->NOM_EMP }} {{ $e->APE_EMP }}
                            @if ($e->DES_ESP)
                                <div style="color: var(--suave); font-size: 12px;">{{ $e->DES_ESP }} · Mat. {{ $e->MAT_EMP }}</div>
                            @endif
                        </td>
                        <td>{{ $e->CI_EMP }}</td>
                        <td>{{ $e->DES_CAR }}</td>
                        <td>{{ $e->TURNO_EMP }}</td>
                        <td>
                            @if ($e->USR_USR)
                                {{ $e->USR_USR }}
                            @else
                                <span class="badge media">Sin usuario</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.empleados.editar', $e->COD_EMP) }}" class="btn">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No se encontraron empleados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection