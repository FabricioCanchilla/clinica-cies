@php
    $e = $empleado ?? null;
    $v = fn ($campo, $col) => old($campo, $e->$col ?? '');
    $cargoActual = old('cargo', $e->COD_CAR ?? '');
@endphp

@csrf

<div class="fila">
    <div>
        <label for="ci">Cédula de identidad</label>
        <input type="text" id="ci" name="ci" value="{{ $v('ci', 'CI_EMP') }}" maxlength="10" required>
    </div>
    <div>
        <label for="titulo">Título</label>
        <select id="titulo" name="titulo" required>
            <option value="">Selecciona</option>
            @foreach ($titulos as $t)
                <option value="{{ $t }}" @selected($v('titulo', 'TIT_EMP') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="fila">
    <div>
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="{{ $v('nombre', 'NOM_EMP') }}" maxlength="40" required>
    </div>
    <div>
        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" value="{{ $v('apellido', 'APE_EMP') }}" maxlength="40" required>
    </div>
</div>

<div class="fila">
    <div>
        <label for="sexo">Sexo</label>
        <select id="sexo" name="sexo" required>
            <option value="">Selecciona</option>
            <option value="M" @selected($v('sexo', 'SEX_EMP') === 'M')>Masculino</option>
            <option value="F" @selected($v('sexo', 'SEX_EMP') === 'F')>Femenino</option>
        </select>
    </div>
    <div>
        <label for="nacimiento">Fecha de nacimiento</label>
        <input type="date" id="nacimiento" name="nacimiento" value="{{ $v('nacimiento', 'FECNAC_EMP') }}"
               max="{{ now()->subYears(18)->format('Y-m-d') }}" style="color-scheme: dark;" required>
    </div>
</div>

<div class="fila">
    <div>
        <label for="telefono">Teléfono (8 dígitos)</label>
        <input type="text" id="telefono" name="telefono" value="{{ $v('telefono', 'TEL_EMP') }}"
               maxlength="8" inputmode="numeric" required>
    </div>
    <div>
        <label for="correo">Correo</label>
        <input type="email" id="correo" name="correo" value="{{ $v('correo', 'COR_EMP') }}" maxlength="60" required>
    </div>
</div>

<label for="direccion">Dirección</label>
<input type="text" id="direccion" name="direccion" value="{{ $v('direccion', 'DIR_EMP') }}" maxlength="100" required>

<div class="fila">
    <div>
        <label for="ingreso">Fecha de ingreso</label>
        <input type="date" id="ingreso" name="ingreso" value="{{ $v('ingreso', 'FECING_EMP') }}"
               max="{{ now()->format('Y-m-d') }}" style="color-scheme: dark;" required>
    </div>
    <div>
        <label for="turno">Turno</label>
        <select id="turno" name="turno" required>
            <option value="">Selecciona</option>
            @foreach ($turnos as $t)
                <option value="{{ $t }}" @selected($v('turno', 'TURNO_EMP') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
</div>

<label for="cargo">Cargo</label>
<select id="cargo" name="cargo" required>
    <option value="">Selecciona un cargo</option>
    @foreach ($cargos as $c)
        <option value="{{ $c->COD_CAR }}" @selected($cargoActual === $c->COD_CAR)>{{ $c->DES_CAR }}</option>
    @endforeach
</select>

{{-- Solo para médicos --}}
<div id="bloque-medico" style="display: none;">
    <div class="fila">
        <div>
            <label for="matricula">Matrícula profesional</label>
            <input type="text" id="matricula" name="matricula" value="{{ $v('matricula', 'MAT_EMP') }}" maxlength="12">
        </div>
        <div>
            <label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad">
                <option value="">Selecciona</option>
                @foreach ($especialidades as $esp)
                    <option value="{{ $esp->COD_ESP }}" @selected($v('especialidad', 'COD_ESP') === $esp->COD_ESP)>{{ $esp->DES_ESP }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<script>
    (function () {
        const cargosMedico = @json($cargosMedico);
        const cargo = document.getElementById('cargo');
        const bloque = document.getElementById('bloque-medico');
        function actualizar() {
            bloque.style.display = cargosMedico.includes(cargo.value) ? 'block' : 'none';
        }
        cargo.addEventListener('change', actualizar);
        actualizar();
    })();
</script>