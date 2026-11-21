@extends('layouts.admin')

@section('titulo', 'Caja')

@php
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
@endphp

@section('contenido')
    @php
        $seccion = request('seccion', 'citas');
        if (!in_array($seccion, ['citas','movimientos','examenes','medicamentos','comprobantes','cierre'], true)) { $seccion = 'citas'; }
    @endphp
    <div class="barra" aria-label="Secciones de caja">
        @foreach (['citas'=>'Citas','movimientos'=>'Movimientos','examenes'=>'Exámenes','medicamentos'=>'Medicamentos','comprobantes'=>'Comprobantes','cierre'=>'Cierre e historial'] as $clave => $nombre)
            <a class="btn {{ $seccion === $clave ? 'principal' : '' }}" href="{{ route('caja.dashboard', ['seccion'=>$clave]) }}">{{ $nombre }}</a>
        @endforeach
    </div>
    <h1>Caja</h1>

    @if (!$caja)
        <p class="sub">No tienes una caja abierta. Ábrela para registrar cobros (RN26).</p>

        <div class="card formulario">
            <form method="POST" action="{{ route('caja.abrir') }}">
                @csrf
                <label for="monto">Monto inicial en efectivo (Bs)</label>
                <input id="monto" type="number" name="monto" min="0" max="100000" step="0.01" value="{{ old('monto', '0.00') }}" required>
                <button type="submit" class="btn principal">Abrir caja</button>
            </form>
        </div>
    @else
        <p class="sub">
            Caja <strong>{{ $caja->COD_CAJ }}</strong> abierta el {{ \Carbon\Carbon::parse($caja->FECAPE_CAJ)->format('d/m/Y H:i') }}.
        </p>
        @if (!\Carbon\Carbon::parse($caja->FECAPE_CAJ)->isToday())
            <div class="alerta aviso">Esta caja corresponde a un día anterior. Ciérrala y abre una nueva antes de registrar cobros.</div>
        @endif

        <div class="grid">
            <div class="card"><div class="num">{{ $bs($caja->MONAPE_CAJ) }}</div><div class="lbl">Apertura</div></div>
            <div class="card"><div class="num">{{ $bs($resumen['total']) }}</div><div class="lbl">Cobrado (confirmado)</div></div>
            <div class="card"><div class="num">{{ $bs($resumen['esperado']) }}</div><div class="lbl">Efectivo esperado en caja</div></div>
            <div class="card"><div class="num" style="color:#b91c1c;">{{ $resumen['anulados'] }}</div><div class="lbl">Cobros anulados</div></div>
        </div>

        @if ($pendientes->isNotEmpty())
            <h2>Cobros sin comprobante</h2>
            <div class="alerta aviso">El pago está registrado, pero Quarkus no generó el comprobante. Reintenta antes de cerrar la caja.</div>
            <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
                <table>
                    <thead><tr><th>Cobro</th><th>Fecha</th><th>Paciente</th><th>Cita</th><th>Monto</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $p)
                            <tr>
                                <td>{{ $p->COD_PAG }}</td>
                                <td>{{ \Carbon\Carbon::parse($p->FEC_PAG)->format('d/m H:i') }}</td>
                                <td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</td>
                                <td>{{ $p->COD_CIT }}</td>
                                <td>{{ $bs($p->MON_PAG) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('caja.comprobante.reintentar', $p->COD_PAG) }}">
                                        @csrf
                                        <button type="submit" class="btn principal">Reintentar comprobante</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

