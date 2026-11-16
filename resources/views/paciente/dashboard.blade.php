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

        @if (session('ok'))
            <div style="margin-bottom: 25px; padding: 16px; border-radius: 12px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                <i class="fas fa-circle-check"></i>
                {{ session('ok') }}
            </div>
        @endif

        @if (session('aviso'))
            <div style="margin-bottom: 25px; padding: 16px; border-radius: 12px; background: #fffbeb; color: #92400e; border: 1px solid #fde68a;">
                <i class="fas fa-triangle-exclamation"></i>
                {{ session('aviso') }}
            </div>
        @endif

        @if ($errors->any())
            <div style="margin-bottom: 25px; padding: 16px; border-radius: 12px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;">
                <i class="fas fa-circle-exclamation"></i>
                {{ $errors->first() }}
            </div>
        @endif


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

    <div style="margin: 15px 0;">
        <a href="{{ route('paciente.citas.crear') }}"
           style="display: inline-block;
                  background: #059669;
                  color: white;
                  padding: 10px 18px;
                  border-radius: 8px;
                  text-decoration: none;
                  font-weight: 600;">
            <i class="fas fa-calendar-plus"></i>
            Agendar nueva cita
        </a>
    </div>

    @if ($citas->isEmpty())

        <p>
            No tienes citas médicas registradas.
        </p>

    @else

        <div style="overflow-x: auto; margin-top: 20px;">

            <table style="width: 100%; border-collapse: collapse;">

                <thead>
                    <tr style="background: #d1fae5; color: #065f46;">
                        <th style="padding: 12px; text-align: left;">Fecha</th>
                        <th style="padding: 12px; text-align: left;">Hora</th>
                        <th style="padding: 12px; text-align: left;">Médico</th>
                        <th style="padding: 12px; text-align: left;">Servicio</th>
                        <th style="padding: 12px; text-align: left;">Estado</th>
                        <th style="padding: 12px; text-align: left;">Motivo</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($citas as $cita)

                        <tr style="border-bottom: 1px solid #e2e8f0;">

                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }}
                            </td>

                            <td style="padding: 12px;">
                                {{ substr($cita->HOR_CIT, 0, 5) }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $cita->TIT_EMP }} {{ $cita->NOM_EMP }} {{ $cita->APE_EMP }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $cita->NOM_SRV }}
                            </td>

                            <td style="padding: 12px;">
                                @php
                                    $colores = [
                                        'Confirmada'        => ['#d1fae5', '#065f46'],
                                        'Pendiente de pago' => ['#fef3c7', '#92400e'],
                                        'Atendida'          => ['#dbeafe', '#1e40af'],
                                        'Expirada'          => ['#fee2e2', '#991b1b'],
                                        'Cancelada'         => ['#fee2e2', '#991b1b'],
                                        'No asistio'        => ['#f1f5f9', '#475569'],
                                    ];
                                    [$fondo, $letra] = $colores[$cita->EST_CIT] ?? ['#f1f5f9', '#475569'];
                                @endphp

                                <span style="background: {{ $fondo }};
                                             color: {{ $letra }};
                                             padding: 4px 10px;
                                             border-radius: 999px;
                                             font-size: 13px;
                                             font-weight: 600;
                                             white-space: nowrap;">
                                    {{ $cita->EST_CIT }}
                                </span>

                                @if ($cita->EST_CIT === 'Pendiente de pago' && $cita->FECEXP_CIT && \Carbon\Carbon::parse($cita->FECEXP_CIT)->isFuture())
                                    <a href="{{ route('paciente.citas.pagar', $cita->COD_CIT) }}"
                                       style="display: inline-block;
                                              margin-left: 8px;
                                              background: #059669;
                                              color: white;
                                              padding: 5px 12px;
                                              border-radius: 999px;
                                              text-decoration: none;
                                              font-size: 13px;
                                              font-weight: 600;
                                              white-space: nowrap;">
                                        <i class="fas fa-lock"></i>
                                        Pagar anticipo
                                    </a>
                                @endif

                                @if ($cita->NRO_COM_ANT)
                                    <a href="{{ route('paciente.citas.comprobante', $cita->COD_CIT) }}" target="_blank"
                                       style="display: inline-block; margin-left: 8px; background: #eef2ff; color: #3730a3;
                                              padding: 5px 12px; border-radius: 999px; text-decoration: none;
                                              font-size: 13px; font-weight: 600; white-space: nowrap;">
                                        <i class="fas fa-file-pdf"></i>
                                        Comprobante {{ $cita->NRO_COM_ANT }}
                                    </a>
                                @elseif ((int) $cita->ANTICIPOS > 0)
                                    <form method="POST" action="{{ route('paciente.citas.comprobante.reintentar', $cita->COD_CIT) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit"
                                                style="margin-left: 8px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
                                                       padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 600;
                                                       cursor: pointer; white-space: nowrap;">
                                            <i class="fas fa-rotate-right"></i>
                                            Reintentar comprobante
                                        </button>
                                    </form>
                                @endif
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

