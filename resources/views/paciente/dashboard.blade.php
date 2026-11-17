@extends('layouts.admin')

@section('titulo', 'Mi espacio de salud')

@push('head')
<style>
    /* Cada opción del menú lateral muestra su sección (sin JS se ven todas) */
    .js .seccion-paciente { display: none; }
    .js .seccion-paciente.visible { display: block; }
    .seccion-paciente { margin-bottom: 24px; scroll-margin-top: 90px; }
</style>
<script>document.documentElement.classList.add('js');</script>
@endpush

@section('contenido')
@php
    $proximas = $citas->whereIn('EST_CIT', ['Confirmada', 'Pendiente de pago'])->count();
    $porPagar = $citas->where('EST_CIT', 'Pendiente de pago')->count();
@endphp

{{-- ===================== INICIO ===================== --}}
<section class="seccion-paciente" id="inicio">
    <div class="bienvenida">
        <img src="{{ asset('images/logo-cies-icono.png') }}" alt="">
        <div>
            <h1>¡Bienvenido, {{ $paciente ? $paciente->NOM_PAC : auth()->user()->USR_USR }}!</h1>
            <p>Este es tu espacio personal de salud en la Clínica CIES. Desde aquí agendas y pagas tus citas,
               revisas tu historial, tus recetas y tus resultados de laboratorio.</p>
        </div>
    </div>

    @if ($porPagar > 0)
        <div class="alerta info">
            <i class="fas fa-lock"></i>
            Tienes {{ $porPagar }} {{ $porPagar === 1 ? 'cita pendiente' : 'citas pendientes' }} de pago. Paga el anticipo antes de que se libere el horario.
            <a href="#mis-citas" data-ir="mis-citas"><strong>Ver mis citas</strong></a>
        </div>
    @endif

    <div class="accesos">
        <a href="#mis-citas" data-ir="mis-citas" class="acceso">
            <div class="ico"><i class="fas fa-calendar-check"></i></div>
            <div><strong>Mis citas</strong><span>Agenda, paga, cancela o reprograma tus citas.</span>
                <span class="cifra">{{ $proximas }} {{ $proximas === 1 ? 'próxima' : 'próximas' }}</span></div>
        </a>
        <a href="#mi-historial" data-ir="mi-historial" class="acceso">
            <div class="ico"><i class="fas fa-file-medical"></i></div>
            <div><strong>Mi historial médico</strong><span>Consultas, médicos tratantes y diagnósticos.</span>
                <span class="cifra">{{ $historial->count() }} {{ $historial->count() === 1 ? 'consulta' : 'consultas' }}</span></div>
        </a>
        <a href="#mis-recetas" data-ir="mis-recetas" class="acceso">
            <div class="ico"><i class="fas fa-prescription"></i></div>
            <div><strong>Mis recetas</strong><span>Consulta y descarga tus recetas en PDF.</span>
                <span class="cifra">{{ $recetas->count() }} {{ $recetas->count() === 1 ? 'receta' : 'recetas' }}</span></div>
        </a>
        <a href="#mis-resultados" data-ir="mis-resultados" class="acceso">
            <div class="ico"><i class="fas fa-vials"></i></div>
            <div><strong>Mis resultados</strong><span>Resultados de laboratorio publicados.</span>
                <span class="cifra">{{ $resultados->count() }} {{ $resultados->count() === 1 ? 'resultado' : 'resultados' }}</span></div>
        </a>
        <a href="#mis-pagos" data-ir="mis-pagos" class="acceso">
            <div class="ico"><i class="fas fa-receipt"></i></div>
            <div><strong>Mis pagos</strong><span>Anticipos y saldos con sus comprobantes.</span>
                <span class="cifra">{{ $pagos->count() }} {{ $pagos->count() === 1 ? 'pago' : 'pagos' }}</span></div>
        </a>
        <a href="#mi-perfil" data-ir="mi-perfil" class="acceso">
            <div class="ico"><i class="fas fa-user-circle"></i></div>
            <div><strong>Mi perfil</strong><span>Consulta y actualiza tus datos de contacto.</span></div>
        </a>
    </div>
</section>

