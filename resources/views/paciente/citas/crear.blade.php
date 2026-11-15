<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar cita | Clínica CIES</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0fdf4;
            color: #1e293b;
        }

        header {
            padding: 22px 8%;
            background: linear-gradient(135deg, #047857, #10b981);
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        main {
            max-width: 900px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .panel {
            background: white;
            padding: 30px;
            border: 1px solid #d1fae5;
            border-radius: 18px;
        }

        h1 { color: #065f46; }

        .campo { margin: 20px 0; }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        select, input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .aviso {
            margin-top: 22px;
            padding: 16px;
            border-radius: 10px;
            background: #ecfdf5;
            color: #065f46;
        }

        .volver {
            display: inline-block;
            margin-top: 22px;
            color: #047857;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

<header>Clínica CIES</header>

<main>
    <section class="panel">

        <h1>Agendar cita médica</h1>

        <p>Consulta los servicios y profesionales registrados.</p>

        <div class="campo">
            <label>Servicio médico</label>
            <select disabled>
                @forelse ($servicios as $servicio)
                    <option>
                        {{ $servicio->NOM_SRV }}
                        - Bs {{ $servicio->PRE_SRV }}
                    </option>
                @empty
                    <option>No hay servicios disponibles</option>
                @endforelse
            </select>
        </div>

        <div class="campo">
            <label>Especialidad</label>
            <select disabled>
                @forelse ($especialidades as $especialidad)
                    <option>
                        {{ $especialidad->NOM_ESP ?? $especialidad->COD_ESP }}
                    </option>
                @empty
                    <option>No hay especialidades registradas</option>
                @endforelse
            </select>
        </div>

        <div class="campo">
            <label>Médico</label>
            <select disabled>
                @forelse ($medicos as $medico)
                    <option>
                        {{ $medico->NOM_EMP }}
                        {{ $medico->APE_EMP }}
                    </option>
                @empty
                    <option>No hay médicos registrados</option>
                @endforelse
            </select>
        </div>

        <div class="campo">
            <label>Horarios disponibles</label>
            <select disabled>
                @forelse ($horarios as $horario)
                    <option>
                        {{ $horario->DIA_HOR }}
                        {{ $horario->HORINI_HOR }}
                        - {{ $horario->HORFIN_HOR }}
                    </option>
                @empty
                    <option>No hay horarios registrados</option>
                @endforelse
            </select>
        </div>

        <div class="aviso">
            El agendamiento todavía no está habilitado.
            Se requiere verificar la disponibilidad médica,
            la confirmación OTP y el proceso de pago.
        </div>

        <a class="volver" href="{{ route('paciente.dashboard') }}">
            Volver a mi panel
        </a>

    </section>
</main>

</body>
</html>