@if ($seccion === 'citas')
        <h2>Citas con saldo por cobrar</h2>
        <form method="GET" action="{{ route('caja.dashboard') }}" class="barra"><input type="hidden" name="seccion" value="citas">
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="CI, nombre, apellido o código de cita">
            <button type="submit" class="btn">Buscar</button>
            @if ($buscar !== '')
                <a href="{{ route('caja.dashboard') }}" class="btn">Limpiar</a>
            @endif
        </form>
        <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
            <table>
                <thead><tr><th>Cita</th><th>Fecha</th><th>Paciente</th><th>Servicio</th><th>Precio</th><th>Pagado</th><th>Saldo</th><th></th></tr></thead>
                <tbody>
                    @forelse ($porCobrar as $c)
                        <tr>
                            <td>{{ $c->COD_CIT }} <div style="color: var(--suave); font-size: 12px;">{{ $c->EST_CIT }}</div></td>
                            <td>{{ \Carbon\Carbon::parse($c->FEC_CIT)->format('d/m/Y') }} {{ substr($c->HOR_CIT, 0, 5) }}</td>
                            <td>{{ $c->NOM_PAC }} {{ $c->APE_PAC }} <div style="color: var(--suave); font-size: 12px;">CI {{ $c->CI_PAC }}</div></td>
                            <td>{{ $c->NOM_SRV }} <div style="color: var(--suave); font-size: 12px;">{{ $c->TIT_EMP }} {{ $c->APE_EMP }}</div></td>
                            <td>{{ $bs($c->PRE_SRV) }}</td>
                            <td>{{ $bs($c->PAGADO) }}</td>
                            <td><strong>{{ $bs($c->SALDO) }}</strong></td>
                            <td><a href="{{ route('caja.cobrar', $c->COD_CIT) }}" class="btn principal">Cobrar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No hay citas con saldo pendiente{{ $buscar !== '' ? ' para esa búsqueda' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

@endif
@if ($seccion === 'movimientos')
        <h2>Movimientos de esta caja</h2>
        <div class="card" style="overflow-x:auto;margin-bottom:28px"><table><thead><tr><th>Fecha</th><th>Paciente</th><th>Cita / pago</th><th>Método</th><th>Descuento</th><th>Cobrado</th><th>Estado</th></tr></thead><tbody>
        @forelse($movimientos as $m)<tr><td>{{ $m->FEC_PAG }}</td><td>{{ $m->NOM_PAC }} {{ $m->APE_PAC }}</td><td>{{ $m->COD_CIT }}<br>{{ $m->COD_PAG }}</td><td>{{ $m->DES_MET }}</td><td>{{ $m->PORCENTAJE ?? 0 }} % ({{ $bs($m->DESCUENTO ?? 0) }})</td><td>{{ $bs($m->MON_PAG) }}</td><td>{{ $m->EST_PAG }}</td></tr>@empty<tr><td colspan="7">No hay movimientos en esta caja.</td></tr>@endforelse
        </tbody></table></div>
@endif
@if ($seccion === 'examenes')
        <h2>Cobro de exámenes</h2>
        <div class="card" style="overflow-x:auto;margin-bottom:24px"><table><thead><tr><th>Orden</th><th>Paciente</th><th>Examen</th><th>Tarifa</th><th>Cobrar</th></tr></thead><tbody>
        @forelse($examenes as $e)<tr><td>{{ $e->COD_ORD }}</td><td>{{ $e->NOM_PAC }} {{ $e->APE_PAC }} · CI {{ $e->CI_PAC }}</td><td>{{ $e->NOM_SRV }}</td><td>{{ $bs($e->PRE_SRV) }}</td><td><form method="post" action="{{ route('caja.examen.cobrar',$e->COD_ORD) }}">@csrf<input type="hidden" name="precio" value="{{ $e->PRE_SRV }}"><select name="metodo" required>@foreach($metodos as $m)<option value="{{ $m->COD_MET }}">{{ $m->DES_MET }}</option>@endforeach</select><button class="btn principal">Cobrar</button></form></td></tr>@empty<tr><td colspan="5">No hay exámenes pendientes para esta búsqueda.</td></tr>@endforelse
        </tbody></table></div>
        <div class="card" style="overflow-x:auto;margin-bottom:24px"><h2>Cobros de exámenes de esta caja</h2><table><tr><th>Cobro</th><th>Paciente y examen</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr>@forelse($cobrosExamen as $e)<tr><td>{{ $e->COD_CEX }} · {{ $e->FECHA }}</td><td>{{ $e->NOM_PAC }} {{ $e->APE_PAC }} · {{ $e->NOM_SRV }}</td><td>{{ $bs($e->MONTO) }} · {{ $e->DES_MET }}</td><td>{{ $e->ESTADO }} {{ $e->MOTIVO }}</td><td><a class="btn" href="{{ route('caja.examen.pdf',$e->COD_CEX) }}">Comprobante PDF</a>@if($e->ESTADO === 'Confirmado' && \Carbon\Carbon::parse($e->FECHA)->isToday())<form method="post" action="{{ route('caja.examen.anular',$e->COD_CEX) }}">@csrf<input name="motivo" minlength="10" maxlength="200" placeholder="Motivo de anulación" required><button class="btn peligro">Anular</button></form>@endif</td></tr>@empty<tr><td colspan="5">Sin cobros de exámenes.</td></tr>@endforelse</table></div>
@endif
@if ($seccion === 'medicamentos')
        <h2>Cobro y entrega de medicamentos recetados</h2>
        <div class="card" style="overflow-x:auto;margin-bottom:24px"><table><tr><th>Receta y paciente</th><th>Medicamento</th><th>Precio unitario</th><th>Pendiente</th><th>Cobrar y entregar</th></tr>@forelse($medicamentos as $m)<tr><td>{{ $m->COD_REC }} · {{ $m->NOM_PAC }} {{ $m->APE_PAC }} · CI {{ $m->CI_PAC }}</td><td>{{ $m->DES_MED }}</td><td>{{ $m->PRECIO !== null ? $bs($m->PRECIO) : 'Sin precio' }}</td><td>{{ $m->CANTIDAD - $m->CANENT_DRE }}</td><td>@if($m->PRECIO)<form method="post" action="{{ route('caja.medicamento.cobrar') }}">@csrf<input type="hidden" name="receta" value="{{ $m->COD_REC }}"><input type="hidden" name="detalle" value="{{ $m->COD_DRE }}"><input type="hidden" name="token" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="precio" value="{{ $m->PRECIO }}"><input type="number" name="cantidad" min="1" max="{{ $m->CANTIDAD - $m->CANENT_DRE }}" value="1" required oninput="this.form.querySelector('output').textContent = 'Bs ' + (Number(this.value || 0) * Number(this.form.elements.precio.value)).toFixed(2)"><output>Bs {{ number_format($m->PRECIO,2) }}</output><select name="metodo">@foreach($metodos as $met)<option value="{{ $met->COD_MET }}">{{ $met->DES_MET }}</option>@endforeach</select><button class="btn principal">Cobrar y entregar</button></form>@else Solicita registrar el precio en farmacia. @endif</td></tr>@empty<tr><td colspan="5">No hay medicamentos con cantidades confirmadas pendientes.</td></tr>@endforelse</table></div>
        <div class="card" style="overflow-x:auto;margin-bottom:24px"><h2>Cobros de medicamentos de esta caja</h2><table><tr><th>Cobro</th><th>Paciente</th><th>Medicamento</th><th>Total</th><th></th></tr>@forelse($cobrosMedicamento as $m)<tr><td>{{ $m->COD_CME }} · {{ $m->FECHA }}</td><td>{{ $m->NOM_PAC }} {{ $m->APE_PAC }}</td><td>{{ $m->DES_MED }} · {{ $m->CANTIDAD }} unidades</td><td>{{ $bs($m->MONTO) }} · {{ $m->DES_MET }}</td><td><a class="btn" href="{{ route('caja.medicamento.pdf',$m->COD_CME) }}">Comprobante PDF</a> · {{ $m->ESTADO }} @if($m->ESTADO === 'Confirmado' && \Carbon\Carbon::parse($m->FECHA)->isToday())<form method="post" action="{{ route('caja.medicamento.anular',$m->COD_CME) }}">@csrf<input name="motivo" minlength="10" maxlength="200" required placeholder="Motivo de anulación"><label><input type="checkbox" name="devuelto" value="1" required> Confirmo que se devolvieron todas las unidades y están aptas para volver al inventario.</label><button class="btn peligro">Anular y devolver stock</button></form>@endif</td></tr>@empty<tr><td colspan="5">Sin cobros de medicamentos.</td></tr>@endforelse</table></div>
@endif
@if ($seccion === 'comprobantes')
        <h2>Comprobantes de esta caja</h2>
        <div class="card" style="overflow-x: auto; margin-bottom: 28px;">
            <table>
                <thead><tr><th>N.º</th><th>Fecha</th><th>Paciente</th><th>Cita</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse ($comprobantes as $k)
                        <tr>
                            <td>{{ $k->NRO_COM }}</td>
                            <td>{{ \Carbon\Carbon::parse($k->FEC_COM)->format('d/m H:i') }}</td>
                            <td>{{ $k->NOM_PAC }} {{ $k->APE_PAC }}</td>
                            <td>{{ $k->COD_CIT }}</td>
                            <td>{{ $bs($k->MONTO_COM) }}</td>
                            <td>
                                <span class="badge {{ $k->EST_COM === 'Emitido' ? 'activo' : 'critica' }}">{{ $k->EST_COM }}</span>
                                @if ($k->MOT_ANU)
                                    <div style="color: var(--suave); font-size: 12px; max-width: 220px;">{{ $k->MOT_ANU }}</div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('caja.pdf', $k->NRO_COM) }}" target="_blank" class="btn">PDF</a>
                                @if ($k->EST_COM === 'Emitido' && \Carbon\Carbon::parse($k->FEC_COM)->isToday())
                                    <form method="POST" action="{{ route('caja.anular', $k->NRO_COM) }}" style="display: flex; gap: 6px; margin-top: 6px;"
                                          onsubmit="return confirm('¿Anular el comprobante {{ $k->NRO_COM }}? El saldo volverá a quedar pendiente.');">
                                        @csrf
                                        <input type="text" name="motivo" minlength="10" maxlength="200" placeholder="Motivo (mín. 10 caracteres)" required
                                               style="padding: 6px 8px; border: 1px solid var(--borde); border-radius: 6px; font-size: 13px;">
                                        <button type="submit" class="btn peligro">Anular</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Todavía no hay comprobantes en esta caja.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

