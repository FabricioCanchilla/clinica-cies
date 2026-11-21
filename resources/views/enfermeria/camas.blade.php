@extends('layouts.admin')

@section('titulo', 'Camas e internación')

@push('head')
<style>
    .camas { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .cama { background: #fff; border: 1px solid var(--borde); border-radius: 14px; padding: 16px; border-top: 6px solid var(--cies); }
    .cama.Ocupada { border-top-color: #e11d48; }
    .cama.Limpieza { border-top-color: #eab308; }
    .cama.aislada { box-shadow: 0 0 0 2px #e11d48 inset; }
    .cama .cab { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .cama .nro { font-size: 20px; font-weight: 800; color: var(--titulo); }
    .cama .est { font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 99px; }
    .est.Disponible { background: #dcebe1; color: #2d4c39; }
    .est.Ocupada { background: #fee2e2; color: #b91c1c; }
    .est.Limpieza { background: #fef3c7; color: #92400e; }
    .cama p { font-size: 13px; color: var(--texto); margin-bottom: 6px; line-height: 1.45; }
    .cama small { color: var(--suave); }
    .cama details { margin-top: 8px; border-top: 1px dashed var(--borde); padding-top: 8px; }
    .cama summary { cursor: pointer; font-size: 13px; font-weight: 700; color: var(--cies-dark); }
    .cama form { margin-top: 8px; }
    .cama select, .cama input[type=text], .cama textarea { width: 100%; padding: 7px 9px; margin-bottom: 8px; border: 1px solid var(--borde-input); border-radius: 8px; font: inherit; font-size: 13px; }
    .tarea { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px; margin-top: 8px; font-size: 13px; }
    .tarea.Terminal { background: #fef2f2; border-color: #fecaca; }
    .tarea label { display: flex; gap: 8px; align-items: flex-start; margin: 5px 0; font-size: 13px; }
    .tag-ais { display: inline-block; background: #e11d48; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; }
    .reglas li { margin: 4px 0 4px 18px; font-size: 13px; color: var(--texto); }
</style>
@endpush

@section('contenido')
    <h1>Camas e internación</h1>
    <p class="sub">Una cama solo se asigna si está disponible y limpia. Tras cada alta, o si la ropa de cama está sucia, la cama sale de servicio hasta completar su limpieza.</p>

    <div class="grid">
        @foreach (['Disponible' => '#3b6449', 'Ocupada' => '#b91c1c', 'Limpieza' => '#92400e'] as $est => $color)
            <div class="card"><div class="num" style="color: {{ $color }};">{{ $porEstado[$est] ?? 0 }}</div><div class="lbl">Camas «{{ $est }}»</div></div>
        @endforeach
        <div class="card"><div class="num" style="color:#b91c1c;">{{ $camas->filter(fn ($c) => !empty($c->AIS_INT))->count() }}</div><div class="lbl">Pacientes en aislamiento</div></div>
    </div>

    <details class="card" style="margin-bottom: 22px;">
        <summary style="cursor:pointer; font-weight:700;"><i class="fas fa-shield-virus" style="color:var(--cies);"></i> Reglas de bioseguridad que aplica el sistema</summary>
        <ul class="reglas" style="margin-top: 10px;">
            <li><strong>Limpieza rutinaria</strong> (alta normal, ropa sucia o manchada): cambio de sábanas y ropa de cama, desinfección de superficies y barandas, limpieza del colchón y la almohada.</li>
            <li><strong>Desinfección terminal</strong> (alta de un paciente con aislamiento como COVID-19, derrame de fluidos o contacto con un paciente infeccioso): todo lo anterior, ventilación del ambiente y al menos <strong>{{ $minTerminal }} minutos</strong> de acción del desinfectante. Antes de ese tiempo el sistema no libera la cama.</li>
            <li>Si se reporta un derrame en una cama que ya tenía una limpieza rutinaria pendiente, esa limpieza pasa a ser terminal.</li>
            <li>Ropa sucia en una cama ocupada genera una tarea de cambio; si el paciente está en aislamiento, se indica usar EPP y tratar la ropa como contaminada.</li>
        </ul>
    </details>

    <div class="camas">
        @foreach ($camas as $c)
            <div class="cama {{ $c->EST_CAM }} {{ !empty($c->AIS_INT) ? 'aislada' : '' }}">
                <div class="cab">
                    <span class="nro"><i class="fas fa-bed" style="color: var(--tenue); font-size: 16px;"></i> Cama {{ $c->NRO_CAM }}</span>
                    <span class="est {{ $c->EST_CAM }}">{{ $c->EST_CAM }}</span>
                </div>
                <p><small>{{ $c->NOM_SAL }} · {{ $c->UBI_SAL }}</small></p>

                @if ($c->COD_INT)
                    <p>
                        <strong>{{ $c->NOM_PAC }} {{ $c->APE_PAC }}</strong> <small>CI {{ $c->CI_PAC }}</small><br>
                        <small>Desde {{ \Carbon\Carbon::parse($c->FECING_INT)->format('d/m/Y H:i') }} · {{ $c->TIT_EMP }} {{ $c->APE_EMP }}</small><br>
                        {{ $c->MOT_INT }}
                    </p>
                    @if (!empty($c->AIS_INT))
                        <p><span class="tag-ais"><i class="fas fa-virus"></i> Aislamiento: {{ $c->AIS_INT }}</span></p>
                    @endif
                @endif

                {{-- Limpiezas abiertas --}}
                @foreach ($c->LIMPIEZAS as $l)
                    <div class="tarea {{ $l->TIP_LIM }}">
                        <strong><i class="fas fa-{{ $l->TIP_LIM === 'Terminal' ? 'biohazard' : 'broom' }}"></i> Limpieza {{ strtolower($l->TIP_LIM) }} · {{ $l->EST_LIM }}</strong><br>
                        <small>{{ $l->MOT_LIM }} · pedida {{ \Carbon\Carbon::parse($l->FECSOL_LIM)->format('d/m H:i') }}</small>
                        @if ($l->OBS_LIM)<br><small>{{ $l->OBS_LIM }}</small>@endif

                        @if ($l->EST_LIM === 'Pendiente')
                            <form method="POST" action="{{ route('enfermeria.limpiezas.iniciar', $l->COD_LIM) }}">
                                @csrf
                                <button class="btn sm principal"><i class="fas fa-play"></i> Iniciar limpieza</button>
                            </form>
                        @else
                            <br><small>En proceso desde {{ \Carbon\Carbon::parse($l->FECINI_LIM)->format('H:i') }}@if ($l->EJE_NOM) por {{ $l->EJE_NOM }} {{ $l->EJE_APE }}@endif</small>
                            @if ($l->TIP_LIM === 'Terminal')
                                <br><small style="color:#b91c1c; font-weight:700;">
                                    @if ($l->FALTAN_MIN > 0)
                                        <i class="fas fa-hourglass-half"></i> Ventilación y desinfectante: faltan {{ $l->FALTAN_MIN }} min (lista a las {{ $l->LISTO_EN }})
                                    @else
                                        <i class="fas fa-check"></i> Tiempo mínimo cumplido
                                    @endif
                                </small>
                            @endif
                            <form method="POST" action="{{ route('enfermeria.limpiezas.completar', $l->COD_LIM) }}">
                                @csrf
                                <label><input type="checkbox" name="sabanas" value="1" @checked($l->SAB_LIM)> Sábanas y ropa de cama retiradas y cambiadas{{ $l->TIP_LIM === 'Terminal' ? ' (embolsadas como contaminadas)' : '' }}</label>
                                <label><input type="checkbox" name="superficies" value="1" @checked($l->SUP_LIM)> Superficies, barandas y velador desinfectados{{ $l->TIP_LIM === 'Terminal' ? ' con hipoclorito al 0,5 %' : '' }}</label>
                                <label><input type="checkbox" name="colchon" value="1" @checked($l->COL_LIM)> Colchón y almohada limpios, funda cambiada</label>
                                @if ($l->TIP_LIM === 'Terminal')
                                    <label><input type="checkbox" name="ventilacion" value="1" @checked($l->VEN_LIM)> Ambiente ventilado (ventanas abiertas / recambio de aire)</label>
                                @endif
                                <input type="text" name="observacion" maxlength="150" placeholder="Observación (opcional)">
                                <button class="btn sm principal"><i class="fas fa-check-double"></i> Completar y liberar</button>
                            </form>
                        @endif
                    </div>
                @endforeach

                {{-- Acciones según el estado --}}
                @if ($c->EST_CAM === 'Disponible' && $c->LIMPIEZAS->isEmpty())
                    <details>
                        <summary><i class="fas fa-user-plus"></i> Internar paciente</summary>
                        <form method="POST" action="{{ route('enfermeria.camas.internar', $c->COD_CAM) }}">
                            @csrf
                            <select name="paciente" required>
                                <option value="">Paciente…</option>
                                @foreach ($pacientes as $p)
                                    <option value="{{ $p->COD_PAC }}">{{ $p->APE_PAC }}, {{ $p->NOM_PAC }} · CI {{ $p->CI_PAC }}</option>
                                @endforeach
                            </select>
                            <select name="medico" required>
                                <option value="">Médico tratante…</option>
                                @foreach ($medicos as $m)
                                    <option value="{{ $m->COD_EMP }}">{{ $m->TIT_EMP }} {{ $m->NOM_EMP }} {{ $m->APE_EMP }} · {{ $m->DES_ESP }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="motivo" maxlength="200" placeholder="Motivo de la internación" required>
                            <select name="aislamiento">
                                <option value="">Sin aislamiento</option>
                                @foreach ($aislamientos as $a)<option value="{{ $a }}">Aislamiento {{ $a }}</option>@endforeach
                            </select>
                            <button class="btn sm principal"><i class="fas fa-bed"></i> Internar</button>
                        </form>
                    </details>
                @endif

                @if ($c->COD_INT)
                    <details>
                        <summary><i class="fas fa-virus"></i> Aislamiento del paciente</summary>
                        <form method="POST" action="{{ route('enfermeria.internaciones.aislamiento', $c->COD_INT) }}">
                            @csrf
                            <select name="aislamiento">
                                <option value="">Sin aislamiento</option>
                                @foreach ($aislamientos as $a)<option value="{{ $a }}" @selected($c->AIS_INT === $a)>{{ $a }}</option>@endforeach
                            </select>
                            <button class="btn sm"><i class="fas fa-save"></i> Guardar</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('enfermeria.internaciones.alta', $c->COD_INT) }}"
                          onsubmit="return confirm('¿Dar de alta al paciente? La cama quedará fuera de servicio hasta completar su limpieza{{ !empty($c->AIS_INT) ? ' TERMINAL' : '' }}.');">
                        @csrf
                        <button class="btn sm acento"><i class="fas fa-door-open"></i> Dar de alta</button>
                    </form>
                @endif

                <details>
                    <summary><i class="fas fa-triangle-exclamation"></i> Reportar ropa sucia o contaminación</summary>
                    <form method="POST" action="{{ route('enfermeria.camas.reportar', $c->COD_CAM) }}">
                        @csrf
                        <select name="motivo" required>
                            @foreach ($motivos as $m => $tipo)<option value="{{ $m }}">{{ $m }} → limpieza {{ strtolower($tipo) }}</option>@endforeach
                        </select>
                        <input type="text" name="observacion" maxlength="150" placeholder="Detalle (opcional)">
                        <button class="btn sm peligro"><i class="fas fa-flag"></i> Reportar</button>
                    </form>
                </details>
            </div>
        @endforeach
    </div>

    <h2>Últimas limpiezas completadas</h2>
    <div class="card tabla">
        <table>
            <thead><tr><th>Cama</th><th>Tipo</th><th>Motivo</th><th>Pedida</th><th>Liberada</th><th class="derecha">Tiempo fuera de servicio</th><th>Realizó</th></tr></thead>
            <tbody>
                @forelse ($historial as $h)
                    <tr>
                        <td>{{ $h->NRO_CAM }}</td>
                        <td>{{ $h->TIP_LIM }}</td>
                        <td>{{ $h->MOT_LIM }}</td>
                        <td>{{ \Carbon\Carbon::parse($h->FECSOL_LIM)->format('d/m H:i') }}</td>
                        <td>{{ \Carbon\Carbon::parse($h->FECFIN_LIM)->format('d/m H:i') }}</td>
                        <td class="derecha">{{ (int) \Carbon\Carbon::parse($h->FECSOL_LIM)->diffInMinutes(\Carbon\Carbon::parse($h->FECFIN_LIM)) }} min</td>
                        <td>{{ $h->NOM_EMP }} {{ $h->APE_EMP }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="vacio">Todavía no hay limpiezas completadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