{{-- ===================== MIS CITAS (RF007, RF008, RF009) ===================== --}}
<section class="seccion-paciente card" id="mis-citas">
    <div class="card-cabecera">
        <h2><i class="fas fa-calendar-check"></i> Mis citas médicas</h2>
        <a href="{{ route('paciente.citas.crear') }}" class="btn principal"><i class="fas fa-calendar-plus"></i> Agendar nueva cita</a>
    </div>

    @if ($citas->isEmpty())
        <div class="vacio"><i class="fas fa-calendar"></i> No tienes citas médicas registradas.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Médico</th>
                        <th>Servicio</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($citas as $cita)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }}</td>
                            <td>{{ substr($cita->HOR_CIT, 0, 5) }}</td>
                            <td>{{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}</td>
                            <td>{{ $cita->NOM_SRV }}</td>
                            <td>{{ $cita->MOT_CIT ?? 'Sin motivo registrado' }}</td>
                            <td>
                                <span class="badge estado-{{ \Illuminate\Support\Str::slug($cita->EST_CIT) }}">{{ $cita->EST_CIT }}</span>
                            </td>
                            <td>
                                <div class="acciones">
                                    @if ($cita->EST_CIT === 'Pendiente de pago' && $cita->FECEXP_CIT && \Carbon\Carbon::parse($cita->FECEXP_CIT)->isFuture())
                                        <a href="{{ route('paciente.citas.pagar', $cita->COD_CIT) }}" class="btn sm acento">
                                            <i class="fas fa-lock"></i> Pagar anticipo
                                        </a>
                                    @endif

                                    @if ($cita->NRO_COM_ANT)
                                        <a href="{{ route('paciente.citas.comprobante', $cita->COD_CIT) }}" target="_blank" class="btn sm">
                                            <i class="fas fa-file-pdf"></i> {{ $cita->NRO_COM_ANT }}
                                        </a>
                                    @elseif ((int) $cita->ANTICIPOS > 0)
                                        <form method="POST" action="{{ route('paciente.citas.comprobante.reintentar', $cita->COD_CIT) }}">
                                            @csrf
                                            <button type="submit" class="btn sm aviso"><i class="fas fa-rotate-right"></i> Reintentar comprobante</button>
                                        </form>
                                    @endif

                                    {{-- RF009: cancelar o reprogramar (RN21, RN22) --}}
                                    @if (in_array($cita->EST_CIT, ['Confirmada', 'Pendiente de pago'], true))
                                        @php
                                            $horasCita = now()->diffInMinutes(\Carbon\Carbon::parse($cita->FEC_CIT . ' ' . $cita->HOR_CIT), false) / 60;
                                        @endphp
                                        @if ($cita->EST_CIT === 'Confirmada' && $horasCita >= 24)
                                            <a href="{{ route('paciente.citas.reprogramar', $cita->COD_CIT) }}" class="btn sm">
                                                <i class="fas fa-calendar-alt"></i> Reprogramar
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('paciente.citas.cancelar', $cita->COD_CIT) }}"
                                              onsubmit="return confirm('{{ $cita->EST_CIT === 'Confirmada' && $horasCita < 24 ? 'Faltan menos de 24 horas: si cancelas, el anticipo NO se devuelve (RN21). ¿Cancelar de todos modos?' : '¿Seguro que quieres cancelar esta cita?' }}');">
                                            @csrf
                                            <button type="submit" class="btn sm peligro"><i class="fas fa-times"></i> Cancelar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ayuda" style="margin: 12px 0 0;">Puedes reprogramar hasta 24 horas antes y como máximo 2 veces (RN22). Si cancelas con menos de 24 horas, el anticipo no se devuelve (RN21).</p>
    @endif
</section>

{{-- ===================== MI HISTORIAL (RF012) ===================== --}}
<section class="seccion-paciente card" id="mi-historial">
    <div class="card-cabecera">
        <h2><i class="fas fa-file-medical"></i> Mi historial médico</h2>
    </div>

    @if ($historial->isEmpty())
        <div class="vacio"><i class="fas fa-notes-medical"></i> No tienes consultas médicas registradas.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Médico tratante</th><th>Diagnóstico</th><th>Tratamiento</th></tr>
                </thead>
                <tbody>
                    @foreach ($historial as $consulta)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($consulta->FEC_CNS)->format('d/m/Y H:i') }}</td>
                            <td>
                                {{ $consulta->TIT_EMP }} {{ $consulta->NOM_EMP }} {{ $consulta->APE_EMP }}
                                @if ($consulta->DES_ESP)<br><small>{{ $consulta->DES_ESP }}</small>@endif
                            </td>
                            <td>{{ $consulta->DIA_CNS ?? 'No registrado' }}</td>
                            <td>{{ $consulta->PLA_CNS ?? 'No registrado' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ===================== MIS RECETAS (RF011) ===================== --}}
