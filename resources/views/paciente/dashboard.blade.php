
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi espacio de salud | Clínica CIES</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0fdf4;
            color: #1e293b;
        }

        .encabezado {
            background: linear-gradient(135deg, #047857, #10b981);
            color: white;
            padding: 24px 6%;
        }

        .barra {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .marca {
            font-size: 24px;
            font-weight: bold;
        }

        .marca i {
            margin-right: 10px;
        }

        .salir {
            background: white;
            color: #047857;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-weight: bold;
            cursor: pointer;
        }

        .contenedor {
            max-width: 1200px;
            margin: auto;
            padding: 35px 20px;
        }

        .bienvenida {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,.05);
            border-left: 6px solid #10b981;
        }

        .bienvenida h1 {
            color: #065f46;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .bienvenida p {
            color: #64748b;
            line-height: 1.7;
        }

        .seccion {
            font-size: 22px;
            margin-bottom: 20px;
            color: #064e3b;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 22px;
        }

        .tarjeta {
            background: white;
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,.05);
            border: 1px solid #d1fae5;
            transition: transform .2s, box-shadow .2s;
        }

        .tarjeta:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 28px rgba(0,0,0,.10);
        }

        .icono {
            width: 60px;
            height: 60px;
            background: #d1fae5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 26px;
            margin-bottom: 20px;
        }

        .tarjeta h3 {
            margin-bottom: 12px;
            color: #064e3b;
        }

        .tarjeta p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .informacion {
            margin-top: 32px;
            background: white;
            padding: 25px;
            border-radius: 18px;
            border: 1px solid #d1fae5;
        }

        .informacion h3 {
            color: #065f46;
            margin-bottom: 12px;
        }

        .informacion p {
            color: #64748b;
            line-height: 1.8;
        }

        @media (max-width: 600px) {
            .bienvenida h1 {
                font-size: 23px;
            }

            .contenedor {
                padding: 20px 14px;
            }
        }
    </style>
</head>

<body>

    <header class="encabezado">
        <div class="barra">
            <div class="marca">
                <i class="fas fa-heartbeat"></i>
                Clínica CIES
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="salir">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </header>

    <main class="contenedor">

        <section class="bienvenida">
            <h1>
                ¡Bienvenido, {{ auth()->user()->USR_USR }}!
            </h1>

            <p>
                Bienvenido a tu espacio personal de salud.
                Desde aquí podrás consultar información
                relacionada con tu atención en Clínica CIES.
            </p>
        </section>

        <h2 class="seccion">Mi espacio de salud</h2>

        <div class="tarjetas">

            <div class="tarjeta">
                <div class="icono">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Mis citas</h3>
                <p>
                    Consulta las citas médicas
                    asociadas a tu cuenta.
                </p>
            </div>

            <div class="tarjeta">
                <div class="icono">
                    <i class="fas fa-file-medical"></i>
                </div>
                <h3>Mi historial médico</h3>
                <p>
                    Consulta tu información clínica
                    y antecedentes médicos.
                </p>
            </div>

            <div class="tarjeta">
                <div class="icono">
                    <i class="fas fa-prescription"></i>
                </div>
                <h3>Mis recetas</h3>
                <p>
                    Consulta las recetas médicas
                    registradas a tu nombre.
                </p>
            </div>

            <div class="tarjeta">
                <div class="icono">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3>Mi perfil</h3>
                <p>
                    Consulta tus datos personales
                    registrados en la clínica.
                </p>
            </div>

        </div>

        
<section class="informacion" style="margin-top: 30px;">

    <h3>
        <i class="fas fa-calendar-check"></i>
        Mis citas médicas
    </h3>

    @if ($citas->isEmpty())

        <p>
            No tienes citas médicas registradas.
        </p>

    @else

        <div style="overflow-x: auto; margin-top: 20px;">

            <table style="width: 100%; border-collapse: collapse;">

                <thead>
                    <tr style="background: #d1fae5; color: #065f46;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Hora</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Motivo</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($citas as $cita)

                        <tr style="border-bottom: 1px solid #e2e8f0;">

                            <td style="padding: 12px;">
                                {{ $cita->FEC_CIT }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $cita->HOR_CIT }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $cita->EST_CIT }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $cita->MOT_CIT ?? 'Sin motivo registrado' }}
                            </td>

                        </tr>

                    @endforeach
                </tbody>

            </table>

        </div>

    @endif

</section>


<section class="informacion">

    <h3>
        <i class="fas fa-user-circle"></i>
        Mi perfil
    </h3>

    @if ($paciente)

        <p>
            <strong>Nombre:</strong>
            {{ $paciente->NOM_PAC }}
        </p>

        <p>
            <strong>Apellido:</strong>
            {{ $paciente->APE_PAC }}
        </p>

        <p>
            <strong>CI:</strong>
            {{ $paciente->CI_PAC }}
        </p>

        <p>
            <strong>Teléfono:</strong>
            {{ $paciente->TEL_PAC ?? 'No registrado' }}
        </p>

        <p>
            <strong>Correo:</strong>
            {{ $paciente->COR_PAC ?? 'No registrado' }}
        </p>

    @else

        <p>
            Tu cuenta todavía no tiene un paciente
            asociado. Contacta con administración
            para verificar tus datos.
        </p>

    @endif

</section>


    </main>

</body>
</html>
