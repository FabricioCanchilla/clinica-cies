@extends('layouts.admin')

@section('titulo', 'Notificaciones')

@section('contenido')
    <h1>Notificaciones</h1>
    <p class="sub">Avisos del sistema. Las alertas críticas (RN46) llegan al administrador y al auditor.</p>

    <div class="card" style="overflow-x: auto;">
        <table>
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Mensaje</th><th>Referencia</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($notificaciones as $n)
                    <tr>
                        <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($n->FEC_NOT)->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="badge {{ $n->TIP_NOT === 'Alerta crítica' ? 'critica' : 'baja' }}">{{ $n->TIP_NOT }}</span>
                        </td>
                        <td>{{ $n->MSG_NOT }}</td>
                        <td>{{ $n->REF_NOT }}</td>
                        <td>{{ $n->EST_NOT === 'Enviada' ? 'Nueva' : $n->EST_NOT }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No tienes notificaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
