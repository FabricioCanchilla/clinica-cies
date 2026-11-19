<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte del paciente {{ $p->COD_PAC }}</title>
<style>
    @page { margin: 26px 30px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
    .cab { border-bottom: 3px solid #cc783c; padding-bottom: 8px; margin-bottom: 10px; }
    .cab table td { vertical-align: top; }
    .marca { font-size: 17px; font-weight: bold; color: #3b6449; }
    .marca small { display: block; font-size: 8.5px; font-weight: normal; color: #64748b; }
    h1 { font-size: 13px; margin: 0; color: #0f172a; }
    h2 { font-size: 11px; color: #3b6449; border-bottom: 1px solid #c9ddd0; padding-bottom: 3px; margin: 14px 0 6px; }
    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 2px 4px; }
    .datos td.k { color: #64748b; width: 18%; }
    .lista th { background: #eef5f0; color: #3b6449; text-align: left; padding: 4px; font-size: 8.5px; text-transform: uppercase; }
    .lista td { padding: 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .kpi td { width: 16.6%; text-align: center; border: 1px solid #e2e8f0; padding: 6px 2px; }
    .kpi b { display: block; font-size: 13px; color: #3b6449; }
    .kpi span { color: #64748b; font-size: 8px; }
    .alerta { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 5px 7px; margin: 6px 0; }
    .pie { position: fixed; bottom: -22px; left: 0; font-size: 7px; color: #94a3b8; }
    small { color: #64748b; }
    .der { text-align: right; }
</style>
</head>
<body>
@php
    $f = fn ($d, $fmt = 'd/m/Y') => $d ? \Carbon\Carbon::parse($d)->format($fmt) : '—';
    $bs = fn ($n) => 'Bs ' . number_format((float) $n, 2, '.', ',');
    $r = $resumen;
@endphp
<div class="pie">Clínica CIES La Paz · Av. Tejada Sorzano #393, Miraflores · Tel. (2) 2216061 — Documento confidencial. Generado el {{ $generadoEn }} por {{ $generadoPor }}.</div>

<div class="cab">
    <table><tr>
        <td><div class="marca">CIES · Clínica La Paz<small>Salud sexual, salud reproductiva, salud integral</small></div></td>
        <td style="text-align:right;"><h1>REPORTE INTEGRAL DEL PACIENTE</h1><small>{{ $p->COD_PAC }}@if ($historia) · Historia {{ $historia->NRO_HIS }}@endif</small></td>
    </tr></table>
</div>

<table class="datos">
    <tr><td class="k">Paciente</td><td><b>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</b></td><td class="k">CI</td><td>{{ $p->CI_PAC }}</td></tr>
    <tr><td class="k">Edad / sexo</td><td>{{ $p->EDAD }} años · {{ $p->SEX_PAC === 'F' ? 'Femenino' : 'Masculino' }}</td><td class="k">Estado civil</td><td>{{ $p->ESTCIV_PAC }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $p->TEL_PAC }}</td><td class="k">Correo</td><td>{{ $p->COR_PAC }}</td></tr>
    <tr><td class="k">Dirección</td><td colspan="3">{{ $p->DIR_PAC }}</td></tr>
</table>

@if ($alergias->isNotEmpty())
    <div class="alerta"><b>Alergias:</b> @foreach ($alergias as $a){{ $a->DES_ALE }} ({{ $a->SEV_ALE }})@if (!$loop->last), @endif @endforeach</div>
@endif
@if ($antecedentes->isNotEmpty())
    <p><b>Antecedentes:</b> @foreach ($antecedentes as $a){{ $a->TIP_ANT }}: {{ $a->DES_ANT }}@if (!$loop->last); @endif @endforeach</p>
@endif

<h2>Resumen de actividad</h2>
<table class="kpi"><tr>
    <td><b>{{ $r['atendidas'] }}</b><span>visitas atendidas</span></td>
    <td><b>{{ $r['citas'] }}</b><span>citas agendadas</span></td>
    <td><b>{{ $r['inasistencias'] }}</b><span>inasistencias</span></td>
    <td><b>{{ $r['canceladas'] }}</b><span>cancelaciones</span></td>
    <td><b>{{ $r['especialidad'] ?? '—' }}</b><span>especialidad más visitada</span></td>
    <td><b>{{ $bs($r['pagado']) }}</b><span>total pagado</span></td>
</tr></table>
<p>Primera visita: <b>{{ $f($r['primera']) }}</b> · Última visita: <b>{{ $f($r['ultima']) }}</b>
    · Próxima cita: <b>{{ $r['proxima'] ? $f($r['proxima']->FEC_CIT) . ' ' . substr($r['proxima']->HOR_CIT, 0, 5) . ' (' . $r['proxima']->DES_ESP . ')' : 'ninguna' }}</b></p>

@if ($porEspecialidad->isNotEmpty())
    <table class="lista" style="width: 60%;">
        <tr><th>Especialidad</th><th class="der">Visitas</th></tr>
        @foreach ($porEspecialidad as $esp => $n)<tr><td>{{ $esp }}</td><td class="der">{{ $n }}</td></tr>@endforeach
    </table>
@endif

<h2>Citas y consultas</h2>
<table class="lista">
    <tr><th>Fecha</th><th>Especialidad / médico</th><th>Motivo</th><th>Diagnóstico · plan</th><th>Estado</th></tr>
    @forelse ($citas as $c)
        <tr>
            <td>{{ $f($c->FEC_CIT) }} {{ substr($c->HOR_CIT, 0, 5) }}</td>
            <td>{{ $c->DES_ESP }}<br><small>{{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }}</small></td>
            <td>{{ $c->MOT_CIT }}</td>
            <td>{{ $c->DIA_CNS ?? '—' }}@if ($c->PLA_CNS)<br><small>{{ $c->PLA_CNS }}</small>@endif</td>
            <td>{{ $c->EST_CIT }}</td>
        </tr>
    @empty
        <tr><td colspan="5">Sin citas.</td></tr>
    @endforelse
</table>

@if ($recetas->isNotEmpty())
<h2>Recetas</h2>
<table class="lista">
    <tr><th>Receta</th><th>Fecha</th><th>Medicamentos</th><th>Estado</th></tr>
    @foreach ($recetas as $rc)
        <tr><td>{{ $rc->COD_REC }}</td><td>{{ $f($rc->FEC_REC) }}</td>
            <td>@foreach ($rc->ITEMS as $it){{ $it->DES_MED }} — {{ $it->DOS_DRE }} c/{{ $it->FRE_DRE }} h, {{ $it->DUR_DRE }} días<br>@endforeach</td>
            <td>{{ $rc->EST_REC }}</td></tr>
    @endforeach
</table>
@endif

@if ($examenes->isNotEmpty())
<h2>Exámenes de laboratorio</h2>
<table class="lista">
    <tr><th>Fecha</th><th>Examen</th><th>Indicación</th><th>Estado</th></tr>
    @foreach ($examenes as $e)
        <tr><td>{{ $f($e->FEC_ORD) }}</td><td>{{ $e->NOM_SRV }} ({{ $e->PRI_ORD }})</td><td>{{ $e->IND_ORD }}</td>
            <td>{{ $e->EST_ORD }}@if ($e->EST_RES) · resultado {{ strtolower($e->EST_RES) }}@endif</td></tr>
    @endforeach
</table>
@endif

@if ($internaciones->isNotEmpty())
<h2>Internaciones</h2>
<table class="lista">
    <tr><th>Ingreso</th><th>Alta</th><th>Motivo</th><th>Sala / cama</th><th>Médico</th></tr>
    @foreach ($internaciones as $i)
        <tr><td>{{ $f($i->FECING_INT, 'd/m/Y H:i') }}</td><td>{{ $i->FECALT_INT ? $f($i->FECALT_INT, 'd/m/Y H:i') : 'Internado' }}</td>
            <td>{{ $i->MOT_INT }}@if (!empty($i->AIS_INT))<br><small>Aislamiento: {{ $i->AIS_INT }}</small>@endif</td>
            <td>{{ $i->NOM_SAL }} · {{ $i->NRO_CAM }}</td><td>{{ $i->TIT_EMP }} {{ $i->APE_EMP }}</td></tr>
    @endforeach
</table>
@endif

<h2>Pagos</h2>
<table class="lista">
    <tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Método</th><th class="der">Monto</th><th>Estado</th></tr>
    @forelse ($pagos as $pg)
        <tr><td>{{ $f($pg->FEC_PAG, 'd/m/Y H:i') }}</td><td>{{ $pg->NOM_SRV }} ({{ $pg->COD_CIT }})</td>
            <td>{{ $pg->TIP_PAG === 'Adelanto' ? 'Anticipo' : 'Saldo' }}</td><td>{{ $pg->DES_MET }}</td>
            <td class="der">{{ $bs($pg->MON_PAG) }}</td><td>{{ $pg->EST_PAG }}</td></tr>
    @empty
        <tr><td colspan="6">Sin pagos.</td></tr>
    @endforelse
</table>

@if ($insumos->isNotEmpty())
<h2>Insumos entregados por familiares</h2>
<table class="lista">
    <tr><th>Fecha</th><th>Medicamento</th><th>Cantidad</th><th>Entregó</th><th>Estado</th></tr>
    @foreach ($insumos as $in)
        <tr><td>{{ $f($in->FEC_INS) }}</td><td>{{ $in->DES_MED }}</td><td>{{ $in->CAN_INS }}</td><td>{{ $in->NOM_ENT }} {{ $in->APE_ENT }}</td><td>{{ $in->EST_INS }}</td></tr>
    @endforeach
</table>
@endif
</body>
</html>
