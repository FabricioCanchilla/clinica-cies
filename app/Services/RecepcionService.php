<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Recepción (RF036, RF037, RF038).
 * - RF036 / RN07: búsqueda por CI antes de crear una ficha; alta presencial de ficha y cuenta.
 * - RF037 / RN23: disponibilidad de médicos, consultorios y camas; check-in de llegada.
 *   Sin AJ01 (estados «En espera» y «En consulta») el check-in se registra en AUDITORIA.
 * - RF038 / RN21: validación de seguros y cancelación o reprogramación justificada por la clínica.
 */
class RecepcionService
{
    public const MINUTOS_TOLERANCIA = 15;

    public function __construct(private CitaService $citas)
    {
    }

    // ---------- RF036 ----------

    public function buscarPacientes(string $q)
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }

        return DB::table('PACIENTE as p')
            ->leftJoin('USUARIO as u', 'u.COD_PAC', '=', 'p.COD_PAC')
            ->where(function ($w) use ($q) {
                $w->where('p.CI_PAC', $q)
                  ->orWhere('p.COD_PAC', $q)
                  ->orWhere(DB::raw("CONCAT(p.NOM_PAC, ' ', p.APE_PAC)"), 'like', '%' . $q . '%');
            })
            ->orderBy('p.COD_PAC')
            ->limit(30)
            ->get(['p.*', 'u.USR_USR', 'u.EST_USR']);
    }

    /** Alta presencial: ficha + cuenta con clave temporal que se cambia en el primer ingreso (RN03). */
    public function registrarPaciente(array $d): array
    {
        if (DB::table('PACIENTE')->where('CI_PAC', $d['ci'])->exists()
            || DB::table('USUARIO')->where('USR_USR', $d['ci'])->exists()) {
            throw new DomainException('Ya existe una ficha con ese CI (RN07). Búscala en lugar de crear otra.');
        }

        $clave = 'Cies' . $d['ci'];

        return DB::transaction(function () use ($d, $clave) {
            $codPac = sprintf('PAC%07d', (int) DB::table('PACIENTE')->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(COD_PAC, 4) AS UNSIGNED)')) + 1);
            $codUsr = sprintf('USR%05d', (int) DB::table('USUARIO')->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(COD_USR, 4) AS UNSIGNED)')) + 1);

            DB::table('PACIENTE')->insert([
                'COD_PAC'    => $codPac,
                'CI_PAC'     => $d['ci'],
                'NOM_PAC'    => trim($d['nombres']),
                'APE_PAC'    => trim($d['apellidos']),
                'SEX_PAC'    => $d['sexo'],
                'FECNAC_PAC' => $d['fecnac'],
                'ESTCIV_PAC' => $d['estciv'],
                'TEL_PAC'    => $d['telefono'],
                'COR_PAC'    => mb_strtolower(trim($d['correo'])),
                'DIR_PAC'    => trim($d['direccion']),
            ]);

            DB::table('USUARIO')->insert([
                'COD_USR'    => $codUsr,
                'USR_USR'    => $d['ci'],
                'CON_USR'    => Hash::make($clave),
                'EST_USR'    => 'Activo',
                'INTFAL_USR' => 0,
                'CAMCON_USR' => 1,
                'COD_ROL'    => 'ROL006',
                'COD_EMP'    => null,
                'COD_PAC'    => $codPac,
            ]);

            Auditoria::registrar('Registro paciente', 'Baja', 'DOM01', 'PACIENTE', $codPac,
                "Alta presencial del paciente {$codPac} (CI {$d['ci']}) en recepción, con aceptación de privacidad y términos",
                codPac: $codPac);

            return ['codPac' => $codPac, 'usuario' => $d['ci'], 'clave' => $clave];
        });
    }

    // ---------- RF037 ----------

    public function citasDelDia(Carbon $dia)
    {
        $checkins = DB::table('AUDITORIA')
            ->where('ENT_AUD', 'CITA')->where('ACC_AUD', 'Check-in')
            ->whereDate('FEC_AUD', $dia->toDateString())
            ->pluck('FEC_AUD', 'REG_AUD');

        return DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')
            ->where('c.FEC_CIT', $dia->toDateString())
            ->whereNotIn('c.EST_CIT', ['Expirada'])
            ->orderBy('c.HOR_CIT')
            ->get(['c.COD_CIT', 'c.HOR_CIT', 'c.EST_CIT', 'c.MOT_CIT', 'c.COD_PAC', 'c.COD_EMP',
                   'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC',
                   'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV', 'k.NOM_CON'])
            ->map(function ($c) use ($checkins, $dia) {
                $c->CHECKIN = $checkins[$c->COD_CIT] ?? null;
                $limite = Carbon::parse($dia->toDateString() . ' ' . $c->HOR_CIT)->addMinutes(self::MINUTOS_TOLERANCIA);
                $c->FUERA_TOLERANCIA = !$c->CHECKIN && Carbon::now()->gt($limite);
                return $c;
            });
    }

    /** Médicos con horario hoy, su próxima hora libre y cuántas citas tienen. */
    public function disponibilidadMedicos(Carbon $dia)
    {
        $medicos = DB::table('EMPLEADO as e')
            ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->whereNotNull('e.COD_ESP')
            ->orderBy('e.APE_EMP')
            ->get(['e.COD_EMP', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'es.DES_ESP']);

        return $medicos->map(function ($m) use ($dia) {
            $libres = $this->citas->horariosLibres($m->COD_EMP, $dia);
            $m->LIBRES = count($libres);
            $m->PROXIMA = $libres[0]['hora'] ?? null;
            $m->TIENE_HORARIO = DB::table('HORARIO_MEDICO')->where('COD_EMP', $m->COD_EMP)->exists();
            return $m;
        });
    }

    /** Consultorios: ocupado si hay una cita Confirmada o Atendida en curso ahora mismo. */
    public function disponibilidadConsultorios()
    {
        $ahora = Carbon::now();
        $dur = (int) config('cies.duracion_cita_min');

        return DB::table('CONSULTORIO')->orderBy('COD_CON')->get()->map(function ($k) use ($ahora, $dur) {
            $enCurso = DB::table('CITA')
                ->where('COD_CON', $k->COD_CON)
                ->where('FEC_CIT', $ahora->toDateString())
                ->whereIn('EST_CIT', ['Confirmada', 'Atendida'])
                ->where('HOR_CIT', '<=', $ahora->format('H:i:s'))
                ->where('HOR_CIT', '>', $ahora->copy()->subMinutes($dur)->format('H:i:s'))
                ->exists();
            $k->OCUPADO = $enCurso;
            return $k;
        });
    }

    public function resumenCamas()
    {
        return DB::table('CAMA')->select('EST_CAM', DB::raw('COUNT(*) AS total'))
            ->groupBy('EST_CAM')->pluck('total', 'EST_CAM');
    }

    public function checkin(string $codCit): void
    {
        DB::transaction(function () use ($codCit) {
            $c = DB::table('CITA')->where('COD_CIT', $codCit)->lockForUpdate()->first();
            if (!$c) {
                throw new DomainException('La cita no existe.');
            }
            if ($c->FEC_CIT !== Carbon::today()->toDateString()) {
                throw new DomainException('El check-in solo se registra el día de la cita.');
            }
            if ($c->EST_CIT !== 'Confirmada') {
                throw new DomainException("La cita está {$c->EST_CIT}: solo se registra la llegada de citas confirmadas.");
            }
            $ya = DB::table('AUDITORIA')->where('ENT_AUD', 'CITA')->where('REG_AUD', $codCit)
                ->where('ACC_AUD', 'Check-in')->exists();
            if ($ya) {
                throw new DomainException('La llegada de este paciente ya fue registrada.');
            }

            $limite = Carbon::parse($c->FEC_CIT . ' ' . $c->HOR_CIT)->addMinutes(self::MINUTOS_TOLERANCIA);
            if (Carbon::now()->gt($limite)) {
                // RN23: pasada la tolerancia la cita es inasistencia y el anticipo se pierde
                DB::table('CITA')->where('COD_CIT', $codCit)->update(['EST_CIT' => 'No asistio']);
                Auditoria::registrar('Inasistencia', 'Media', 'DOM04', 'CITA', $codCit,
                    'Llegada después de los 15 minutos de tolerancia: la cita pasa a «No asistio» y el anticipo se pierde (RN23)',
                    codPac: $c->COD_PAC);
                throw new DomainException('El paciente llegó después de los 15 minutos de tolerancia: la cita quedó como «No asistio» (RN23).');
            }

            Auditoria::registrar('Check-in', 'Baja', 'DOM04', 'CITA', $codCit,
                'Llegada del paciente registrada en recepción a las ' . Carbon::now()->format('H:i'),
                codPac: $c->COD_PAC);
        });
    }

    /** RN23 manual desde recepción. */
    public function marcarInasistencia(string $codCit): void
    {
        $c = DB::table('CITA')->where('COD_CIT', $codCit)->first();
        if (!$c || $c->EST_CIT !== 'Confirmada') {
            throw new DomainException('Solo una cita confirmada puede marcarse como inasistencia.');
        }
        $limite = Carbon::parse($c->FEC_CIT . ' ' . $c->HOR_CIT)->addMinutes(self::MINUTOS_TOLERANCIA);
        if (Carbon::now()->lte($limite)) {
            throw new DomainException('Aún no pasaron los 15 minutos de tolerancia.');
        }
        DB::table('CITA')->where('COD_CIT', $codCit)->update(['EST_CIT' => 'No asistio']);
        Auditoria::registrar('Inasistencia', 'Media', 'DOM04', 'CITA', $codCit,
            'Sin llegada registrada a los 15 minutos de la hora reservada: «No asistio», el anticipo se pierde (RN23)',
            codPac: $c->COD_PAC);
    }

    /**
     * RN23 automático (tarea programada): citas confirmadas de HOY sin check-in pasada la tolerancia.
     * Solo el día en curso, para no tocar citas antiguas registradas antes de existir el check-in.
     */
    public static function inasistenciasAutomaticas(): int
    {
        $hoy = Carbon::today()->toDateString();
        $limite = Carbon::now()->subMinutes(self::MINUTOS_TOLERANCIA)->format('H:i:s');

        $conCheckin = DB::table('AUDITORIA')->where('ENT_AUD', 'CITA')->where('ACC_AUD', 'Check-in')
            ->whereDate('FEC_AUD', $hoy)->pluck('REG_AUD');

        $n = DB::table('CITA')
            ->where('FEC_CIT', $hoy)
            ->where('EST_CIT', 'Confirmada')
            ->where('HOR_CIT', '<', $limite)
            ->whereNotIn('COD_CIT', $conCheckin)
            ->whereNotExists(fn ($q) => $q->from('CONSULTA')->whereColumn('CONSULTA.COD_CIT', 'CITA.COD_CIT'))
            ->update(['EST_CIT' => 'No asistio']);

        if ($n > 0) {
            Log::channel('cies')->info("RN23: citas marcadas como No asistio: {$n}");
        }
        return $n;
    }

    // ---------- RF038 ----------

    public function segurosDelPaciente(string $codPac)
    {
        return DB::table('SEGURO_PACIENTE as s')
            ->join('ASEGURADORA as a', 'a.COD_ASE', '=', 's.COD_ASE')
            ->where('s.COD_PAC', $codPac)
            ->orderByDesc('s.FECVAL_SEG')
            ->get(['s.*', 'a.DES_ASE']);
    }

    /** Verifica la póliza: si no está vencida queda «Vigente»; una póliza ya vencida no se registra. */
    public function registrarSeguro(string $codPac, string $codAse, string $nroAfil, string $fecVen): void
    {
        $ven = Carbon::parse($fecVen)->startOfDay();
        if ($ven->lt(Carbon::today())) {
            throw new DomainException('La póliza está vencida (' . $ven->format('d/m/Y') . '): no se puede validar.');
        }

        DB::transaction(function () use ($codPac, $codAse, $nroAfil, $ven) {
            $codSeg = sprintf('SEG%07d', (int) DB::table('SEGURO_PACIENTE')->max('id') + 1);
            DB::table('SEGURO_PACIENTE')->insert([
                'COD_SEG'     => $codSeg,
                'NROAFIL_SEG' => $nroAfil,
                'FECVAL_SEG'  => Carbon::today()->toDateString(),
                'FECVEN_SEG'  => $ven->toDateString(),
                'EST_SEG'     => 'Vigente',
                'COD_PAC'     => $codPac,
                'COD_ASE'     => $codAse,
                'COD_EMP'     => auth()->user()->COD_EMP,
            ]);
            Auditoria::registrar('Validar seguro', 'Baja', 'DOM04', 'SEGURO_PACIENTE', $codSeg,
                "Póliza {$nroAfil} validada como Vigente hasta el " . $ven->format('d/m/Y'), codPac: $codPac);
        });
    }

    /** Revalida la vigencia de una póliza registrada. */
    public function revalidarSeguro(string $codSeg): string
    {
        $s = DB::table('SEGURO_PACIENTE')->where('COD_SEG', $codSeg)->first();
        if (!$s) {
            throw new DomainException('El seguro no existe.');
        }
        $vigente = Carbon::parse($s->FECVEN_SEG)->gte(Carbon::today());
        $cambios = ['EST_SEG' => $vigente ? 'Vigente' : 'Vencido', 'COD_EMP' => auth()->user()->COD_EMP];
        if ($vigente) {
            $cambios['FECVAL_SEG'] = Carbon::today()->toDateString();
        }
        DB::table('SEGURO_PACIENTE')->where('COD_SEG', $codSeg)->update($cambios);
        Auditoria::registrar('Validar seguro', 'Baja', 'DOM04', 'SEGURO_PACIENTE', $codSeg,
            "Revalidación de la póliza {$s->NROAFIL_SEG}: {$cambios['EST_SEG']}", codPac: $s->COD_PAC);
        return $cambios['EST_SEG'];
    }

    /** RN21: si cancela la clínica, siempre se devuelve el anticipo. El motivo es obligatorio. */
    public function cancelarPorClinica(string $codCit, string $motivo): string
    {
        return DB::transaction(function () use ($codCit, $motivo) {
            $c = DB::table('CITA')->where('COD_CIT', $codCit)->lockForUpdate()->first();
            if (!$c || !in_array($c->EST_CIT, ['Confirmada', 'Pendiente de pago'], true)) {
                throw new DomainException('Solo se cancelan citas confirmadas o pendientes de pago (RN25).');
            }
            $anticipo = (float) DB::table('PAGO')->where('COD_CIT', $codCit)
                ->where('TIP_PAG', 'Adelanto')->where('EST_PAG', 'Confirmado')->sum('MON_PAG');

            DB::table('CITA')->where('COD_CIT', $codCit)->update(['EST_CIT' => 'Cancelada', 'FECEXP_CIT' => null]);

            $dev = $anticipo > 0 ? ' Devolución del 100 % del anticipo (Bs ' . number_format($anticipo, 2) . ').' : '';
            Auditoria::registrar('Cancelar cita', 'Media', 'DOM04', 'CITA', $codCit,
                mb_substr("Cancelada por la clínica: {$motivo}.{$dev}", 0, 255), codPac: $c->COD_PAC);
            $this->notificar($c->COD_PAC, 'Cita cancelada',
                "La clínica canceló tu cita {$codCit}: {$motivo}.{$dev}", $codCit);

            return 'Cita cancelada por la clínica.' . $dev;
        });
    }

    /** RN21: reprogramación sin costo cuando la decide la clínica (no aplica el límite de 24 h ni de 2 veces). */
    public function reprogramarPorClinica(string $codCit, string $fecha, string $hora, string $motivo): object
    {
        $dia = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        if ($dia->lt(Carbon::today())) {
            throw new DomainException('La nueva fecha no puede ser anterior a hoy.');
        }

        return DB::transaction(function () use ($codCit, $dia, $fecha, $hora, $motivo) {
            $c = DB::table('CITA')->where('COD_CIT', $codCit)->lockForUpdate()->first();
            if (!$c || $c->EST_CIT !== 'Confirmada') {
                throw new DomainException('Solo se reprograman citas confirmadas.');
            }
            DB::table('EMPLEADO')->where('COD_EMP', $c->COD_EMP)->lockForUpdate()->first();
            $slot = collect($this->citas->horariosLibres($c->COD_EMP, $dia))->firstWhere('hora', $hora);
            if (!$slot) {
                throw new DomainException('Ese horario ya no está disponible.');
            }
            $anterior = Carbon::parse($c->FEC_CIT)->format('d/m/Y') . ' ' . substr($c->HOR_CIT, 0, 5);
            DB::table('CITA')->where('COD_CIT', $codCit)->update([
                'FEC_CIT' => $fecha, 'HOR_CIT' => $hora . ':00', 'COD_CON' => $slot['cod_con'],
            ]);
            $nueva = $dia->format('d/m/Y') . ' ' . $hora;
            // Acción distinta a «Reprogramar cita» para no consumir los 2 cambios del paciente (RN22)
            Auditoria::registrar('Reprog. por clínica', 'Media', 'DOM04', 'CITA', $codCit,
                mb_substr("De {$anterior} a {$nueva}, sin costo: {$motivo}", 0, 255), codPac: $c->COD_PAC);
            $this->notificar($c->COD_PAC, 'Cita reprogramada',
                "La clínica movió tu cita {$codCit} al {$nueva}: {$motivo}", $codCit);

            return DB::table('CITA')->where('COD_CIT', $codCit)->first();
        });
    }

    private function notificar(string $codPac, string $tipo, string $msg, string $ref): void
    {
        $codUsr = DB::table('USUARIO')->where('COD_PAC', $codPac)->value('COD_USR');
        if (!$codUsr) {
            return;
        }
        DB::table('NOTIFICACION')->insert([
            'COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1),
            'FEC_NOT' => now(),
            'TIP_NOT' => $tipo,
            'MSG_NOT' => mb_substr($msg, 0, 255),
            'CAN_NOT' => 'Sistema',
            'EST_NOT' => 'Enviada',
            'REF_NOT' => $ref,
            'COD_USR' => $codUsr,
        ]);
    }
}
