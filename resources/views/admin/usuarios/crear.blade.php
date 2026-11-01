@extends('layouts.admin')

@section('titulo', 'Nuevo usuario')

@section('contenido')
    <h1>Nuevo usuario</h1>
    <p class="sub">Crea la cuenta de acceso de un empleado. Deberá cambiar la contraseña en su primer ingreso.</p>

    @if ($empleados->isEmpty())
        <div class="alerta error">No hay empleados sin usuario. Primero hay que registrar al empleado.</div>
        <a href="{{ route('admin.usuarios') }}" class="btn">← Volver</a>
    @else
        <form method="POST" action="{{ route('admin.usuarios.guardar') }}" class="card formulario">
            @csrf

            <label for="empleado">Empleado</label>
            <select id="empleado" name="empleado" required>
                <option value="">Selecciona un empleado</option>
                @foreach ($empleados as $e)
                    <option value="{{ $e->COD_EMP }}" @selected(old('empleado') === $e->COD_EMP)>
                        {{ $e->NOM_EMP }} {{ $e->APE_EMP }} · CI {{ $e->CI_EMP }}
                    </option>
                @endforeach
            </select>

            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="">Selecciona un rol</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->COD_ROL }}" @selected(old('rol') === $r->COD_ROL)>{{ $r->DES_ROL }}</option>
                @endforeach
            </select>

            <label for="usuario">Nombre de usuario</label>
            <input type="text" id="usuario" name="usuario" value="{{ old('usuario') }}"
                   maxlength="20" autocomplete="off" required>

            <label for="clave">Contraseña temporal (mínimo 8 caracteres)</label>
            <input type="password" id="clave" name="clave" autocomplete="new-password" required>

            <label for="clave_confirmation">Repite la contraseña temporal</label>
            <input type="password" id="clave_confirmation" name="clave_confirmation" autocomplete="new-password" required>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn principal">Crear usuario</button>
                <a href="{{ route('admin.usuarios') }}" class="btn">Cancelar</a>
            </div>
        </form>
    @endif
@endsection