@extends('layouts.admin')

@section('titulo', 'Pacientes')

@section('contenido')
    <h1>Pacientes</h1>
    <p class="sub">RF036 / RN07: busca siempre por CI antes de crear una ficha nueva.</p>

    <form method="GET" action="{{ route('recepcion.pacientes') }}" class="barra" style="display:flex; gap:10px; margin-bottom: 20px;">
        <input type="text" name="q" value="{{ $q }}" placeholder="CI, código de paciente o nombre" autofocus style="flex:1;">
        <button type="submit" class="btn principal"><i class="fas fa-search"></i> Buscar</button>
    </form>

    @if ($buscado)
        <div class="card" style="overflow-x: auto;">
            @if ($pacientes->isEmpty())
                <p class="sub">No se encontró ningún paciente con «{{ $q }}».</p>
                <a href="{{ route('recepcion.pacientes.crear', ['ci' => preg_match('/^[0-9]{5,10}$/', $q) ? $q : null]) }}" class="btn principal">
                    <i class="fas fa-user-plus"></i> Registrar paciente nuevo
                </a>
            @else
                <table>
                    <thead><tr><th>Código</th><th>CI</th><th>Nombre</th><th>Teléfono</th><th>Cuenta</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pacientes as $p)
                            <tr>
                                <td>{{ $p->COD_PAC }}</td>
                                <td>{{ $p->CI_PAC }}</td>
                                <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</td>
                                <td>{{ $p->TEL_PAC }}</td>
                                <td>{{ $p->USR_USR ? $p->USR_USR . ' (' . $p->EST_USR . ')' : 'Sin cuenta' }}</td>
                                <td><a href="{{ route('recepcion.paciente', $p->COD_PAC) }}" class="btn">Ver ficha</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @else
        <p class="sub">Escribe un CI para comprobar si el paciente ya tiene ficha.</p>
    @endif
@endsection
