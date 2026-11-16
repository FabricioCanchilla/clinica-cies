<?php

namespace App\Services;


use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CitaService
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    public function __construct(private OtpClient $otp, private ComprobanteService $comprobantes)
    {
    }

    // ---------- Catálogos para el formulario ----------

    public function especialidades()
    {
        return DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get();
    }

    public function medicos(string $codEsp)
    {
        return DB::table('EMPLEADO')
            ->where('COD_ESP', $codEsp)
            ->orderBy('APE_EMP')
            ->get(['COD_EMP', 'TIT_EMP', 'NOM_EMP', 'APE_EMP', 'COD_ESP']);
    }

    public function serviciosConsulta()
    {
        return DB::table('SERVICIO as s')
            ->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
            ->where('c.DES_CLA', 'like', 'Consulta%')
            ->orderBy('s.NOM_SRV')
            ->get(['s.COD_SRV', 's.NOM_SRV', 's.PRE_SRV']);
    }

    public function metodosPago()
    {
        return DB::table('METODO_PAGO')->orderBy('DES_MET')->get();
    }

    // ---------- Disponibilidad ----------

    /** Horas libres de un médico en una fecha. Un pendiente de pago vencido NO bloquea (RNF007). */
    public function horariosLibres(string $codEmp, Carbon $fecha): array
    {
        $dia   = self::DIAS[$fecha->dayOfWeek];
        $ahora = Carbon::now();
        $dur   = (int) config('cies.duracion_cita_min');

        $bloques = DB::table('HORARIO_MEDICO')
            ->where('COD_EMP', $codEmp)
            ->where('DIA_HOR', $dia)
            ->orderBy('HORINI_HOR')
            ->get();

        if ($bloques->isEmpty()) {
            return [];
        }

        $ocupadas = DB::table('CITA')
            ->where('COD_EMP', $codEmp)
            ->where('FEC_CIT', $fecha->toDateString())
            ->where(function ($q) use ($ahora) {
                $q->whereIn('EST_CIT', ['Confirmada', 'Atendida'])
                  ->orWhere(function ($q2) use ($ahora) {
                      $q2->where('EST_CIT', 'Pendiente de pago')
                         ->where('FECEXP_CIT', '>=', $ahora->toDateTimeString());
                  });
            })
            ->pluck('HOR_CIT')
            ->map(fn ($h) => substr($h, 0, 5))
            ->all();

        $libres = [];
        foreach ($bloques as $b) {
            $t   = Carbon::parse($fecha->toDateString() . ' ' . $b->HORINI_HOR);
            $fin = Carbon::parse($fecha->toDateString() . ' ' . $b->HORFIN_HOR);
            while ($t->copy()->addMinutes($dur)->lte($fin)) {
                $hora = $t->format('H:i');
                if ($t->gt($ahora) && !in_array($hora, $ocupadas, true)) {
                    $libres[] = ['hora' => $hora, 'cod_con' => $b->COD_CON];
                }
                $t->addMinutes($dur);
            }
        }
        return $libres;
    }

    // ---------- RF007: reservar (bloquea el horario 15 min) ----------

    public function reservar(string $codPac, string $codEmp, string $fecha, string $hora, string $codSrv, string $motivo): object
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 200) {
            throw new DomainException('Indica el motivo de la consulta (máximo 200 caracteres).');
        }
        if (!Carbon::hasFormat($fecha, 'Y-m-d')) {
            throw new DomainException('Fecha inválida.');
        }
        $dia = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        $max = (int) config('cies.dias_max_anticipacion');
        if ($dia->lt(Carbon::today()) || $dia->gt(Carbon::today()->addDays($max))) {
            throw new DomainException("La fecha debe estar entre hoy y los próximos {$max} días.");
        }

        return DB::transaction(function () use ($codPac, $codEmp, $dia, $fecha, $hora, $codSrv, $motivo) {
            // Serializa las reservas de este médico: dos pacientes no pueden tomar la misma hora
            $medico = DB::table('EMPLEADO')
                ->where('COD_EMP', $codEmp)->whereNotNull('COD_ESP')
                ->lockForUpdate()->first();
            if (!$medico) {
                throw new DomainException('Médico no válido.');
            }
            if (!DB::table('SERVICIO')->where('COD_SRV', $codSrv)->exists()) {
                throw new DomainException('Servicio no válido.');
            }

            $slot = collect($this->horariosLibres($codEmp, $dia))->firstWhere('hora', $hora);
            if (!$slot) {
                throw new DomainException('Ese horario ya no está disponible. Elige otro.');
            }

            $codCit = sprintf('CIT%07d', (int) DB::table('CITA')->max('id') + 1);
            $expira = Carbon::now()->addMinutes((int) config('cies.minutos_bloqueo'));

            DB::table('CITA')->insert([
                'COD_CIT'    => $codCit,
                'FEC_CIT'    => $fecha,
                'HOR_CIT'    => $hora . ':00',
                'DUR_CIT'    => (int) config('cies.duracion_cita_min'),
                'EST_CIT'    => 'Pendiente de pago',
                'MOT_CIT'    => $motivo,
                'FECEXP_CIT' => $expira->toDateTimeString(),
                'COD_PAC'    => $codPac,
                'COD_EMP'    => $codEmp,
                'COD_CON'    => $slot['cod_con'],
                'COD_SRV'    => $codSrv,
            ]);

            Auditoria::registrar(
                'Reservar cita', 'Baja', 'DOM04', 'CITA', $codCit,
                "Horario {$fecha} {$hora} bloqueado hasta las " . $expira->format('H:i'),
                codPac: $codPac
            );

            return DB::table('CITA')->where('COD_CIT', $codCit)->first();
        });
    }

    // ---------- Consultas de citas ----------

    /** Cita del paciente lista para pagar. Si venció, la marca Expirada y avisa. */
    public function citaParaPago(string $codCit, string $codPac): object
    {
        $c = DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')
            ->where('c.COD_CIT', $codCit)
            ->where('c.COD_PAC', $codPac)
            ->first([
                'c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT', 'c.FECEXP_CIT',
                'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV', 's.PRE_SRV', 'k.NOM_CON',
            ]);

        if (!$c) {
            throw new DomainException('Cita no encontrada.');
        }
        if ($c->EST_CIT !== 'Pendiente de pago') {
            throw new DomainException("Esta cita ya no está pendiente de pago (estado: {$c->EST_CIT}).");
        }
        if (Carbon::parse($c->FECEXP_CIT)->lt(Carbon::now())) {
            DB::table('CITA')->where('COD_CIT', $codCit)->where('EST_CIT', 'Pendiente de pago')
                ->update(['EST_CIT' => 'Expirada']);
            throw new DomainException('El tiempo para pagar el anticipo venció y el horario fue liberado. Agenda una nueva cita.');
        }

        $c->anticipo = $this->montoAnticipo((float) $c->PRE_SRV);
        return $c;
    }

    public function citasDelPaciente(string $codPac)
    {
        return DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('c.FEC_CIT')->orderByDesc('c.HOR_CIT')
            ->select(['c.COD_CIT', 'c.FEC_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT', 'c.FECEXP_CIT',
                      'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV'])
            // Comprobante del anticipo (en línea) y si hay un anticipo confirmado sin comprobante
            ->selectSub(DB::table('COMPROBANTE')->select('NRO_COM')
                ->whereColumn('COD_CIT', 'c.COD_CIT')->where('EST_COM', 'Emitido')->whereNull('COD_CAJ')
                ->limit(1), 'NRO_COM_ANT')
            ->selectSub(DB::table('PAGO')->selectRaw('COUNT(*)')
                ->whereColumn('COD_CIT', 'c.COD_CIT')->where('TIP_PAG', 'Adelanto')
                ->where('EST_PAG', 'Confirmado')->whereNull('COD_CAJ'), 'ANTICIPOS')
            ->get();
    }

    /** Reintento manual del comprobante del anticipo (botón del panel del paciente). */
    public function reintentarComprobante(string $codCit, string $codPac): ?string
    {
        if (!DB::table('CITA')->where('COD_CIT', $codCit)->where('COD_PAC', $codPac)->exists()) {
            throw new DomainException('La cita no existe.');
        }
        return $this->comprobantes->emitirAnticipo($codCit);
    }

    /** NRO del comprobante del anticipo de una cita del paciente (para descargar el PDF). */
    public function comprobanteAnticipo(string $codCit, string $codPac): ?string
    {
        if (!DB::table('CITA')->where('COD_CIT', $codCit)->where('COD_PAC', $codPac)->exists()) {
            return null;
        }
        return $this->comprobantes->anticipoEmitido($codCit)?->NRO_COM;
    }

    public function montoAnticipo(float $precio): float
    {
        return round($precio * ((int) config('cies.anticipo_porcentaje')) / 100, 2);
    }

    // ---------- RF008: OTP al correo del paciente ----------

    public function enviarOtp(string $codCit, string $codPac, string $codUsr): array
    {
        $this->citaParaPago($codCit, $codPac);

        $correo = DB::table('PACIENTE')->where('COD_PAC', $codPac)->value('COR_PAC');
        if (!$correo) {
            throw new DomainException('Tu perfil no tiene correo registrado.');
        }

        try {
            $r = $this->otp->generar($codUsr, 'PAGO_CITA', 'CORREO', $correo);
        } catch (DomainException $e) {
            Auditoria::registrar('Fallo envio OTP', 'Media', 'DOM02', 'OTP', $codCit,
                mb_substr($e->getMessage(), 0, 255), codPac: $codPac);
            throw $e;
        }

        $oculto = $this->ocultarCorreo($correo);
        Auditoria::registrar('OTP enviado', 'Baja', 'DOM02', 'OTP', $r['codOtp'],
            "Código de pago de la cita {$codCit} enviado a {$oculto}", codPac: $codPac);

        return ['codOtp' => $r['codOtp'], 'correo' => $oculto, 'segundos' => $r['segundosValidez']];
    }

    // ---------- RF008: confirmar pago con el OTP ----------

    public function confirmarPago(string $codCit, string $codPac, string $codUsr, string $codOtp, string $codigo, string $codMet): object
    {
        $cita = $this->citaParaPago($codCit, $codPac);

        if (!DB::table('METODO_PAGO')->where('COD_MET', $codMet)->exists()) {
            throw new DomainException('Selecciona un método de pago válido.');
        }

        // La validación va fuera de la transacción: no se mantienen bloqueos durante la llamada HTTP
        $res = $this->otp->validar($codOtp, trim($codigo));
        if (!$res['valido']) {
            Auditoria::registrar('OTP rechazado', 'Media', 'DOM02', 'OTP', $codOtp,
                "Pago de la cita {$codCit}: {$res['motivo']}", codPac: $codPac);
            throw new DomainException(OtpClient::mensajeMotivo($res));
        }

        $pago = DB::transaction(function () use ($cita, $codCit, $codPac, $codUsr, $codOtp, $codMet) {
            $c = DB::table('CITA')->where('COD_CIT', $codCit)->where('COD_PAC', $codPac)
                ->lockForUpdate()->first();
            if (!$c || $c->EST_CIT !== 'Pendiente de pago') {
                throw new DomainException('La cita ya no está pendiente de pago.');
            }
            if (Carbon::parse($c->FECEXP_CIT)->lt(Carbon::now())) {
                throw new DomainException('El tiempo para pagar venció justo ahora y el horario fue liberado.');
            }

            // Defensa en profundidad: el OTP debe estar Verificado, ser de este propósito/usuario y no haberse usado
            $otp = DB::table('OTP')->where('COD_OTP', $codOtp)->first();
            if (!$otp || $otp->EST_OTP !== 'Verificado' || $otp->PRO_OTP !== 'PAGO_CITA'
                || $otp->COD_USR !== $codUsr
                || DB::table('PAGO')->where('COD_OTP', $codOtp)->exists()) {
                throw new DomainException('El código de verificación no es válido para este pago.');
            }

            $codPag = sprintf('PAG%07d', (int) DB::table('PAGO')->max('id') + 1);
            DB::table('PAGO')->insert([
                'COD_PAG' => $codPag,
                'FEC_PAG' => Carbon::now()->toDateTimeString(),
                'MON_PAG' => $cita->anticipo,
                'TIP_PAG' => 'Adelanto',
                'EST_PAG' => 'Confirmado',
                'COD_CIT' => $codCit,
                'COD_MET' => $codMet,
                'COD_OTP' => $codOtp,
                'COD_CAJ' => null,
            ]);

            DB::table('CITA')->where('COD_CIT', $codCit)->update([
                'EST_CIT' => 'Confirmada', 'FECEXP_CIT' => null,
            ]);

            $fecha = Carbon::parse($cita->FEC_CIT)->format('d/m/Y');
            $hora  = substr($cita->HOR_CIT, 0, 5);
            DB::table('NOTIFICACION')->insert([
                'COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1),
                'FEC_NOT' => Carbon::now()->toDateTimeString(),
                'TIP_NOT' => 'Confirmación de cita',
                'MSG_NOT' => "Tu cita del {$fecha} a las {$hora} con {$cita->TIT_EMP} {$cita->APE_EMP} fue confirmada. Anticipo pagado: Bs {$cita->anticipo}.",
                'CAN_NOT' => 'Sistema',
                'EST_NOT' => 'Enviada',
                'REF_NOT' => $codCit,
                'COD_USR' => $codUsr,
            ]);

            Auditoria::registrar('Confirmar pago', 'Media', 'DOM04', 'PAGO', $codPag,
                "Anticipo Bs {$cita->anticipo} de la cita {$codCit} confirmado con OTP {$codOtp}", codPac: $codPac);

            return (object) ['COD_PAG' => $codPag, 'monto' => $cita->anticipo, 'COD_CIT' => $codCit];
        });

        // Ya hubo commit: el pago está confirmado pase lo que pase con Quarkus.
        // Si falla, emitirAnticipo() lo audita (DOM04) y devuelve null -> botón "Reintentar comprobante".
        try {
            $pago->NRO_COM = $this->comprobantes->emitirAnticipo($codCit);
        } catch (DomainException $e) {
            $pago->NRO_COM = null;
        }

        return $pago;
    }

    // ---------- RNF007: liberación automática ----------

    public function liberarVencidas(): int
    {
        $n = DB::table('CITA')
            ->where('EST_CIT', 'Pendiente de pago')
            ->where('FECEXP_CIT', '<', Carbon::now()->toDateTimeString())
            ->update(['EST_CIT' => 'Expirada']);

        if ($n > 0) {
            Log::channel('cies')->info("Citas sin pago liberadas: {$n}");
        }
        return $n;
    }

    private function ocultarCorreo(string $correo): string
    {
        [$u, $d] = explode('@', $correo, 2) + [1 => ''];
        return mb_substr($u, 0, 2) . str_repeat('*', max(1, mb_strlen($u) - 2)) . '@' . $d;
    }
}