<section class="seccion-paciente card" id="mis-recetas">
    <div class="card-cabecera">
        <h2><i class="fas fa-prescription"></i> Mis recetas médicas</h2>
    </div>

    @if ($recetas->isEmpty())
        <div class="vacio"><i class="fas fa-prescription-bottle"></i> No tienes recetas médicas registradas.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Estado</th><th>Observaciones</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($recetas as $receta)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($receta->FEC_REC)->format('d/m/Y') }}</td>
                            <td><span class="badge">{{ $receta->EST_REC }}</span></td>
                            <td>{{ $receta->OBS_REC ?? 'Sin observaciones' }}</td>
                            <td class="derecha">
                                <a href="{{ route('paciente.recetas.pdf', $receta->COD_REC) }}" target="_blank" class="btn sm">
                                    <i class="fas fa-file-pdf"></i> Descargar PDF
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ===================== MIS RESULTADOS (RF010) ===================== --}}
<section class="seccion-paciente card" id="mis-resultados">
    <div class="card-cabecera">
        <h2><i class="fas fa-vials"></i> Mis resultados de laboratorio</h2>
    </div>

    @if ($resultados->isEmpty())
        <div class="vacio"><i class="fas fa-flask"></i> No tienes resultados publicados.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Examen</th><th>Resultado</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($resultados as $r)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($r->FEC_RES)->format('d/m/Y H:i') }}</td>
                            <td>{{ $r->NOM_SRV }}<br><small>Orden {{ $r->COD_ORD }}</small></td>
                            <td>
                                {{ $r->DES_RES }}
                                @if ($r->CRI_RES)
                                    <span class="badge critica">Valor crítico</span>
                                @endif
                            </td>
                            <td class="derecha">
                                <button type="button" class="btn sm" onclick="imprimirResultado(this)">
                                    <i class="fas fa-download"></i> Descargar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ===================== MIS PAGOS (RF013) ===================== --}}
<section class="seccion-paciente card" id="mis-pagos">
    <div class="card-cabecera">
        <h2><i class="fas fa-receipt"></i> Mis pagos</h2>
    </div>

    @if ($pagos->isEmpty())
        <div class="vacio"><i class="fas fa-wallet"></i> No tienes pagos registrados.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th><th>Cita / servicio</th><th>Tipo</th><th class="derecha">Monto (Bs)</th>
                        <th>Método</th><th>Estado</th><th>Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagos as $p)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($p->FEC_PAG)->format('d/m/Y H:i') }}</td>
                            <td>{{ $p->COD_CIT }}<br><small>{{ $p->NOM_SRV }}</small></td>
                            <td>{{ $p->TIP_PAG === 'Adelanto' ? 'Anticipo' : 'Saldo' }}</td>
                            <td class="derecha"><strong>{{ number_format($p->MON_PAG, 2) }}</strong></td>
                            <td>{{ $p->DES_MET }}</td>
                            <td><span class="badge">{{ $p->EST_PAG }}</span></td>
                            <td>
                                @if ($p->TIP_PAG === 'Adelanto' && !$p->COD_CAJ)
                                    <a href="{{ route('paciente.citas.comprobante', $p->COD_CIT) }}" target="_blank" class="btn sm">
                                        <i class="fas fa-file-pdf"></i> Ver PDF
                                    </a>
                                @else
                                    <small>Emitido en caja</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ayuda" style="margin: 12px 0 0;">El saldo de cada cita (precio menos anticipo) se paga en caja antes de la atención (RN24).</p>
    @endif
</section>

{{-- ===================== INSUMOS DE FAMILIARES (RF016) ===================== --}}
<section class="seccion-paciente card" id="mis-insumos">
    <div class="card-cabecera">
        <h2><i class="fas fa-hand-holding-medical"></i> Insumos entregados por familiares</h2>
    </div>

    @if ($insumos->isEmpty())
        <div class="vacio"><i class="fas fa-box-open"></i> No hay insumos entregados por tus familiares.</div>
    @else
        <div class="tabla">
            <table>
                <thead>
                    <tr><th>Comprobante</th><th>Fecha</th><th>Medicamento</th><th>Cantidad</th><th>Entregado por</th></tr>
                </thead>
                <tbody>
                    @foreach ($insumos as $c)
                        <tr>
                            <td>
                                <a href="{{ route('paciente.insumos.comprobante', $c->NRO_CEN) }}" target="_blank" class="btn sm">
                                    <i class="fas fa-file-pdf"></i> {{ $c->NRO_CEN }}
                                </a>
                            </td>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($c->FEC_CEN)->format('d/m/Y H:i') }}</td>
                            <td>{{ $c->DES_MED }} {{ $c->DOS_INS }}</td>
                            <td>{{ $c->CAN_INS }}</td>
                            <td>{{ $c->NOM_ENT }} {{ $c->APE_ENT }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ===================== MI PERFIL (RF014) ===================== --}}
