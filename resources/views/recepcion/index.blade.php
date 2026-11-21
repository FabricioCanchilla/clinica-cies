@extends('layouts.admin')
@section('titulo','Recepción')
@section('contenido')
@php
    $seccion = request('seccion', 'citas');
    if (!in_array($seccion, ['citas','disponibilidad','reservar','pacientes','seguros'], true)) { $seccion = 'citas'; }
@endphp
<h1>Recepción</h1><p class="sub">Gestiona pacientes, citas, llegadas y seguros desde un solo lugar.</p>
@if(session('ok'))<div class="card" role="status">{{ session('ok') }}</div>
@endif
@if($errors->any())<div class="card" role="alert"><strong>Revisa los datos:</strong> {{ $errors->first() }}</div>
@endif
<form class="barra" method="get"><input type="hidden" name="seccion" value="{{ $seccion }}"><label>Fecha <input type="date" name="fecha" value="{{ $fecha }}"></label><label>Paciente o CI <input name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="Nombre, CI o código"></label><label>Médico <select name="medico"><option value="">Todos</option>
@foreach($medicos as $m)<option value="{{ $m->COD_EMP }}" @selected(request('medico')===$m->COD_EMP)>{{ $m->NOM_EMP }} {{ $m->APE_EMP }} · {{ $m->DES_ESP }}</option>
@endforeach</select></label><button class="btn principal">Consultar</button><a class="btn" href="{{ route('recepcion.dashboard') }}">Hoy</a></form>
<div class="grid" style="margin-bottom:20px"><div class="card"><strong>{{ $citas->count() }}</strong><br>Citas del día</div><div class="card"><strong>{{ $citas->where('ATENCION','En espera')->count() }}</strong><br>En espera</div><div class="card"><strong>{{ $camas->where('EST_CAM','Disponible')->count() }}</strong><br>Camas disponibles</div></div>
@if ($seccion === 'citas')
<section id="citas" class="card" style="overflow-x:auto;margin-bottom:20px"><h2>Citas y lista de espera · {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h2><table><thead><tr><th>Hora</th><th>Paciente</th><th>Médico y consultorio</th><th>Motivo</th><th>Estado</th><th>Pago</th><th>Acciones</th></tr></thead><tbody>
@forelse($citas as $c)<tr><td>{{ substr($c->HOR_CIT,0,5) }}<br>{{ $c->COD_CIT }}</td><td>{{ $c->NOM_PAC }} {{ $c->APE_PAC }}<br>CI {{ $c->CI_PAC }} · {{ $c->TEL_PAC }}</td><td>{{ $c->NOM_EMP }} {{ $c->APE_EMP }}<br>{{ $c->NOM_SRV }} · {{ $c->NOM_CON }}</td><td>{{ $c->MOT_CIT }}</td><td>{{ $c->EST_CIT==='Confirmada' ? ($c->ATENCION ?? $c->EST_CIT) : $c->EST_CIT }}</td><td>Bs {{ number_format($c->PAGADO,2) }} / {{ number_format($c->PRE_SRV,2) }}<br>
@if(!$c->LIQUIDADO && $c->PAGADO < $c->PRE_SRV)Saldo: acudir a caja
@endif</td><td>
@if($c->EST_CIT==='Confirmada' && $c->FEC_CIT===today()->toDateString() && !$c->ATENCION)<form method="post" action="{{ route('recepcion.citas.llegada',$c->COD_CIT) }}">@csrf<button class="btn">Registrar llegada</button></form>
@endif
@if($c->EST_CIT==='Confirmada' && \Carbon\Carbon::parse($c->FEC_CIT.' '.$c->HOR_CIT)->isFuture())<form method="post" action="{{ route('recepcion.citas.recordatorio',$c->COD_CIT) }}">@csrf<button class="btn">Avisar al paciente</button></form>
@endif
@if($c->EST_CIT==='Confirmada' && $c->ATENCION!=='En consulta')<details><summary>Reprogramar</summary><form method="post" action="{{ route('recepcion.citas.reprogramar',$c->COD_CIT) }}">@csrf<label>Fecha<input type="date" name="fecha" min="{{ today()->addDay()->toDateString() }}" required></label><label>Hora<input type="time" name="hora" required></label><label>Motivo<input name="motivo" minlength="10" maxlength="200" required></label><button class="btn">Guardar</button></form></details>
@endif
@if(in_array($c->EST_CIT,['Confirmada','Pendiente de pago']))<details><summary>Cancelar</summary><p>Con al menos 24 horas de anticipación, el anticipo queda como saldo del paciente. Los pagos finales requieren revisión en caja.</p><form method="post" action="{{ route('recepcion.citas.cancelar',$c->COD_CIT) }}">@csrf<label>Motivo<input name="motivo" minlength="10" maxlength="200" required></label><button class="btn">Confirmar cancelación</button></form></details>
@endif
</td></tr>
@empty<tr><td colspan="7">No hay citas que coincidan con los filtros.</td></tr>
@endforelse</tbody></table></section>
@endif
@if ($seccion === 'disponibilidad')
<section id="disponibilidad" class="card" style="margin-bottom:20px"><h2>Disponibilidad</h2><p>Selecciona una fecha y un médico en los filtros superiores para ver sus horarios libres.</p>
@if(request('medico'))<p><strong>Horarios libres:</strong> @forelse($disponibles as $h)<span class="badge baja">{{ $h['hora'] }}</span> @empty No hay horarios libres para la fecha seleccionada. @endforelse</p>
@endif<h3>Consultorios</h3><p>
@forelse($consultorios as $con){{ $con->NOM_CON }}
@if(!$loop->last) · @endif @empty Sin consultorios registrados. @endforelse</p><h3>Camas</h3><p>
@forelse($camas as $c){{ $c->NOM_SAL }} / {{ $c->NRO_CAM }}: {{ $c->EST_CAM }}
@if(!$loop->last) · @endif @empty Sin camas registradas. @endforelse</p></section>
@endif
@if ($seccion === 'reservar')
<section class="card" style="margin-bottom:20px" id="reservar"><h2>Reservar una cita</h2><p>Busca al paciente por nombre, apellido, CI o código. El médico se asigna por turnos entre los disponibles. Los precios especializados son referenciales de prueba.</p>
<form method="post" action="{{ route('recepcion.citas.guardar') }}" id="form-reserva" data-busqueda="{{ route('recepcion.pacientes.buscar') }}" data-especialidades="{{ route('recepcion.pacientes.especialidades', ['paciente'=>'__PACIENTE__']) }}">@csrf
<div class="barra"><div style="position:relative;min-width:240px"><label for="buscar-paciente-reserva">Paciente</label><input id="buscar-paciente-reserva" type="search" autocomplete="off" placeholder="Escribe nombre, apellido, CI o código" aria-autocomplete="list" aria-controls="pacientes-sugeridos" aria-describedby="paciente-ayuda" required><input id="paciente-codigo-reserva" type="hidden" name="paciente"><div id="pacientes-sugeridos" role="listbox" style="position:absolute;background:white;border:1px solid #cbd5e1;border-radius:8px;z-index:5;width:100%;max-height:240px;overflow:auto" hidden></div><small id="paciente-ayuda">Escribe al menos dos caracteres y selecciona un resultado.</small></div>
<label>Especialidad<select id="especialidad-reserva" name="especialidad" required><option value="">Selecciona primero un paciente</option></select></label>
<label>Fecha<input type="date" name="fecha" min="{{ today()->toDateString() }}" max="{{ today()->addDays(config('cies.dias_max_anticipacion'))->toDateString() }}" value="{{ $fecha }}" required></label><label>Hora<input type="time" name="hora" required></label><label>Motivo<input name="motivo" maxlength="200" required></label></div>
<p id="estado-especialidades" aria-live="polite">La consulta general es el primer paso. Las especialidades se habilitan con una derivación.</p><noscript>Activa JavaScript para buscar pacientes y seleccionar las especialidades autorizadas.</noscript><button class="btn principal">Reservar</button></form></section>
@endif

@if ($seccion === 'pacientes')
<section id="pacientes" class="card" style="margin-bottom:20px;overflow-x:auto"><h2>Pacientes</h2><p>Busca por CI antes de crear una ficha para evitar duplicados. Se muestran hasta 100 resultados.</p><table><thead><tr><th>Código</th><th>Paciente</th><th>CI</th><th>Teléfono</th><th>Correo</th><th>Cuenta</th></tr></thead><tbody>
@forelse($pacientes as $p)<tr><td>{{ $p->COD_PAC }}</td><td>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</td><td>{{ $p->CI_PAC }}</td><td>{{ $p->TEL_PAC }}</td><td>{{ $p->COR_PAC }}</td><td>
@if($p->CUENTAS > 0)
Cuenta existente
@else
<form method="post" action="{{ route('recepcion.pacientes.activacion',$p->COD_PAC) }}">@csrf<button class="btn">Enviar enlace de activación</button></form>
@endif
</td></tr>
@empty<tr><td colspan="6">Sin pacientes que coincidan.</td></tr>
@endforelse</tbody></table><details style="margin-top:20px"><summary>Registrar paciente nuevo</summary><p>Después de registrar la ficha, envía el enlace de activación al correo registrado. El paciente elige su contraseña.</p><form method="post" action="{{ route('recepcion.pacientes.guardar') }}">@csrf<div class="barra"><label>CI<input name="ci" pattern="[0-9]{5,10}" required></label><label>Nombres<input name="nombres" maxlength="40" required></label><label>Apellidos<input name="apellidos" maxlength="40" required></label><label>Sexo<select name="sexo" required><option value="">Seleccionar</option><option value="F">Femenino</option><option value="M">Masculino</option></select></label><label>Nacimiento<input type="date" name="nacimiento" required></label><label>Estado civil<select name="estado_civil">
@foreach(['Soltero','Casado','Divorciado','Viudo','Concubino'] as $e)<option>{{ $e }}</option>
@endforeach</select></label><label>Teléfono<input name="telefono" pattern="[0-9]{7,8}" required></label><label>Correo<input type="email" name="correo" maxlength="60" required></label><label>Dirección<input name="direccion" maxlength="100" required></label></div><button class="btn principal">Registrar ficha</button></form></details></section>
@endif
@if ($seccion === 'seguros')
<section id="seguros" class="card" style="overflow-x:auto"><h2>Seguros médicos</h2><p>Registra el estado después de verificar la vigencia y cobertura con la aseguradora.</p><table><thead><tr><th>Paciente</th><th>Aseguradora</th><th>Afiliación</th><th>Vigencia</th><th>Estado registrado</th></tr></thead><tbody>
@forelse($seguros as $s)<tr><td>{{ $s->NOM_PAC }} {{ $s->APE_PAC }} · {{ $s->CI_PAC }}</td><td>{{ $s->DES_ASE }}</td><td>{{ $s->NROAFIL_SEG }}</td><td>{{ $s->FECVAL_SEG }} — {{ $s->FECVEN_SEG }}</td><td>{{ $s->EST_SEG }} @if($s->EST_SEG==='Vigente' && \Carbon\Carbon::parse($s->FECVEN_SEG)->isPast()) (fecha vencida) @endif</td></tr>
@empty<tr><td colspan="5">No hay seguros registrados.</td></tr>
@endforelse</tbody></table><details style="margin-top:20px"><summary>Registrar seguro verificado</summary><form method="post" action="{{ route('recepcion.seguros.guardar') }}">@csrf<div class="barra"><label>Paciente<select name="paciente" required><option value="">Seleccionar</option>
@foreach($pacientes as $p)<option value="{{ $p->COD_PAC }}">{{ $p->APE_PAC }}, {{ $p->NOM_PAC }} · {{ $p->CI_PAC }}</option>
@endforeach</select></label><label>Aseguradora<select name="aseguradora" required><option value="">Seleccionar</option>
@foreach($aseguradoras as $a)<option value="{{ $a->COD_ASE }}">{{ $a->DES_ASE }}</option>
@endforeach</select></label><label>Número de afiliación<input name="afiliacion" maxlength="15" required></label><label>Válido desde<input type="date" name="desde" required></label><label>Hasta<input type="date" name="hasta" required></label><label>Estado<select name="estado"><option>Pendiente</option><option>Vigente</option><option>Vencido</option><option>Rechazado</option></select></label></div><button class="btn principal">Registrar seguro</button></form></details></section>
@endif
<script src="{{ asset('js/recepcion-reserva.js') }}" defer></script>
@endsection
