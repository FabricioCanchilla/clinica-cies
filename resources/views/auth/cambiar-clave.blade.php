<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica CIES — Cambiar contraseña</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #0d1117; color: #e6edf3; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            width: 100%; max-width: 400px; background: #161b22;
            border: 1px solid #263041; border-radius: 12px; padding: 32px;
        }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .sub { color: #8b98a8; font-size: 14px; margin-bottom: 24px; }
        label { display: block; font-size: 13px; color: #8b98a8; margin-bottom: 6px; }
        input {
            width: 100%; padding: 10px 12px; margin-bottom: 16px;
            background: #0d1117; border: 1px solid #263041; border-radius: 8px;
            color: #e6edf3; font-size: 15px;
        }
        input:focus { outline: none; border-color: #2dd4bf; }
        button.principal {
            width: 100%; padding: 11px; background: #2dd4bf; color: #06201c;
            border: 0; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        button.principal:hover { background: #5eead4; }
        .error {
            background: #3b1219; border: 1px solid #7f1d2d; color: #fda4af;
            padding: 10px 12px; border-radius: 8px; font-size: 14px; margin-bottom: 16px;
        }
        .aviso {
            background: rgba(45,212,191,.12); color: #2dd4bf;
            padding: 10px 12px; border-radius: 8px; font-size: 14px; margin-bottom: 16px;
        }
        .salir { margin-top: 14px; text-align: center; }
        .salir button {
            background: none; border: 0; color: #8b98a8; cursor: pointer;
            font-size: 13px; text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Cambiar contraseña</h1>
        <p class="sub">{{ auth()->user()->USR_USR }}</p>

        @if ($obligatorio)
            <div class="aviso">Tu contraseña es temporal. Debes cambiarla para continuar.</div>
        @endif

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('clave.actualizar') }}">
            @csrf
            <label for="actual">Contraseña actual</label>
            <input type="password" id="actual" name="actual" autofocus required>

            <label for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
            <input type="password" id="nueva" name="nueva" required>

            <label for="nueva_confirmation">Repite la nueva contraseña</label>
            <input type="password" id="nueva_confirmation" name="nueva_confirmation" required>

            <button type="submit" class="principal">Guardar contraseña</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="salir">
            @csrf
            <button type="submit">Cerrar sesión</button>
        </form>
    </div>
</body>
</html>