<section class="seccion-paciente card" id="mi-perfil">
    <div class="card-cabecera">
        <h2><i class="fas fa-user-circle"></i> Mi perfil</h2>
    </div>

    @if ($paciente)
        <div class="resumen" style="max-width: 760px;">
            <div><span>Código de paciente</span><span>{{ $paciente->COD_PAC }}</span></div>
            <div><span>CI</span><span>{{ $paciente->CI_PAC }}</span></div>
            <div><span>Nombre completo</span><span>{{ $paciente->NOM_PAC }} {{ $paciente->APE_PAC }}</span></div>
            <div><span>Fecha de nacimiento</span><span>{{ \Carbon\Carbon::parse($paciente->FECNAC_PAC)->format('d/m/Y') }}</span></div>
        </div>
        <p class="ayuda" style="margin-top: -8px;">Para corregir tu nombre, CI o fecha de nacimiento acércate a recepción.</p>

        <form method="POST" action="{{ route('paciente.perfil.actualizar') }}" class="formulario ancho">
            @csrf
            <div class="fila">
                <div>
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="8" required value="{{ old('telefono', $paciente->TEL_PAC) }}">
                </div>
                <div>
                    <label for="correo">Correo</label>
                    <input type="email" id="correo" name="correo" maxlength="60" required value="{{ old('correo', $paciente->COR_PAC) }}">
                </div>
            </div>
            <label for="estciv">Estado civil</label>
            <select id="estciv" name="estciv" required>
                @foreach (['Soltero','Casado','Divorciado','Viudo','Concubino'] as $ec)
                    <option value="{{ $ec }}" @selected(old('estciv', $paciente->ESTCIV_PAC) === $ec)>{{ $ec }}</option>
                @endforeach
            </select>
            <label for="direccion">Dirección</label>
            <input type="text" id="direccion" name="direccion" maxlength="100" required value="{{ old('direccion', $paciente->DIR_PAC) }}">
            <button type="submit" class="btn principal"><i class="fas fa-save"></i> Guardar cambios</button>
        </form>
    @else
        <div class="vacio"><i class="fas fa-user-slash"></i> Tu cuenta todavía no tiene un paciente asociado. Contacta con administración para verificar tus datos.</div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    // Navegación del panel: el menú lateral muestra una sección a la vez, igual que en los demás módulos.
    (function () {
        document.documentElement.classList.add('js');
        const secciones = Array.from(document.querySelectorAll('.seccion-paciente'));
        const enlaces = document.querySelectorAll('nav a[data-seccion]');
        const ids = secciones.map(s => s.id);

        function mostrar(id) {
            if (!ids.includes(id)) id = 'inicio';
            secciones.forEach(s => s.classList.toggle('visible', s.id === id));
            // En Inicio también se ven las citas, que es lo que más usa el paciente
            if (id === 'inicio') document.getElementById('mis-citas').classList.add('visible');
            enlaces.forEach(a => a.classList.toggle('activo', a.dataset.seccion === id));
            window.scrollTo({ top: 0 });
        }

        enlaces.forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            history.replaceState(null, '', '#' + a.dataset.seccion);
            mostrar(a.dataset.seccion);
        }));
        document.querySelectorAll('[data-ir]').forEach(a => a.addEventListener('click', e => {
            e.preventDefault();
            history.replaceState(null, '', '#' + a.dataset.ir);
            mostrar(a.dataset.ir);
        }));
        window.addEventListener('hashchange', () => mostrar(location.hash.slice(1)));
        mostrar(location.hash.slice(1));
    })();

    // RF010: descarga del resultado como PDF desde el diálogo de impresión del navegador
    function imprimirResultado(btn) {
        const fila = btn.closest('tr').cloneNode(true);
        fila.lastElementChild.remove();
        const w = window.open('', '_blank');
        w.document.write(`<html><head><title>Resultado de laboratorio | Clínica CIES</title>
            <style>body{font-family:Segoe UI,Arial,sans-serif;padding:30px;color:#0f172a}
            .cab{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #cc783c;padding-bottom:12px;margin-bottom:18px}
            .cab img{height:60px}h2{color:#3b6449;margin:0}
            td{padding:10px;border:1px solid #cbd5e1;vertical-align:top}</style></head><body>
            <div class="cab"><img src="{{ asset('images/logo-cies.png') }}"><h2>Resultado de laboratorio</h2></div>
            <p><strong>Paciente:</strong> {{ $paciente ? $paciente->NOM_PAC . ' ' . $paciente->APE_PAC . ' (' . $paciente->COD_PAC . ')' : '' }}</p>
            <table style="border-collapse:collapse;width:100%"><tr>${fila.innerHTML}</tr></table>
            <p style="margin-top:30px;font-size:12px;color:#64748b">Documento generado desde el portal del paciente.</p>
            </body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 400);
    }
</script>
@endpush