@endif
@if ($seccion === 'cierre')
        <h2>Cierre de caja</h2>
        <div class="card formulario">
            @if ($resumen['porMetodo']->isNotEmpty())
                <table style="margin-bottom: 16px;">
                    <thead><tr><th>Método</th><th>Cobros</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($resumen['porMetodo'] as $m)
                            <tr><td>{{ $m->DES_MET }}</td><td>{{ $m->cantidad }}</td><td>{{ $bs($m->total) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <form method="POST" action="{{ route('caja.cerrar') }}"
                  onsubmit="return confirm('¿Cerrar la caja {{ $caja->COD_CAJ }}? No podrás registrar más cobros en ella.');">
                @csrf
                <label for="contado">Efectivo contado en caja (Bs). Esperado: {{ $bs($resumen['esperado']) }}</label>
                <input id="contado" type="number" name="contado" min="0" step="0.01" value="{{ old('contado') }}" required>
                <button type="submit" class="btn peligro">Cerrar caja</button>
            </form>
        </div>
@endif
    @endif
@if ($seccion === 'cierre')
    <h2 style="margin-top:28px">Historial de cierres</h2>
    <div class="card" style="overflow-x:auto"><table><thead><tr><th>Caja</th><th>Apertura</th><th>Cierre</th><th>Cobrado</th><th>Efectivo esperado</th><th>Contado</th><th>Diferencia</th></tr></thead><tbody>
    @forelse($historial as $h)<tr><td>{{ $h->COD_CAJ }}</td><td>{{ $h->FECAPE_CAJ }}<br>{{ $bs($h->MONAPE_CAJ) }}</td><td>{{ $h->FECCIE_CAJ }}</td><td>{{ $bs($h->resumen['total']) }}</td><td>{{ $bs($h->resumen['esperado']) }}</td><td>{{ $bs($h->MONCIE_CAJ) }}</td><td>{{ $bs($h->diferencia) }}</td></tr>@empty<tr><td colspan="7">Aún no tienes cierres registrados.</td></tr>@endforelse
    </tbody></table></div>
@endif
@endsection
