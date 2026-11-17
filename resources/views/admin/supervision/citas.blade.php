@extends('layouts.admin')

@section('titulo', 'Citas')

@section('contenido')
    <h1>Citas</h1>
    <p class="sub">Supervisión de la agenda de la clínica (solo lectura). Las cancelaciones y reprogramaciones las gestiona recepción.</p>

    <form method="GET" class="barra" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom: 20px;">
        <input type="date" name="desde" value="{{ $filtro['desde'] }}">
        <input type="date" name="hasta" value="{{ $filtro['hasta'] }}">
        <select name="estado">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected($filtro['estado'] === $e)>{{ $e }}</option>
            @endforeach
        </select>
        <select name="medico">
            <option value="">Todos los médicos</option>
            @foreach ($medicos as $m)
                <option value="{{ $m->COD_EMP }}" @selected($filtro['medico'] === $m->COD_EMP)>{{ $m->TIT_EMP }} {{ $m->NOM_EMP }} {{ $m->APE_EMP }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn principal">Filtrar</button>
    </form>

    <div class="grid">
        @foreach ($estados as $e)
            <div class="card"><div class="num">{{ $porEstado[$e] ?? 0 }}</div><div class="lbl">{{ $e }}</div></div>
        @endforeach
    </div>

    <div class="card" style="overflow-x: auto;">
        @if ($citas->isEmpty())
            <p class="sub">No hay citas con esos filtros.</p>
        @else
            <table>
                <thead><tr><th>Cita</th><th>Fecha</th><th>Paciente</th><th>Médico</th><th>Servicio</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($citas as $c)
                        <tr>
                            <td>{{ $c->COD_CIT }}</td>
                            <td>{{ \Carbon\Carbon::parse($c->FEC_CIT)->format('d/m/Y') }} {{ substr($c->HOR_CIT, 0, 5) }}</td>
                            <td>{{ $c->NOM_PAC }} {{ $c->APE_PAC }}<br><small>{{ $c->COD_PAC }}</small></td>
                            <td>{{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }}</td>
                            <td>{{ $c->NOM_SRV }}</td>
                            <td>{{ $c->EST_CIT }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
