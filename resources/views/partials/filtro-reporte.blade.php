{{-- Filtro común de los reportes: periodo (día, semana, mes, año o rango), especialidad y médico.
     Variables: $f (filtro actual), $catalogos (especialidades y médicos), $ruta, $sinMedico (opcional), $sinEspecialidad (opcional) --}}
<form method="GET" action="{{ $ruta }}" class="filtro-reporte {{ $f['periodo'] === 'rango' ? 'rango' : '' }}" id="filtro-reporte">
    <div>
        <label for="f-periodo">Ver por</label>
        <select id="f-periodo" name="periodo">
            @foreach (['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'anio' => 'Año', 'rango' => 'Rango de fechas'] as $v => $t)
                <option value="{{ $v }}" @selected($f['periodo'] === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="grupo-fecha">
        <label for="f-fecha">Fecha de referencia</label>
        <input id="f-fecha" type="date" name="fecha" value="{{ $f['fecha'] }}">
    </div>
    <div class="grupo-rango">
        <div>
            <label for="f-desde">Desde</label>
            <input id="f-desde" type="date" name="desde" value="{{ $f['desde'] }}">
        </div>
        <div>
            <label for="f-hasta">Hasta</label>
            <input id="f-hasta" type="date" name="hasta" value="{{ $f['hasta'] }}">
        </div>
    </div>
    @empty($sinEspecialidad)
        <div>
            <label for="f-esp">Especialidad</label>
            <select id="f-esp" name="especialidad">
                <option value="">Todas</option>
                @foreach ($catalogos['especialidades'] as $e)
                    <option value="{{ $e->COD_ESP }}" @selected($f['especialidad'] === $e->COD_ESP)>{{ $e->DES_ESP }}</option>
                @endforeach
            </select>
        </div>
    @endempty
    @empty($sinMedico)
        <div>
            <label for="f-med">Médico</label>
            <select id="f-med" name="medico">
                <option value="">Todos</option>
                @foreach ($catalogos['medicos'] as $m)
                    <option value="{{ $m->COD_EMP }}" data-esp="{{ $m->COD_ESP }}" @selected($f['medico'] === $m->COD_EMP)>
                        {{ $m->TIT_EMP }} {{ $m->NOM_EMP }} {{ $m->APE_EMP }} · {{ $m->DES_ESP }}
                    </option>
                @endforeach
            </select>
        </div>
    @endempty
    <div class="acciones">
        <button type="submit" class="btn principal"><i class="fas fa-filter"></i> Aplicar</button>
        <a href="{{ $ruta }}" class="btn"><i class="fas fa-rotate-left"></i> Este mes</a>
        @isset($exportar)
            <a href="{{ $exportar }}" class="btn"><i class="fas fa-file-excel" style="color:#1d6f42;"></i> Excel</a>
        @endisset
        <button type="button" class="btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / PDF</button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('filtro-reporte');
        if (!form) return;
        const periodo = document.getElementById('f-periodo');
        periodo.addEventListener('change', () => form.classList.toggle('rango', periodo.value === 'rango'));
        // Al cambiar de periodo se aplica de inmediato (día/semana/mes/año)
        periodo.addEventListener('change', () => { if (periodo.value !== 'rango') form.submit(); });

        // Los médicos se filtran según la especialidad elegida
        const esp = document.getElementById('f-esp');
        const med = document.getElementById('f-med');
        if (esp && med) {
            const filtrar = () => {
                [...med.options].forEach(o => { if (o.value) o.hidden = esp.value !== '' && o.dataset.esp !== esp.value; });
                if (med.selectedOptions[0] && med.selectedOptions[0].hidden) med.value = '';
            };
            esp.addEventListener('change', filtrar);
            filtrar();
        }
    })();
</script>
@endpush
