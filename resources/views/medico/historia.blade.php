@extends('layouts.admin')

@section('titulo', 'Historia ' . ($historia->NRO_HIS ?? $paciente->COD_PAC))
@section('encabezado', 'Portal Médico · Historia clínica')

@push('head')
<style>
    .hc-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
    .hc-grid .card { margin-bottom: 20px; }
    .hc-item { border-bottom: 1px solid var(--borde); padding: 12px 0; font-size: 14px; }
    .hc-item:last-child { border-bottom: 0; }
    .hc-item .meta { font-size: 12px; color: var(--suave); }
    .adenda { margin: 8px 0 0 14px; padding: 8px 12px; border-left: 3px solid var(--naranja); background: var(--naranja-bg); border-radius: 0 8px 8px 0; font-size: 13px; }
    .cie { display: inline-block; font-family: ui-monospace, monospace; font-size: 12px; background: var(--cies-bg); color: var(--cies-dark); padding: 1px 6px; border-radius: 6px; font-weight: 700; }
    .mini-form { display: grid; gap: 8px; margin-top: 10px; }
    .mini-form input, .mini-form select, .mini-form textarea { padding: 8px 10px; border: 1px solid var(--borde-input); border-radius: 8px; font: inherit; font-size: 13px; width: 100%; }
    details summary { cursor: pointer; font-size: 13px; color: var(--cies-dark); font-weight: 600; margin-top: 6px; }
    .ale { color: #b91c1c; font-weight: 700; }
    @media (max-width: 1000px) { .hc-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('contenido')
    <div class="card-cabecera">
        <div>
            <h1>{{ $paciente->NOM_PAC }} {{ $paciente->APE_PAC }}</h1>
            <p class="sub" style="margin-bottom: 0;">
                CI {{ $paciente->CI_PAC }} · {{ \Carbon\Carbon::parse($paciente->FECNAC_PAC)->age }} años · {{ $paciente->SEX_PAC === 'F' ? 'Femenino' : 'Masculino' }}
                @if ($historia) · {{ $historia->NRO_HIS }} abierta el {{ \Carbon\Carbon::parse($historia->FECAPE_HIS)->format('d/m/Y') }} @else · sin historia abierta @endif
            </p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('medico.historia.consentimiento', $paciente->COD_PAC) }}"><i class="fas fa-file-signature"></i> Consentimiento informado</a>
            <a class="btn" href="{{ route('reportes.paciente', $paciente->COD_PAC) }}"><i class="fas fa-file-medical"></i> Reporte</a>
        </div>
    </div>
    @if ($emergencia)
        <div class="alerta aviso"><i class="fas fa-unlock"></i> Estás viendo esta historia con acceso de emergencia (auditado).</div>
    @endif

    <div class="hc-grid">
        <div>
            <div class="card">
                <h2><i class="fas fa-stethoscope"></i> Consultas y adendas</h2>
                <p class="ayuda" style="margin-top: 0;">RN41: las consultas no se editan ni se borran; cada corrección es una adenda con autor, fecha y hora.</p>
                @forelse ($consultas as $c)
                    <div class="hc-item">
                        <div class="meta">{{ \Carbon\Carbon::parse($c->FEC_CNS)->format('d/m/Y H:i') }} · {{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }} · {{ $c->DES_ESP }} · {{ $c->COD_CNS }}</div>
                        <div style="margin-top: 4px;">
                            @if ($c->COD_CIE)<span class="cie" title="{{ $c->DES_CIE }}">{{ $c->COD_CIE }}</span>@endif
                            <strong>{{ $c->DIA_CNS }}</strong>
                        </div>
                        <div style="color: var(--suave);">{{ $c->PLA_CNS }}</div>
                        @foreach ($adendas->get($c->COD_CNS, collect()) as $a)
                            <div class="adenda">
                                <div class="meta">Adenda {{ $a->COD_ADE }} · {{ \Carbon\Carbon::parse($a->FEC_ADE)->format('d/m/Y H:i') }} · {{ $a->TIT_EMP }} {{ $a->NOM_EMP }} {{ $a->APE_EMP }}</div>
                                {{ $a->TXT_ADE }}
                            </div>
                        @endforeach
                        <details>
                            <summary><i class="fas fa-pen"></i> Agregar adenda</summary>
                            <form method="POST" action="{{ route('medico.historia.adenda', $c->COD_CNS) }}" class="mini-form">
                                @csrf
                                <textarea name="texto" rows="2" minlength="5" maxlength="500" required placeholder="Corrección o aclaración"></textarea>
                                <button class="btn sm principal" style="justify-self: start;">Guardar adenda</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="vacio"><i class="fas fa-notes-medical"></i> Sin consultas registradas.</div>
                @endforelse
            </div>

            <div class="card">
                <h2><i class="fas fa-notes-medical"></i> Notas de evolución</h2>
                @forelse ($notas as $n)
                    <div class="hc-item">
                        <div class="meta">{{ \Carbon\Carbon::parse($n->FEC_NEV)->format('d/m/Y H:i') }} · {{ $n->TIT_EMP }} {{ $n->NOM_EMP }} {{ $n->APE_EMP }}</div>
                        {{ $n->TXT_NEV }}
                    </div>
                @empty
                    <div class="vacio">Sin notas.</div>
                @endforelse
            </div>

            <div class="card tabla">
                <h2><i class="fas fa-prescription"></i> Recetas</h2>
                @if ($recetas->isEmpty())
                    <div class="vacio">Sin recetas.</div>
                @else
                    <table>
                        <thead><tr><th>Fecha</th><th>Receta</th><th>Medicamento</th><th>Indicación</th><th>Estado</th></tr></thead>
                        <tbody>
                        @foreach ($recetas as $r)
                            <tr>
                                <td class="nowrap">{{ \Carbon\Carbon::parse($r->FEC_REC)->format('d/m/Y') }}</td>
                                <td>{{ $r->COD_REC }}</td>
                                <td>{{ $r->DES_MED }}</td>
                                <td>{{ $r->DOS_DRE }} · {{ $r->DES_VIA }} · cada {{ $r->FRE_DRE }} h · {{ $r->DUR_DRE }} días</td>
                                <td><span class="badge">{{ $r->EST_REC }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card tabla">
                <h2><i class="fas fa-vials"></i> Exámenes</h2>
                @if ($resultados->isEmpty())
                    <div class="vacio">Sin órdenes de examen.</div>
                @else
                    <table>
                        <thead><tr><th>Fecha</th><th>Examen</th><th>Orden</th><th>Resultado</th></tr></thead>
                        <tbody>
                        @foreach ($resultados as $o)
                            <tr>
                                <td class="nowrap">{{ \Carbon\Carbon::parse($o->FEC_ORD)->format('d/m/Y') }}</td>
                                <td>{{ $o->NOM_SRV }} @if ($o->PRI_ORD === 'Urgente')<span class="badge critica">Urgente</span>@endif</td>
                                <td><span class="badge">{{ $o->EST_ORD }}</span></td>
                                <td>
                                    @if ($o->EST_RES === 'Publicado')
                                        @if ($o->CRI_RES)<span class="badge critica">Valor crítico</span>@endif
                                        {{ \Illuminate\Support\Str::limit($o->DES_RES, 120) }}
                                        @if ($o->ARC_RES)
                                            <a href="{{ route('resultados.archivo', $o->COD_RES) }}" target="_blank" class="btn sm"><i class="fas fa-paperclip"></i> Adjunto</a>
                                        @endif
                                    @else
                                        <small>{{ $o->EST_RES ? 'Resultado ' . mb_strtolower($o->EST_RES) . ', aún no publicado' : 'Sin resultado' }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card">
                <h2><i class="fas fa-share-from-square"></i> Derivaciones e interconsultas</h2>
                @forelse ($derivaciones as $d)
                    <div class="hc-item">
                        <div class="meta">{{ \Carbon\Carbon::parse($d->FEC_DER)->format('d/m/Y H:i') }} · {{ $d->COD_DER }} · de {{ $d->TIT_EMP }} {{ $d->NOM_EMP }} {{ $d->APE_EMP }}</div>
                        <strong>{{ $d->TIP_DER }} a {{ $d->DES_ESP }}</strong>
                        <span class="badge {{ $d->PRI_DER === 'Urgente' ? 'critica' : '' }}">{{ $d->PRI_DER }}</span>
                        <span class="badge {{ $d->EST_DER === 'Respondida' ? 'activo' : ($d->EST_DER === 'Rechazada' ? 'bloqueado' : 'media') }}">{{ $d->EST_DER }}</span>
                        <div>{{ $d->MOT_DER }}</div>
                        @if ($d->RES_DER)<div class="adenda">{{ $d->RES_DER }}</div>@endif
                    </div>
                @empty
                    <div class="vacio">Sin derivaciones.</div>
                @endforelse
                <details>
                    <summary><i class="fas fa-plus"></i> Nueva derivación o interconsulta</summary>
                    <form method="POST" action="{{ route('medico.historia.derivar', $paciente->COD_PAC) }}" class="mini-form">
                        @csrf
                        <div class="fila" style="gap: 8px;">
                            <select name="especialidad" required>
                                <option value="">Especialidad destino…</option>
                                @foreach ($especialidades as $e)
                                    <option value="{{ $e->COD_ESP }}">{{ $e->DES_ESP }}</option>
                                @endforeach
                            </select>
                            <select name="tipo"><option>Interconsulta</option><option>Derivación</option></select>
                        </div>
                        <select name="prioridad"><option>Normal</option><option>Urgente</option></select>
                        <textarea name="motivo" rows="2" minlength="10" maxlength="300" required placeholder="Motivo y pregunta clínica"></textarea>
                        <button class="btn sm principal" style="justify-self: start;">Enviar</button>
                    </form>
                </details>
            </div>
        </div>

        <div>
            <div class="card">
                <h2 style="color: #b91c1c;"><i class="fas fa-allergies" style="color: #b91c1c;"></i> Alergias</h2>
                @forelse ($alergias as $a)
                    <div class="hc-item"><span class="ale">{{ $a->DES_ALE }}</span> <small>({{ $a->SEV_ALE }})</small></div>
                @empty
                    <p class="vacio" style="padding: 8px;">Sin alergias registradas.</p>
                @endforelse
                <details>
                    <summary><i class="fas fa-plus"></i> Registrar alergia</summary>
                    <form method="POST" action="{{ route('medico.historia.alergia', $paciente->COD_PAC) }}" class="mini-form">
                        @csrf
                        <input name="descripcion" maxlength="60" required placeholder="Ej. Penicilina">
                        <select name="severidad"><option>Leve</option><option>Moderada</option><option>Grave</option></select>
                        <button class="btn sm principal" style="justify-self: start;">Guardar</button>
                    </form>
                </details>
            </div>

            <div class="card">
                <h2><i class="fas fa-book-medical"></i> Antecedentes</h2>
                @forelse ($antecedentes as $a)
                    <div class="hc-item"><span class="badge">{{ $a->TIP_ANT === 'Quirurgico' ? 'Quirúrgico' : $a->TIP_ANT }}</span> {{ $a->DES_ANT }}</div>
                @empty
                    <p class="vacio" style="padding: 8px;">Sin antecedentes.</p>
                @endforelse
                <details>
                    <summary><i class="fas fa-plus"></i> Registrar antecedente</summary>
                    <form method="POST" action="{{ route('medico.historia.antecedente', $paciente->COD_PAC) }}" class="mini-form">
                        @csrf
                        <select name="tipo"><option value="Personal">Personal</option><option value="Familiar">Familiar</option><option value="Quirurgico">Quirúrgico</option></select>
                        <input name="descripcion" maxlength="200" required placeholder="Ej. Hipertensión desde 2019">
                        <button class="btn sm principal" style="justify-self: start;">Guardar</button>
                    </form>
                </details>
            </div>

            <div class="card tabla">
                <h2><i class="fas fa-heart-pulse"></i> Signos vitales</h2>
                @if ($signos->isEmpty())
                    <p class="vacio" style="padding: 8px;">Sin registros.</p>
                @else
                    <table>
                        <thead><tr><th>Fecha</th><th>PA</th><th>Pulso</th><th>T°</th></tr></thead>
                        <tbody>
                        @foreach ($signos as $s)
                            <tr>
                                <td class="nowrap"><small>{{ \Carbon\Carbon::parse($s->FEC_SIV)->format('d/m H:i') }}</small></td>
                                <td>{{ $s->SIS_SIV }}/{{ $s->DIA_SIV }}</td>
                                <td>{{ $s->PUL_SIV }}</td>
                                <td>{{ $s->TEM_SIV }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card">
                <h2><i class="fas fa-file-signature"></i> Consentimientos</h2>
                @forelse ($consentimientos as $c)
                    <div class="hc-item">
                        <div class="meta">{{ \Carbon\Carbon::parse($c->FEC_CNT)->format('d/m/Y H:i') }} · {{ $c->COD_CNT }} · {{ $c->TIT_EMP }} {{ $c->APE_EMP }}</div>
                        <strong>{{ $c->PRO_CNT }}</strong>
                        <div class="meta">Firmó: {{ $c->FIRNOM_CNT }}</div>
                        <img src="{{ route('medico.historia.firma', $c->COD_CNT) }}" alt="Firma" style="max-width: 180px; border: 1px solid var(--borde); border-radius: 6px; margin-top: 6px;">
                    </div>
                @empty
                    <p class="vacio" style="padding: 8px;">Sin consentimientos.</p>
                @endforelse
            </div>

            <div class="card">
                <h2><i class="fas fa-bed"></i> Internaciones</h2>
                @forelse ($internaciones as $i)
                    <div class="hc-item">
                        <div class="meta">{{ \Carbon\Carbon::parse($i->FECING_INT)->format('d/m/Y') }}{{ $i->FECALT_INT ? ' – ' . \Carbon\Carbon::parse($i->FECALT_INT)->format('d/m/Y') : '' }} · {{ $i->COD_CAM }}</div>
                        {{ $i->MOT_INT }} <span class="badge {{ $i->EST_INT === 'Internado' ? 'media' : '' }}">{{ $i->EST_INT }}</span>
                    </div>
                @empty
                    <p class="vacio" style="padding: 8px;">Sin internaciones.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
