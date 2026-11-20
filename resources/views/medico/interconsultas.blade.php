@extends('layouts.admin')

@section('titulo', 'Interconsultas')
@section('encabezado', 'Portal Médico · Derivaciones e interconsultas')

@section('contenido')
    <h1>Derivaciones e interconsultas</h1>
    <p class="sub">RF023: pacientes remitidos a tu especialidad por otros médicos de la clínica. Las urgentes aparecen primero.</p>

    <div class="card tabla" style="margin-bottom: 24px;" data-vivo>
        <h2><i class="fas fa-inbox"></i> Recibidas</h2>
        @if ($recibidas->isEmpty())
            <div class="vacio"><i class="fas fa-inbox"></i> No hay derivaciones para tu especialidad.</div>
        @else
            <table>
                <thead><tr><th>Fecha</th><th>Paciente</th><th>De</th><th>Motivo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach ($recibidas as $d)
                    <tr>
                        <td class="nowrap">{{ \Carbon\Carbon::parse($d->FEC_DER)->format('d/m/Y H:i') }}<br><small>{{ $d->COD_DER }}</small></td>
                        <td><a href="{{ route('medico.historia', $d->COD_PAC) }}">{{ $d->APE_PAC }}, {{ $d->NOM_PAC }}</a><br><small>CI {{ $d->CI_PAC }}</small></td>
                        <td>{{ $d->TIT_EMP }} {{ $d->NOM_EMP }} {{ $d->APE_EMP }}<br><small>{{ $d->ESP_ORI }}</small></td>
                        <td>
                            <span class="badge">{{ $d->TIP_DER }}</span>
                            @if ($d->PRI_DER === 'Urgente')<span class="badge critica">Urgente</span>@endif
                            <div style="margin-top: 4px;">{{ $d->MOT_DER }}</div>
                            @if ($d->RES_DER)<small><strong>Respuesta:</strong> {{ $d->RES_DER }}</small>@endif
                        </td>
                        <td><span class="badge {{ $d->EST_DER === 'Respondida' ? 'activo' : ($d->EST_DER === 'Rechazada' ? 'bloqueado' : 'media') }}">{{ $d->EST_DER }}</span></td>
                        <td style="min-width: 240px;">
                            @if ($d->EST_DER === 'Pendiente')
                                <form method="POST" action="{{ route('medico.interconsultas.responder', $d->COD_DER) }}" class="formulario">
                                    @csrf
                                    <textarea name="respuesta" rows="2" minlength="5" maxlength="500" required placeholder="Respuesta / conducta" style="margin-bottom: 6px;"></textarea>
                                    <div class="acciones">
                                        <button name="estado" value="Respondida" class="btn sm principal">Responder</button>
                                        <button name="estado" value="Rechazada" class="btn sm peligro">Rechazar</button>
                                    </div>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card tabla">
        <h2><i class="fas fa-paper-plane"></i> Enviadas por mí</h2>
        @if ($enviadas->isEmpty())
            <div class="vacio">No enviaste derivaciones. Se crean desde la historia clínica o al atender una cita.</div>
        @else
            <table>
                <thead><tr><th>Fecha</th><th>Paciente</th><th>A</th><th>Motivo</th><th>Estado</th></tr></thead>
                <tbody>
                @foreach ($enviadas as $d)
                    <tr>
                        <td class="nowrap">{{ \Carbon\Carbon::parse($d->FEC_DER)->format('d/m/Y') }}</td>
                        <td>{{ $d->APE_PAC }}, {{ $d->NOM_PAC }}</td>
                        <td>{{ $d->DES_ESP }}</td>
                        <td>{{ $d->MOT_DER }} @if ($d->RES_DER)<br><small><strong>Respuesta:</strong> {{ $d->RES_DER }}</small>@endif</td>
                        <td><span class="badge {{ $d->EST_DER === 'Respondida' ? 'activo' : ($d->EST_DER === 'Rechazada' ? 'bloqueado' : 'media') }}">{{ $d->EST_DER }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
