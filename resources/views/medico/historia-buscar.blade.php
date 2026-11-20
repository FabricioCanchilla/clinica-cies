@extends('layouts.admin')

@section('titulo', 'Historias clínicas')
@section('encabezado', 'Portal Médico · Historias clínicas')

@section('contenido')
    <h1>Historias clínicas</h1>
    <p class="sub">RF017: expediente completo del paciente. Ves directo a tus pacientes (citas, internaciones o interconsultas a tu especialidad); a otros solo con acceso de emergencia auditado.</p>

    <form method="GET" class="barra">
        <input name="q" value="{{ $q }}" placeholder="CI, código o nombre del paciente" autofocus>
        <button class="btn principal"><i class="fas fa-search"></i> Buscar</button>
    </form>

    @if ($q !== '')
        <div class="card tabla">
            @if ($pacientes->isEmpty())
                <div class="vacio"><i class="fas fa-user-slash"></i> No hay pacientes con «{{ $q }}».</div>
            @else
                <table>
                    <thead><tr><th>Paciente</th><th>CI</th><th>Edad</th><th>Relación</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($pacientes as $p)
                        <tr>
                            <td>{{ $p->APE_PAC }}, {{ $p->NOM_PAC }} <small>{{ $p->COD_PAC }}</small></td>
                            <td>{{ $p->CI_PAC }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->FECNAC_PAC)->age }} años</td>
                            <td>
                                @if ($p->TRATANTE)<span class="badge activo">Mi paciente</span>@else<span class="badge media">Sin relación</span>@endif
                            </td>
                            <td><a class="btn sm principal" href="{{ route('medico.historia', $p->COD_PAC) }}"><i class="fas fa-file-medical"></i> Abrir historia</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
@endsection
