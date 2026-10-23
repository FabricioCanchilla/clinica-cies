@extends('layouts.admin')

@section('titulo', 'Usuarios')

@section('contenido')
    <h1>Usuarios</h1>
    <p class="sub">Cuentas de acceso del personal y de los pacientes.</p>

    <form method="GET" action="{{ route('admin.usuarios') }}" class="barra">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por usuario o nombre">
        <button type="submit" class="btn">Buscar</button>
        @if ($buscar !== '')
            <a href="{{ route('admin.usuarios') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Persona</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Intentos fallidos</th>
                    <th>Último acceso</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr>
                        <td>{{ $u->USR_USR }}</td>
                        <td>{{ $u->PERSONA ?? '—' }}</td>
                        <td>{{ $u->DES_ROL }}</td>
                        <td><span class="badge {{ strtolower($u->EST_USR) }}">{{ $u->EST_USR }}</span></td>
                        <td>{{ $u->INTFAL_USR }}</td>
                        <td>{{ $u->FECULT_USR ? \Carbon\Carbon::parse($u->FECULT_USR)->format('d/m/Y H:i') : 'Nunca' }}</td>
                                                <td>
                            @if ($u->COD_USR === auth()->user()->COD_USR)
                                <span style="color: var(--texto-suave, #888);">Tu cuenta</span>
                            @elseif ($u->EST_USR === 'Activo')
                                <form method="POST" action="{{ route('admin.usuarios.estado', $u->COD_USR) }}"
                                      onsubmit="return confirm('¿Bloquear a {{ $u->USR_USR }}?')">
                                    @csrf
                                    <input type="hidden" name="accion" value="bloquear">
                                    <button type="submit" class="btn peligro">Bloquear</button>
                                </form>
                            @elseif ($u->EST_USR === 'Bloqueado')
                                <form method="POST" action="{{ route('admin.usuarios.estado', $u->COD_USR) }}">
                                    @csrf
                                    <input type="hidden" name="accion" value="desbloquear">
                                    <button type="submit" class="btn">Desbloquear</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No se encontraron usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection