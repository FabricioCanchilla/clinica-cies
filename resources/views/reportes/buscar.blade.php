@extends('layouts.admin')

@section('titulo', 'Reporte por paciente')

@section('contenido')
    <h1>Reporte por paciente</h1>
    <p class="sub">
        Todo lo que el paciente hizo en la clínica: citas, consultas, recetas, exámenes, pagos e internaciones.
        @if (auth()->user()->COD_ROL === 'ROL003') Se muestran solo sus pacientes. @endif
    </p>

    <form method="GET" class="barra">
        <input type="text" name="q" value="{{ $q }}" placeholder="CI, código o nombre del paciente" autofocus>
        <button class="btn principal"><i class="fas fa-search"></i> Buscar</button>
        @if ($q !== '')<a class="btn" href="{{ url()->current() }}">Limpiar</a>@endif
    </form>

    <div class="card tabla">
        <table>
            <thead><tr><th>Paciente</th><th>CI</th><th>Edad</th><th class="derecha">Visitas atendidas</th><th>Última visita</th><th></th></tr></thead>
            <tbody>
                @forelse ($pacientes as $p)
                    <tr>
                        <td><strong>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</strong><br><small>{{ $p->COD_PAC }}</small></td>
                        <td>{{ $p->CI_PAC }}</td>
                        <td>{{ \Carbon\Carbon::parse($p->FECNAC_PAC)->age }} años</td>
                        <td class="derecha">{{ $p->VISITAS }}</td>
                        <td>{{ $p->ULTIMA ? \Carbon\Carbon::parse($p->ULTIMA)->format('d/m/Y') : '—' }}</td>
                        <td class="acciones">
                            <a class="btn sm principal" href="{{ route('reportes.paciente', $p->COD_PAC) }}"><i class="fas fa-file-medical"></i> Ver reporte</a>
                            <a class="btn sm" href="{{ route('reportes.paciente.pdf', $p->COD_PAC) }}" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio"><i class="fas fa-user-slash"></i>No se encontraron pacientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
