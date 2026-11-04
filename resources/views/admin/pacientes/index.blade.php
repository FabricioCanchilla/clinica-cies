@extends('layouts.admin')

@section('titulo', 'Pacientes')

@section('contenido')

    <h1>Pacientes</h1>
    <p class="sub">Gestión y consulta de pacientes registrados en la Clínica CIES.</p>

    <form method="GET" action="{{ route('admin.pacientes') }}" class="barra">
        <input
            type="text"
            name="buscar"
            value="{{ $buscar }}"
            placeholder="Buscar por CI, código, nombre o apellido"
        >

        <button type="submit" class="btn">Buscar</button>

        @if ($buscar !== '')
            <a href="{{ route('admin.pacientes') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="card" style="overflow-x: auto;">

        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>CI</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Sexo</th>
                    <th>Fecha de nacimiento</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                </tr>
            </thead>

            <tbody>

                @forelse ($pacientes as $p)

                    <tr>
                        <td>{{ $p->COD_PAC }}</td>
                        <td>{{ $p->CI_PAC }}</td>
                        <td>{{ $p->NOM_PAC }}</td>
                        <td>{{ $p->APE_PAC }}</td>
                        <td>{{ $p->SEX_PAC }}</td>

                        <td>
                            {{ $p->FECNAC_PAC
                                ? \Carbon\Carbon::parse($p->FECNAC_PAC)->format('d/m/Y')
                                : '—' }}
                        </td>

                        <td>{{ $p->TEL_PAC }}</td>
                        <td>{{ $p->COR_PAC }}</td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="8" style="text-align: center;">
                            No se encontraron pacientes registrados.
                        </td>
                    </tr>

                @endforelse

            </tbody>
        </table>

    </div>

    <div style="margin-top: 18px;">
        {{ $pacientes->links() }}
    </div>

@endsection