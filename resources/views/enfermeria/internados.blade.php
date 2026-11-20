@extends('layouts.admin')

@section('titulo', 'Pacientes internados')

@section('contenido')
<div data-vivo>
    <h1>Pacientes internados</h1>
    <p class="sub">Turno {{ $turno }} · signos vitales, dosis (5 correctos), notas, balance hídrico y cambio de turno. La pantalla se actualiza sola.</p>

    <div class="card" style="margin-bottom: 24px;">
        <h2><i class="fas fa-bell"></i> Alertas de dosificación (RF034)</h2>
        @if ($alertas->isEmpty())
            <div class="vacio"><i class="fas fa-circle-check"></i> No hay dosis pendientes en la próxima hora.</div>
        @else
            <div class="tabla">
                <table>
                    <thead><tr><th>Hora</th><th>Cama</th><th>Paciente</th><th>Medicamento</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($alertas as $a)
                        <tr>
                            <td class="nowrap"><strong>{{ \Carbon\Carbon::parse($a->FECPRG_DOS)->format('H:i') }}</strong> <small>{{ \Carbon\Carbon::parse($a->FECPRG_DOS)->format('d/m') }}</small></td>
                            <td>{{ $a->NRO_CAM }}</td>
                            <td>{{ $a->APE_PAC }}, {{ $a->NOM_PAC }}</td>
                            <td>{{ $a->DES_MED }} · {{ $a->DOS_DOS }} · {{ $a->DES_VIA }}</td>
                            <td>@if ($a->ATRASADA)<span class="badge critica">Atrasada</span>@else<span class="badge media">Próxima</span>@endif</td>
                            <td><a class="btn sm principal" href="{{ route('enfermeria.internaciones.show', $a->COD_INT) }}#dosis">Atender</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card tabla">
        <h2><i class="fas fa-bed-pulse"></i> Internados ({{ $internados->count() }})</h2>
        @if ($internados->isEmpty())
            <div class="vacio"><i class="fas fa-bed"></i> No hay pacientes internados. Se internan desde <a href="{{ route('enfermeria.camas') }}">Camas e internación</a>.</div>
        @else
            <table>
                <thead><tr><th>Cama</th><th>Paciente</th><th>Motivo</th><th>Últimos signos</th><th>Dosis</th><th></th></tr></thead>
                <tbody>
                @foreach ($internados as $i)
                    <tr>
                        <td><strong>{{ $i->NRO_CAM }}</strong><br><small>{{ $i->NOM_SAL }}</small></td>
                        <td>
                            {{ $i->APE_PAC }}, {{ $i->NOM_PAC }}<br><small>CI {{ $i->CI_PAC }} · {{ $i->TIT_EMP }} {{ $i->APE_EMP }}</small>
                            @if ($i->ALERGIAS)<br><span class="badge critica"><i class="fas fa-allergies"></i> {{ implode(', ', $i->ALERGIAS) }}</span>@endif
                            @if (!empty($i->AIS_INT))<br><span class="badge alta">Aislamiento {{ $i->AIS_INT }}</span>@endif
                        </td>
                        <td>{{ $i->MOT_INT }}<br><small>desde {{ \Carbon\Carbon::parse($i->FECING_INT)->format('d/m/Y H:i') }}</small></td>
                        <td>
                            @if ($i->SIGNO)
                                PA {{ $i->SIGNO->SIS_SIV }}/{{ $i->SIGNO->DIA_SIV }} · P {{ $i->SIGNO->PUL_SIV }} · {{ $i->SIGNO->TEM_SIV }} °C
                                <br><small>{{ \Carbon\Carbon::parse($i->SIGNO->FEC_SIV)->diffForHumans() }}</small>
                            @else
                                <span class="badge media">Sin registro</span>
                            @endif
                        </td>
                        <td>
                            @if ($i->ATRASADAS)<span class="badge critica">{{ $i->ATRASADAS }} atrasada(s)</span>@endif
                            @if ($i->PENDIENTES)<span class="badge media">{{ $i->PENDIENTES }} en la próxima hora</span>@endif
                            @if (!$i->PENDIENTES && !$i->ATRASADAS)<small>Al día</small>@endif
                        </td>
                        <td><a class="btn sm principal" href="{{ route('enfermeria.internaciones.show', $i->COD_INT) }}"><i class="fas fa-notes-medical"></i> Abrir</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

    @php $panel = app(\App\Services\IndicadoresRolService::class)->enfermeria(); @endphp
    <h2 style="margin-top: 8px;"><i class="fas fa-chart-column"></i> Indicadores</h2>
    @include('partials.panel-graficos', ['panel' => $panel])
@endsection
