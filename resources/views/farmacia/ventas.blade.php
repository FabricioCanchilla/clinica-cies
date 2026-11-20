@extends('layouts.admin')

@section('titulo', 'Venta en mostrador')

@section('contenido')
    <h1>Venta en mostrador</h1>
    <p class="sub">RF041: venta a pacientes ambulantes o consumo cargado a la cuenta de un hospitalizado. El stock sale por PEPS y se emite el recibo.</p>

    @if (session('recibo'))
        <div class="alerta info"><i class="fas fa-receipt"></i> Recibo listo:
            <a href="{{ route('farmacia.ventas.pdf', session('recibo')) }}" target="_blank"><strong>{{ session('recibo') }}</strong> (PDF)</a></div>
    @endif

    <div class="card formulario ancho" style="margin-bottom: 24px; max-width: 900px;">
        <form method="POST" action="{{ route('farmacia.ventas.guardar') }}" id="form-venta">
            @csrf
            <div class="fila">
                <div>
                    <label for="tipo">Tipo de venta</label>
                    <select id="tipo" name="tipo">
                        <option value="Ambulante" @selected(old('tipo') !== 'Hospitalizado')>Paciente ambulante / cliente</option>
                        <option value="Hospitalizado" @selected(old('tipo') === 'Hospitalizado')>Cargar a un hospitalizado</option>
                    </select>
                </div>
                <div class="solo-amb">
                    <label for="metodo">Método de pago</label>
                    <select id="metodo" name="metodo">
                        @foreach ($metodos as $m)<option value="{{ $m->COD_MET }}" @selected(old('metodo') === $m->COD_MET)>{{ $m->DES_MET }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="fila solo-amb">
                <div><label for="cliente">Nombre del cliente (opcional)</label><input id="cliente" name="cliente" maxlength="100" value="{{ old('cliente') }}"></div>
                <div><label for="ci">CI del paciente (opcional, vincula la venta)</label><input id="ci" name="ci" maxlength="10" value="{{ old('ci') }}"></div>
            </div>
            <div class="solo-hosp">
                <label for="internacion">Paciente internado</label>
                <select id="internacion" name="internacion">
                    <option value="">—</option>
                    @foreach ($internados as $i)<option value="{{ $i->COD_INT }}" @selected(old('internacion') === $i->COD_INT)>Cama {{ $i->NRO_CAM }} · {{ $i->APE_PAC }}, {{ $i->NOM_PAC }} · CI {{ $i->CI_PAC }}</option>@endforeach
                </select>
            </div>

            <label>Medicamentos</label>
            <div class="tabla">
            <table style="margin-bottom: 12px;" id="tabla-items">
                <thead><tr><th>Medicamento (stock vigente)</th><th style="width: 110px;">Cantidad</th><th class="derecha" style="width: 110px;">Importe</th></tr></thead>
                <tbody>
                @for ($i = 0; $i < 5; $i++)
                    <tr>
                        <td style="padding: 6px;">
                            <select name="items[{{ $i }}][med]" class="it-med" style="margin: 0;">
                                <option value="" data-pre="0">—</option>
                                @foreach ($catalogo as $m)
                                    <option value="{{ $m->COD_MED }}" data-pre="{{ $m->PRE_MED }}" @disabled($m->STOCK <= 0) @selected(old("items.$i.med") === $m->COD_MED)>
                                        {{ $m->DES_MED }} · Bs {{ number_format($m->PRE_MED, 2) }} · stock {{ $m->STOCK }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td style="padding: 6px;"><input type="number" min="1" max="1000" name="items[{{ $i }}][cantidad]" class="it-can" value="{{ old("items.$i.cantidad") }}" style="margin: 0;"></td>
                        <td class="derecha it-imp" style="padding: 6px;">0.00</td>
                    </tr>
                @endfor
                </tbody>
                <tfoot><tr><td colspan="2"><strong>Total Bs</strong></td><td class="derecha"><strong id="total">0.00</strong></td></tr></tfoot>
            </table>
            </div>
            <button class="btn principal"><i class="fas fa-cash-register"></i> Registrar venta y emitir recibo</button>
        </form>
    </div>

    <div class="card tabla">
        <h2><i class="fas fa-receipt"></i> Ventas de hoy</h2>
        @if ($ventas->isEmpty())
            <div class="vacio">Todavía no hay ventas hoy.</div>
        @else
            <table>
                <thead><tr><th>N°</th><th>Hora</th><th>Cliente</th><th>Tipo</th><th class="derecha">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach ($ventas as $v)
                    <tr>
                        <td>{{ $v->NRO_VEN }}</td>
                        <td>{{ \Carbon\Carbon::parse($v->FEC_VEN)->format('H:i') }}</td>
                        <td>{{ $v->CLI_VEN }}</td>
                        <td>{{ $v->TIP_VEN }}{{ $v->COD_INT ? ' · ' . $v->COD_INT : '' }}</td>
                        <td class="derecha">Bs {{ number_format($v->TOT_VEN, 2) }}</td>
                        <td><span class="badge {{ $v->EST_VEN === 'Emitida' ? 'activo' : 'bloqueado' }}">{{ $v->EST_VEN }}</span></td>
                        <td>
                            <div class="acciones">
                                <a class="btn sm" target="_blank" href="{{ route('farmacia.ventas.pdf', $v->NRO_VEN) }}"><i class="fas fa-file-pdf"></i> Recibo</a>
                                @if ($v->EST_VEN === 'Emitida')
                                    <form method="POST" action="{{ route('farmacia.ventas.anular', $v->NRO_VEN) }}" class="form-anular">
                                        @csrf <input type="hidden" name="motivo">
                                        <button class="btn sm peligro">Anular</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const tipo = document.getElementById('tipo');
    const vista = () => {
        const h = tipo.value === 'Hospitalizado';
        document.querySelectorAll('.solo-amb').forEach(e => e.style.display = h ? 'none' : '');
        document.querySelectorAll('.solo-hosp').forEach(e => e.style.display = h ? '' : 'none');
    };
    const calcular = () => {
        let total = 0;
        document.querySelectorAll('#tabla-items tbody tr').forEach(tr => {
            const s = tr.querySelector('.it-med'), c = tr.querySelector('.it-can');
            const op = s.selectedOptions[0];
            const imp = parseFloat(op ? op.dataset.pre : 0) * parseInt(c.value || 0, 10);
            tr.querySelector('.it-imp').textContent = imp.toFixed(2); total += imp;
        });
        document.getElementById('total').textContent = total.toFixed(2);
    };
    document.querySelectorAll('.form-anular').forEach(f => f.addEventListener('submit', e => {
        const m = prompt('Motivo de la anulación (mínimo 5 caracteres)');
        if (!m || m.trim().length < 5) { e.preventDefault(); return; }
        f.querySelector('input[name=motivo]').value = m.trim();
    }));
    tipo.addEventListener('change', vista);
    document.getElementById('form-venta').addEventListener('input', calcular);
    document.getElementById('form-venta').addEventListener('change', calcular);
    vista(); calcular();
})();
</script>
@endpush