<section class="informacion" style="margin-top: 30px;">

    <h3>
        <i class="fas fa-prescription"></i>
        Mis recetas médicas
    </h3>

    @if ($recetas->isEmpty())

        <p>
            No tienes recetas médicas registradas.
        </p>

    @else

        <div style="overflow-x: auto; margin-top: 20px;">

            <table style="width: 100%; border-collapse: collapse;">

                <thead>
                    <tr style="background: #d1fae5; color: #065f46;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px;">Observaciones</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($recetas as $receta)

                        <tr style="border-bottom: 1px solid #e2e8f0;">

                            <td style="padding: 12px;">
                                {{ $receta->FEC_REC }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $receta->EST_REC }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $receta->OBS_REC ?? 'Sin observaciones' }}
                            </td>

                        </tr>

                    @endforeach
                </tbody>

            </table>

        </div>

    @endif

</section>

<section class="informacion" style="margin-top: 30px;">

    <h3>
        <i class="fas fa-hand-holding-medical"></i>
        Comprobantes de insumos entregados por familiares
    </h3>

    @if ($insumos->isEmpty())
        <p>No hay insumos entregados por tus familiares.</p>
    @else
        <div style="overflow-x: auto; margin-top: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #d1fae5; color: #065f46;">
                        <th style="padding: 12px;">Comprobante</th>
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Medicamento</th>
                        <th style="padding: 12px;">Cantidad</th>
                        <th style="padding: 12px;">Entregado por</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($insumos as $c)
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px;">
                                <a href="{{ route('paciente.insumos.comprobante', $c->NRO_CEN) }}" target="_blank" style="color: #3730a3; font-weight: 600;">
                                    <i class="fas fa-file-pdf"></i> {{ $c->NRO_CEN }}
                                </a>
                            </td>
                            <td style="padding: 12px;">{{ \Carbon\Carbon::parse($c->FEC_CEN)->format('d/m/Y H:i') }}</td>
                            <td style="padding: 12px;">{{ $c->DES_MED }} {{ $c->DOS_INS }}</td>
                            <td style="padding: 12px;">{{ $c->CAN_INS }}</td>
                            <td style="padding: 12px;">{{ $c->NOM_ENT }} {{ $c->APE_ENT }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</section>


<section class="informacion" style="margin-top: 30px;">

    <h3>
        <i class="fas fa-file-medical"></i>
        Mi historial médico
    </h3>

    @if ($historial->isEmpty())

        <p>No tienes consultas médicas registradas.</p>

    @else

        <div style="overflow-x: auto; margin-top: 20px;">

            <table style="width: 100%; border-collapse: collapse;">

                <thead>
                    <tr style="background: #d1fae5; color: #065f46;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Diagnóstico</th>
                        <th style="padding: 12px;">Tratamiento</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($historial as $consulta)

                        <tr style="border-bottom: 1px solid #e2e8f0;">

                            <td style="padding: 12px;">
                                {{ $consulta->FEC_CNS }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $consulta->DIA_CNS ?? 'No registrado' }}
                            </td>

                            <td style="padding: 12px;">
                                {{ $consulta->PLA_CNS ?? 'No registrado' }}
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
