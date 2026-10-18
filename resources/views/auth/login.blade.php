<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clínica CIES — Iniciar sesión</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #0d1117;
            color: #e6edf3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            width: 100%;
            max-width: 380px;
            background: #161b22;
            border: 1px solid #263041;
            border-radius: 12px;
            padding: 32px;
        }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .sub { color: #8b98a8; font-size: 14px; margin-bottom: 24px; }
        label { display: block; font-size: 13px; color: #8b98a8; margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 16px;
            background: #0d1117;
            border: 1px solid #263041;
            border-radius: 8px;
            color: #e6edf3;
            font-size: 15px;
        }
        input:focus { outline: none; border-color: #2dd4bf; }
        button {
            width: 100%;
            padding: 11px;
            background: #2dd4bf;
            color: #06201c;
            border: 0;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #5eead4; }
        .error {
            background: #3b1219;
            border: 1px solid #7f1d2d;
            color: #fda4af;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Clínica CIES</h1>
        <p class="sub">Inicia sesión para continuar</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario"
            value="{{ old('usuario') }}" autofocus required>

            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required>

            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>