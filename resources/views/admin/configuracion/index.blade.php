@extends('layouts.admin')

@section('titulo', 'Configuración general')

@push('head')
<style>
    .pestanas { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid var(--borde); }
    .pestanas a { padding: 10px 16px; text-decoration: none; color: var(--suave); font-weight: 600; font-size: 14px; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .pestanas a.activo { color: var(--cies-dark); border-bottom-color: var(--naranja); }
    .matriz th, .matriz td { text-align: center; padding: 8px 6px; }
    .matriz th:first-child, .matriz td:first-child { text-align: left; }
    .matriz input { width: 18px; height: 18px; accent-color: var(--cies); }
    .en-linea { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .en-linea input, .en-linea select { padding: 6px 8px; border: 1px solid var(--borde-input); border-radius: 8px; font: inherit; font-size: 13px; }
</style>
@endpush

@section('contenido')
    <h1>Configuración general</h1>
    <p class="sub">RF043 y RF044: parámetros del sistema, especialidades, tarifas, permisos por rol y copias de seguridad. Cada cambio queda en auditoría.</p>

    <nav class="pestanas" style="padding: 0;">
        @foreach (['parametros' => 'Parámetros', 'especialidades' => 'Especialidades', 'tarifas' => 'Tarifas', 'permisos' => 'Roles y permisos', 'respaldos' => 'Respaldos'] as $k => $t)
            <a href="{{ route('admin.configuracion', ['seccion' => $k]) }}" class="{{ $seccion === $k ? 'activo' : '' }}">{{ $t }}</a>
        @endforeach
    </nav>

    @if ($seccion === 'parametros')
        <div class="card formulario ancho">
            <form method="POST" action="{{ route('admin.configuracion.parametros') }}">
                @csrf
                @foreach ($parametros as $p)
                    <label for="p-{{ $p->COD_PAR }}">{{ $p->DES_PAR }} <small style="font-weight: 400;">({{ $p->MIN_PAR }}–{{ $p->MAX_PAR }})</small></label>
                    <input id="p-{{ $p->COD_PAR }}" type="number" name="p[{{ $p->COD_PAR }}]" min="{{ $p->MIN_PAR }}" max="{{ $p->MAX_PAR }}"
                           value="{{ old('p.' . $p->COD_PAR, $p->VAL_PAR) }}" required>
                    <p class="ayuda">Último cambio: {{ \Carbon\Carbon::parse($p->FEC_PAR)->format('d/m/Y H:i') }}{{ $p->USR_USR ? ' por ' . $p->USR_USR : '' }}</p>
                @endforeach
                <button class="btn principal"><i class="fas fa-save"></i> Guardar parámetros</button>
            </form>
        </div>
    @elseif ($seccion === 'especialidades')
        <div class="card tabla">
            <form method="POST" action="{{ route('admin.configuracion.especialidad') }}" class="en-linea" style="margin-bottom: 16px;">
                @csrf
                <input name="descripcion" maxlength="40" required placeholder="Nueva especialidad">
                <button class="btn principal sm"><i class="fas fa-plus"></i> Agregar</button>
            </form>
            <table>
                <thead><tr><th>Código</th><th>Especialidad</th><th>Médicos</th></tr></thead>
                <tbody>
                @foreach ($especialidades as $e)
                    <tr>
                        <td>{{ $e->COD_ESP }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.configuracion.especialidad') }}" class="en-linea">
                                @csrf <input type="hidden" name="codigo" value="{{ $e->COD_ESP }}">
                                <input name="descripcion" maxlength="40" required value="{{ $e->DES_ESP }}" style="min-width: 260px;">
                                <button class="btn sm">Guardar</button>
                            </form>
                        </td>
                        <td>{{ $e->MEDICOS }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($seccion === 'tarifas')
        <div class="card tabla" style="margin-bottom: 24px;">
            <h2><i class="fas fa-tags"></i> Servicios y tarifas</h2>
            <form method="POST" action="{{ route('admin.configuracion.servicio') }}" class="en-linea" style="margin-bottom: 16px;">
                @csrf
                <input name="nombre" maxlength="50" required placeholder="Nuevo servicio">
                <input type="number" name="precio" step="0.01" min="0" required placeholder="Bs" style="width: 100px;">
                <select name="clasificacion">@foreach ($clasificaciones as $c)<option value="{{ $c->COD_CLA }}">{{ $c->DES_CLA }}</option>@endforeach</select>
                <button class="btn principal sm"><i class="fas fa-plus"></i> Agregar</button>
            </form>
            <table>
                <thead><tr><th>Código</th><th>Servicio · clasificación · precio (Bs)</th></tr></thead>
                <tbody>
                @foreach ($servicios as $s)
                    <tr>
                        <td>{{ $s->COD_SRV }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.configuracion.servicio') }}" class="en-linea">
                                @csrf <input type="hidden" name="codigo" value="{{ $s->COD_SRV }}">
                                <input name="nombre" maxlength="50" required value="{{ $s->NOM_SRV }}" style="min-width: 280px;">
                                <select name="clasificacion">@foreach ($clasificaciones as $c)<option value="{{ $c->COD_CLA }}" @selected($c->COD_CLA === $s->COD_CLA)>{{ $c->DES_CLA }}</option>@endforeach</select>
                                <input type="number" name="precio" step="0.01" min="0" required value="{{ $s->PRE_SRV }}" style="width: 100px;">
                                <button class="btn sm">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card tabla">
            <h2><i class="fas fa-pills"></i> Precio de venta de medicamentos (farmacia)</h2>
            <table>
                <tbody>
                @foreach ($medicamentos as $m)
                    <tr>
                        <td>{{ $m->COD_MED }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.configuracion.medicamento') }}" class="en-linea">
                                @csrf <input type="hidden" name="codigo" value="{{ $m->COD_MED }}">
                                <span style="min-width: 260px; display: inline-block;">{{ $m->DES_MED }}</span>
                                <input type="number" name="precio" step="0.01" min="0" required value="{{ $m->PRE_MED }}" style="width: 100px;">
                                <button class="btn sm">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($seccion === 'permisos')
        <div class="card tabla">
            <p class="ayuda" style="margin-top: 0;">RNF001: cada rol entra solo a su panel y, dentro, a los módulos marcados. Quitar un permiso bloquea esas pantallas con error 403. El permiso de configuración del administrador no se puede quitar.</p>
            <form method="POST" action="{{ route('admin.configuracion.permisos') }}">
                @csrf
                <table class="matriz">
                    <thead>
                        <tr><th>Módulo</th>@foreach ($roles as $r)<th title="{{ $r->DES_ROL }}">{{ \Illuminate\Support\Str::limit($r->DES_ROL, 6, '.') }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @foreach ($modulos as $cod => $m)
                        <tr>
                            <td>{{ $m['nombre'] }} <small>{{ $cod }}</small></td>
                            @foreach ($roles as $r)
                                @php $prot = in_array($cod, $protegidos[$r->COD_ROL] ?? [], true); @endphp
                                <td><input type="checkbox" name="m[{{ $r->COD_ROL }}][]" value="{{ $cod }}"
                                           @checked(in_array($cod, $asignados[$r->COD_ROL] ?? [], true) || $prot) @disabled($prot)
                                           title="{{ $r->DES_ROL }} · {{ $m['nombre'] }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button class="btn principal" style="margin-top: 16px;"><i class="fas fa-save"></i> Guardar permisos</button>
            </form>
        </div>
    @elseif ($seccion === 'respaldos')
        <div class="card tabla">
            <div class="card-cabecera">
                <p class="ayuda" style="margin: 0;">RN48: copia completa automática cada día a las 02:00, se conservan {{ config('cies.respaldo_retencion_dias') }} días y la prueba de restauración corre el día 1 de cada mes. La prueba restaura la copia en una base aparte y compara las filas tabla por tabla.</p>
                <form method="POST" action="{{ route('admin.configuracion.respaldos.crear') }}" onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    <button class="btn principal"><i class="fas fa-database"></i> Crear respaldo ahora</button>
                </form>
            </div>
            @if ($respaldos->isEmpty())
                <div class="vacio"><i class="fas fa-database"></i> Todavía no hay respaldos.</div>
            @else
                <table>
                    <thead><tr><th>Código</th><th>Fecha</th><th>Tipo</th><th>Contenido</th><th>Prueba de restauración</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($respaldos as $r)
                        <tr>
                            <td>{{ $r->COD_RES }}<br><small title="{{ $r->HAS_RES }}">SHA-256 {{ substr($r->HAS_RES, 0, 12) }}…</small></td>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($r->FEC_RES)->format('d/m/Y H:i') }}</td>
                            <td>{{ $r->TIP_RES }}{{ $r->USR_USR ? ' · ' . $r->USR_USR : '' }} <span class="badge {{ $r->EST_RES === 'Vigente' ? 'activo' : '' }}">{{ $r->EST_RES }}</span></td>
                            <td>{{ $r->TAB_RES }} tablas · {{ number_format($r->FIL_RES) }} filas<br><small>{{ number_format($r->TAM_RES / 1024, 1) }} KB</small></td>
                            <td>
                                @if ($r->FECPRU_RES)
                                    <span class="badge {{ str_starts_with((string) $r->RESPRU_RES, 'Restauración correcta') ? 'activo' : 'critica' }}">{{ \Carbon\Carbon::parse($r->FECPRU_RES)->format('d/m/Y H:i') }}</span>
                                    <br><small>{{ $r->RESPRU_RES }}</small>
                                @else
                                    <small>Sin probar</small>
                                @endif
                            </td>
                            <td>
                                @if ($r->EST_RES === 'Vigente')
                                    <div class="acciones">
                                        <form method="POST" action="{{ route('admin.configuracion.respaldos.probar', $r->COD_RES) }}" onsubmit="this.querySelector('button').disabled = true;">
                                            @csrf <button class="btn sm"><i class="fas fa-vial-circle-check"></i> Probar restauración</button>
                                        </form>
                                        <a class="btn sm" href="{{ route('admin.configuracion.respaldos.descargar', $r->COD_RES) }}"><i class="fas fa-download"></i></a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
@endsection
