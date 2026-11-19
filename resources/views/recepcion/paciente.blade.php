@extends('layouts.admin')

@section('titulo', 'Ficha del paciente')

@section('contenido')
    <h1>{{ $p->NOM_PAC }} {{ $p->APE_PAC }}</h1>
    <p class="sub">
        {{ $p->COD_PAC }} · CI {{ $p->CI_PAC }} · {{ \Carbon\Carbon::parse($p->FECNAC_PAC)->age }} años ·
        Tel. {{ $p->TEL_PAC }} · {{ $p->COR_PAC }} · {{ $p->DIR_PAC }}
    </p>

    <h2>Seguros (RF038)</h2>
    <div class="card" style="overflow-x: auto; margin-bottom: 16px;">
        @if ($seguros->isEmpty())
            <p class="sub">El paciente no tiene seguros registrados.</p>
        @else
            <table>
                <thead><tr><th>Aseguradora</th><th>Afiliación</th><th>Validado</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @foreach ($seguros as $s)
                        <tr>
                            <td>{{ $s->DES_ASE }}</td>
                            <td>{{ $s->NROAFIL_SEG }}</td>
                            <td>{{ \Carbon\Carbon::parse($s->FECVAL_SEG)->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($s->FECVEN_SEG)->format('d/m/Y') }}</td>
                            <td><strong style="color: {{ $s->EST_SEG === 'Vigente' ? '#3b6449' : '#b91c1c' }};">{{ $s->EST_SEG }}</strong></td>
                            <td>
                                <form method="POST" action="{{ route('recepcion.seguros.revalidar', $s->COD_SEG) }}">
                                    @csrf
                                    <button type="submit" class="btn">Verificar vigencia</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card formulario ancho" style="margin-bottom: 28px;">
        <form method="POST" action="{{ route('recepcion.seguros.guardar', $p->COD_PAC) }}"
              style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0 12px; align-items: end;">
            @csrf
            <div><label>Aseguradora</label>
                <select name="aseguradora" required>
                    @foreach ($aseguradoras as $a)
                        <option value="{{ $a->COD_ASE }}" @selected(old('aseguradora') === $a->COD_ASE)>{{ $a->DES_ASE }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>N.º de afiliación</label><input name="afiliacion" maxlength="15" required value="{{ old('afiliacion') }}"></div>
            <div><label>Vence el</label><input type="date" name="vencimiento" required value="{{ old('vencimiento') }}"></div>
            <div><button type="submit" class="btn principal" style="margin-bottom: 14px;">Validar póliza</button></div>
        </form>
    </div>

    <h2>Citas (coordinación de agenda)</h2>
    <div class="card" style="overflow-x: auto;">
        @if ($citas->isEmpty())
            <p class="sub">Sin citas.</p>
        @else
            <table>
                <thead><tr><th>Cita</th><th>Fecha</th><th>Médico</th><th>Servicio</th><th>Estado</th><th>Acciones de la clínica</th></tr></thead>
                <tbody>
                    @foreach ($citas as $c)
                        <tr>
                            <td>{{ $c->COD_CIT }}</td>
                            <td>{{ \Carbon\Carbon::parse($c->FEC_CIT)->format('d/m/Y') }} {{ substr($c->HOR_CIT, 0, 5) }}</td>
                            <td>{{ $c->TIT_EMP }} {{ $c->NOM_EMP }} {{ $c->APE_EMP }}</td>
                            <td>{{ $c->NOM_SRV }}</td>
                            <td>{{ $c->EST_CIT }}</td>
                            <td>
                                @if (in_array($c->EST_CIT, ['Confirmada', 'Atendida', 'Pendiente de pago'], true))
                                    <a href="{{ route('ordenes.pago', $c->COD_CIT) }}" class="btn"><i class="fas fa-file-invoice-dollar"></i> Orden de pago</a>
                                @endif
                                @if ($c->EST_CIT === 'Confirmada')
                                    <a href="{{ route('recepcion.reprogramar', $c->COD_CIT) }}" class="btn">Reprogramar</a>
                                @endif
                                @if (in_array($c->EST_CIT, ['Confirmada', 'Pendiente de pago'], true))
                                    <form method="POST" action="{{ route('recepcion.cancelar', $c->COD_CIT) }}" style="display:inline-flex; gap:6px; margin-top:6px;">
                                        @csrf
                                        <input name="motivo" required minlength="10" maxlength="150" placeholder="Motivo de la cancelación"
                                               style="padding:7px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; width: 200px;">
                                        <button type="submit" class="btn peligro" onclick="return confirm('Se cancela por la clínica y se devuelve el 100 % del anticipo (RN21). ¿Continuar?');">Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
