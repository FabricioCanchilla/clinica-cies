@extends('layouts.admin')

@section('titulo', 'Internación')

@section('contenido')
    <h1>Internación</h1>
    <p class="sub">Estado de las camas (RN39) e internaciones registradas (solo lectura).</p>

    <div class="grid">
        @foreach (['Disponible' => '#047857', 'Ocupada' => '#b91c1c', 'Limpieza' => '#92400e'] as $est => $color)
            <div class="card"><div class="num" style="color: {{ $color }};">{{ $porEstado[$est] ?? 0 }}</div><div class="lbl">Camas «{{ $est }}»</div></div>
        @endforeach
    </div>

    <h2>Camas</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
        @if ($camas->isEmpty())
            <p class="sub">No hay camas registradas.</p>
        @else
            <table>
                <thead><tr><th>Sala</th><th>Cama</th><th>Estado</th><th>Paciente internado</th><th>Desde</th></tr></thead>
                <tbody>
                    @foreach ($camas as $c)
                        <tr>
                            <td>{{ $c->NOM_SAL }}<br><small>{{ $c->UBI_SAL }}</small></td>
                            <td>{{ $c->NRO_CAM }}</td>
                            <td>{{ $c->EST_CAM }}</td>
                            <td>{{ $c->NOM_PAC ? $c->NOM_PAC . ' ' . $c->APE_PAC : '—' }}</td>
                            <td>{{ $c->FECING_INT ? \Carbon\Carbon::parse($c->FECING_INT)->format('d/m/Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Internaciones</h2>
    <div class="card" style="overflow-x: auto;">
        @if ($internaciones->isEmpty())
            <p class="sub">No hay internaciones registradas.</p>
        @else
            <table>
                <thead><tr><th>Código</th><th>Paciente</th><th>Médico</th><th>Ingreso</th><th>Alta</th><th>Motivo</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($internaciones as $i)
                        <tr>
                            <td>{{ $i->COD_INT }}</td>
                            <td>{{ $i->NOM_PAC }} {{ $i->APE_PAC }}</td>
                            <td>{{ $i->TIT_EMP }} {{ $i->NOM_EMP }} {{ $i->APE_EMP }}</td>
                            <td>{{ \Carbon\Carbon::parse($i->FECING_INT)->format('d/m/Y H:i') }}</td>
                            <td>{{ $i->FECALT_INT ? \Carbon\Carbon::parse($i->FECALT_INT)->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $i->MOT_INT }}</td>
                            <td>{{ $i->EST_INT }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
