@extends('layouts.admin')

@section('titulo', 'Dashboard')

@section('contenido')
    <h1>Dashboard</h1>
    <p class="sub">Resumen general del sistema</p>

    <div class="grid">
        @foreach ($totales as $etiqueta => $total)
            <div class="card">
                <div class="num">{{ $total }}</div>
                <div class="lbl">{{ $etiqueta }}</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2>Últimos usuarios registrados</h2>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ultimos as $u)
                    <tr>
                        <td>{{ $u->USR_USR }}</td>
                        <td>{{ $u->DES_ROL }}</td>
                        <td><span class="badge {{ strtolower($u->EST_USR) }}">{{ $u->EST_USR }}</span></td>
                        <td>{{ $u->FECULT_USR ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection