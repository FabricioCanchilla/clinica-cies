@extends('layouts.admin')

@section('titulo', 'Editar usuario')

@section('contenido')
    <h1>Editar usuario</h1>
    <p class="sub">Cambia el nombre de usuario o el rol de la cuenta {{ $usuario->COD_USR }}.</p>

    <form method="POST"
          action="{{ route('admin.usuarios.actualizar', $usuario->COD_USR) }}"
          class="card formulario"
          id="form-editar">
        @csrf
        @method('PUT')

        <label>Código</label>
        <input type="text" value="{{ $usuario->COD_USR }}" disabled>

        <label for="usuario">Nombre de usuario</label>
        <input type="text" id="usuario" name="usuario"
               value="{{ old('usuario', $usuario->USR_USR) }}"
               maxlength="20" autocomplete="off" required>

        <label for="rol">Rol</label>
        @if ($esPaciente)
            <input type="text" value="Paciente (no se puede cambiar)" disabled>
        @elseif ($esPropio)
            <input type="text" value="No puedes cambiar tu propio rol" disabled>
        @else
            <select id="rol" name="rol" required>
                @foreach ($roles as $r)
                    <option value="{{ $r->COD_ROL }}"
                        @selected(old('rol', $usuario->COD_ROL) === $r->COD_ROL)>
                        {{ $r->DES_ROL }}
                    </option>
                @endforeach
            </select>
        @endif

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn principal">Guardar cambios</button>
            <a href="{{ route('admin.usuarios') }}" class="btn">Cancelar</a>
        </div>
    </form>

    @if (!$esPaciente && !$esPropio)
        <script>
            document.getElementById('form-editar').addEventListener('submit', function (e) {
                var rolOriginal = @json($usuario->COD_ROL);
                var rolNuevo = document.getElementById('rol').value;
                if (rolNuevo !== rolOriginal && (rolNuevo === 'ROL001' || rolOriginal === 'ROL001')) {
                    if (!confirm('Vas a asignar o quitar el rol de Administrador. ¿Confirmas el cambio?')) {
                        e.preventDefault();
                    }
                }
            });
        </script>
    @endif
@endsection