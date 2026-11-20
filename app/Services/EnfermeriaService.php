<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enfermería e internación:
 *  RF030 / RN40 signos vitales y resumen clínico (alergias, antecedentes, indicaciones activas)
 *  RF033 / RN37 administración de dosis validando los 5 correctos (paciente, medicamento, dosis, vía, hora)
 *  RF034 / RN38 dosis pendientes, omisión con incidencia y aviso al médico tratante
 *  RF035 notas de enfermería, balance hídrico y resumen para el cambio de turno
 */
class EnfermeriaService
{
    public const CONCEPTOS_INGRESO = ['Oral', 'Intravenoso', 'Sonda', 'Otro ingreso'];
    public const CONCEPTOS_EGRESO  = ['Orina', 'Drenaje', 'Vómito', 'Deposición', 'Sangrado', 'Otro egreso'];
    public const MAX_DOSIS_PROGRAMADAS = 60;

    public static function turno(?Carbon $t = null): string
    {
        $h = ($t ?? now())->hour;
        return $h >= 7 && $h < 13 ? 'Mañana' : ($h >= 13 && $h < 19 ? 'Tarde' : 'Noche');
    }

    /** Inicio del turno en curso (07:00, 13:00 o 19:00; la noche empieza el día anterior antes de las 07:00). */
    public static function inicioTurno(?Carbon $t = null): Carbon
    {
        $t = ($t ?? now())->copy();
        return match (self::turno($t)) {
            'Mañana' => $t->setTime(7, 0),
            'Tarde'  => $t->setTime(13, 0),
            default  => $t->hour < 7 ? $t->subDay()->setTime(19, 0) : $t->setTime(19, 0),
        };
    }

    public function internados()
    {
        $ventana = (int) config('cies.ventana_dosis_min', 30);
        return DB::table('INTERNACION as i')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')
            ->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->where('i.EST_INT', 'Internado')
            ->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')
            ->get(['i.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'c.NRO_CAM', 's.NOM_SAL', 'e.TIT_EMP', 'e.APE_EMP'])
            ->each(function ($i) use ($ventana) {
                $i->PENDIENTES = DB::table('DOSIS')->where('COD_INT', $i->COD_INT)->where('EST_DOS', 'Programada')
                    ->where('FECPRG_DOS', '<=', now()->addHour())->count();
                $i->ATRASADAS = DB::table('DOSIS')->where('COD_INT', $i->COD_INT)->where('EST_DOS', 'Programada')
                    ->where('FECPRG_DOS', '<', now()->subMinutes($ventana))->count();
                $i->SIGNO = DB::table('SIGNO_VITAL')->where('COD_PAC', $i->COD_PAC)->orderByDesc('FEC_SIV')->first();
                $i->ALERGIAS = DB::table('ALERGIA as a')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'a.COD_HIS')
                    ->where('h.COD_PAC', $i->COD_PAC)->pluck('a.DES_ALE')->all();
            });
    }

    /** RF034: dosis programadas en la próxima hora o ya vencidas, de todos los internados. */
    public function alertasDosis()
    {
        $ventana = (int) config('cies.ventana_dosis_min', 30);
        return DB::table('DOSIS as d')
            ->join('INTERNACION as i', 'i.COD_INT', '=', 'd.COD_INT')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')
            ->join('DETALLE_RECETA as dr', 'dr.COD_DRE', '=', 'd.COD_DRE')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'dr.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'dr.COD_VIA')
            ->where('i.EST_INT', 'Internado')->where('d.EST_DOS', 'Programada')
            ->where('d.FECPRG_DOS', '<=', now()->addHour())
            ->orderBy('d.FECPRG_DOS')
            ->get(['d.COD_DOS', 'd.FECPRG_DOS', 'd.DOS_DOS', 'd.COD_INT', 'p.NOM_PAC', 'p.APE_PAC', 'c.NRO_CAM', 'm.DES_MED', 'v.DES_VIA'])
            ->each(fn ($d) => $d->ATRASADA = Carbon::parse($d->FECPRG_DOS)->lt(now()->subMinutes($ventana)));
    }

    public function internacion(string $codInt): object
    {
        $i = DB::table('INTERNACION as i')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')
            ->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->where('i.COD_INT', $codInt)
            ->first(['i.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.FECNAC_PAC', 'p.SEX_PAC',
                     'c.NRO_CAM', 's.NOM_SAL', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);
        if (!$i) {
            throw new DomainException('La internación no existe.');
        }
        return $i;
    }

    /** RF030: resumen clínico para enfermería. */
    public function resumen(object $int): array
    {
        $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $int->COD_PAC)->value('COD_HIS') ?? '__';
        return [
            'alergias'     => DB::table('ALERGIA')->where('COD_HIS', $his)->get(),
            'antecedentes' => DB::table('ANTECEDENTE')->where('COD_HIS', $his)->get(),
            'indicaciones' => $this->indicacionesActivas($int->COD_PAC),
            'signos'       => DB::table('SIGNO_VITAL as s')->join('EMPLEADO as e', 'e.COD_EMP', '=', 's.COD_EMP')
                                ->where('s.COD_PAC', $int->COD_PAC)->orderByDesc('s.FEC_SIV')->limit(12)
                                ->get(['s.*', 'e.NOM_EMP', 'e.APE_EMP']),
            'dosis'        => DB::table('DOSIS as d')
                                ->join('DETALLE_RECETA as dr', 'dr.COD_DRE', '=', 'd.COD_DRE')
                                ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'dr.COD_MED')
                                ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'dr.COD_VIA')
                                ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'd.COD_EMP')
                                ->leftJoin('INCIDENCIA_DOSIS as inc', 'inc.COD_DOS', '=', 'd.COD_DOS')
                                ->where('d.COD_INT', $int->COD_INT)
                                ->where(fn ($w) => $w->where('d.EST_DOS', '!=', 'Programada')
                                    ->orWhere('d.FECPRG_DOS', '<=', now()->addHours(24)))
                                ->orderBy('d.FECPRG_DOS')->limit(80)
                                ->get(['d.*', 'm.DES_MED', 'm.COD_MED', 'v.DES_VIA', 'v.COD_VIA', 'e.NOM_EMP', 'e.APE_EMP', 'inc.MOT_INC']),
            'notas'        => DB::table('NOTA_ENFERMERIA as n')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'n.COD_EMP')
                                ->where('n.COD_INT', $int->COD_INT)->orderByDesc('n.FEC_NEN')->limit(30)
                                ->get(['n.*', 'e.NOM_EMP', 'e.APE_EMP']),
            'balance'      => $this->balance($int->COD_INT),
        ];
    }

    /** Ítems de recetas activas del paciente (indicaciones médicas vigentes). */
    public function indicacionesActivas(string $codPac)
    {
        return DB::table('DETALLE_RECETA as d')
            ->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')
            ->join('CONSULTA as co', 'co.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'co.COD_HIS')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')
            ->where('h.COD_PAC', $codPac)->where('r.EST_REC', 'Activa')
            ->orderByDesc('r.FEC_REC')
            ->get(['d.*', 'r.FEC_REC', 'm.DES_MED', 'v.DES_VIA']);
    }

    // ---------- RF030 / RN40 ----------

    public function registrarSignos(string $codPac, array $d, object $actor): string
    {
        $sis = (int) $d['sistolica']; $dia = (int) $d['diastolica']; $pul = (int) $d['pulso']; $tem = (float) $d['temperatura'];
        if ($sis <= $dia || $dia <= 0) {
            throw new DomainException('La presión sistólica debe ser mayor que la diastólica (RN40).');
        }
        if ($pul <= 0) {
            throw new DomainException('El pulso debe ser mayor que cero (RN40).');
        }
        if ($tem < 30.0 || $tem > 45.0) {
            throw new DomainException('La temperatura debe estar entre 30,0 y 45,0 °C (RN40).');
        }
        $cod = sprintf('SIV%07d', (int) DB::table('SIGNO_VITAL')->max('id') + 1);
        DB::table('SIGNO_VITAL')->insert([
            'COD_SIV' => $cod, 'FEC_SIV' => now(), 'SIS_SIV' => $sis, 'DIA_SIV' => $dia,
            'PUL_SIV' => $pul, 'TEM_SIV' => round($tem, 1), 'COD_PAC' => $codPac, 'COD_EMP' => $actor->COD_EMP,
        ]);
        $alerta = $sis >= 180 || $dia >= 110 || $sis < 90 || $pul > 120 || $pul < 50 || $tem >= 38.5 || $tem < 35;
        Auditoria::registrar('Signos vitales', $alerta ? 'Media' : 'Baja', 'DOM03', 'SIGNO_VITAL', $cod,
            "PA {$sis}/{$dia}, pulso {$pul}, T° {$tem}" . ($alerta ? ' (fuera de rango)' : ''), $actor, $codPac);
        return $cod;
    }

    // ---------- Programación de dosis (a partir de la indicación médica) ----------

    public function programarDosis(string $codInt, string $codDre, Carbon $inicio, object $actor): int
    {
        return DB::transaction(function () use ($codInt, $codDre, $inicio, $actor) {
            $int = DB::table('INTERNACION')->where('COD_INT', $codInt)->lockForUpdate()->first();
            if (!$int || $int->EST_INT !== 'Internado') {
                throw new DomainException('Solo se programan dosis de pacientes internados.');
            }
            $item = $this->indicacionesActivas($int->COD_PAC)->firstWhere('COD_DRE', $codDre);
            if (!$item) {
                throw new DomainException('La indicación no pertenece a una receta activa del paciente.');
            }
            if (DB::table('DOSIS')->where('COD_INT', $codInt)->where('COD_DRE', $codDre)->where('EST_DOS', 'Programada')->exists()) {
                throw new DomainException("{$item->DES_MED} ya tiene dosis programadas en esta internación.");
            }
            // AJ04: si el medicamento lo trajo la familia y está verificado, la dosis se vincula a ese insumo
            $codIns = DB::table('INSUMO_TERCERO')->where('COD_PAC', $int->COD_PAC)->where('COD_MED', $item->COD_MED)
                ->where('EST_INS', 'Verificado')->orderByDesc('FEC_INS')->value('COD_INS');

            $total = min(intdiv(24 * (int) $item->DUR_DRE, (int) $item->FRE_DRE), self::MAX_DOSIS_PROGRAMADAS);
            $n = (int) DB::table('DOSIS')->lockForUpdate()->max('id');
            for ($k = 0; $k < $total; $k++) {
                $n++;
                DB::table('DOSIS')->insert([
                    'COD_DOS' => sprintf('DOS%07d', $n), 'FECPRG_DOS' => $inicio->copy()->addHours($k * (int) $item->FRE_DRE),
                    'FECADM_DOS' => null, 'DOS_DOS' => $item->DOS_DRE, 'EST_DOS' => 'Programada',
                    'COD_INT' => $codInt, 'COD_DRE' => $codDre, 'COD_INS' => $codIns, 'COD_EMP' => null,
                ]);
            }
            Auditoria::registrar('Programar dosis', 'Media', 'DOM03', 'DOSIS', $codDre,
                "{$total} dosis de {$item->DES_MED} {$item->DOS_DRE} cada {$item->FRE_DRE} h desde {$inicio->format('d/m H:i')}",
                $actor, $int->COD_PAC);
            return $total;
        });
    }

    // ---------- RF033 / RN37: 5 correctos ----------

    /**
     * $lectura: lo que la enfermera escanea o escribe al lado de la cama.
     *   paciente    → código de la pulsera (COD_PAC) o CI
     *   medicamento → código del medicamento (COD_MED), del lote o del insumo
     *   dosis, via  → lo que va a administrar
     * Devuelve la lista de verificaciones; si alguna falla, no se registra la administración.
     */
    public function administrar(string $codDos, array $lectura, object $actor): array
    {
        return DB::transaction(function () use ($codDos, $lectura, $actor) {
            $d = DB::table('DOSIS as d')
                ->join('INTERNACION as i', 'i.COD_INT', '=', 'd.COD_INT')
                ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
                ->join('DETALLE_RECETA as dr', 'dr.COD_DRE', '=', 'd.COD_DRE')
                ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'dr.COD_MED')
                ->where('d.COD_DOS', $codDos)->lockForUpdate()
                ->first(['d.*', 'i.COD_PAC', 'i.EST_INT', 'p.CI_PAC', 'dr.COD_MED', 'dr.COD_VIA', 'm.DES_MED']);
            if (!$d) {
                throw new DomainException('La dosis no existe.');
            }
            if ($d->EST_DOS !== 'Programada') {
                throw new DomainException("La dosis ya está «{$d->EST_DOS}» (RN38).");
            }

            $norm = fn ($t) => preg_replace('/\s+/', ' ', mb_strtolower(trim(Str::ascii((string) $t))));
            $pac = strtoupper(trim($lectura['paciente'] ?? ''));
            $med = strtoupper(trim($lectura['medicamento'] ?? ''));
            $lotes = DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $d->COD_MED)->pluck('NRO_LOT')
                ->merge(DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $d->COD_MED)->pluck('COD_LOT'))
                ->merge($d->COD_INS ? DB::table('INSUMO_TERCERO')->where('COD_INS', $d->COD_INS)->pluck('LOT_INS') : [])
                ->merge($d->COD_INS ? [$d->COD_INS] : [])
                ->map(fn ($x) => strtoupper($x))->all();
            $ventana = (int) config('cies.ventana_dosis_min', 30);
            $minutos = now()->diffInMinutes(Carbon::parse($d->FECPRG_DOS), false);

            $checks = [
                'Paciente correcto'    => $pac !== '' && ($pac === strtoupper($d->COD_PAC) || $pac === strtoupper($d->CI_PAC)),
                'Medicamento correcto' => $med !== '' && ($med === strtoupper($d->COD_MED) || in_array($med, $lotes, true)),
                'Dosis correcta'       => $norm($lectura['dosis'] ?? '') === $norm($d->DOS_DOS),
                'Vía correcta'         => ($lectura['via'] ?? '') === $d->COD_VIA,
                'Hora correcta'        => abs($minutos) <= $ventana,
            ];
            $fallas = array_keys(array_filter($checks, fn ($ok) => !$ok));

            if ($fallas) {
                Auditoria::registrar('5 correctos', 'Alta', 'DOM03', 'DOSIS', $codDos,
                    "Administración bloqueada de {$d->DES_MED}: falla " . implode(', ', $fallas), $actor, $d->COD_PAC);
                return ['ok' => false, 'checks' => $checks, 'fallas' => $fallas];
            }

            DB::table('DOSIS')->where('COD_DOS', $codDos)->update([
                'EST_DOS' => 'Administrada', 'FECADM_DOS' => now(), 'COD_EMP' => $actor->COD_EMP,
            ]);
            Auditoria::registrar('Administrar dosis', 'Media', 'DOM03', 'DOSIS', $codDos,
                "{$d->DES_MED} {$d->DOS_DOS} administrada con los 5 correctos verificados", $actor, $d->COD_PAC);
            return ['ok' => true, 'checks' => $checks, 'fallas' => []];
        });
    }

    // ---------- RF034 / RN38: omisión con incidencia ----------

    public function omitir(string $codDos, string $motivo, object $actor): void
    {
        DB::transaction(function () use ($codDos, $motivo, $actor) {
            $d = DB::table('DOSIS as d')
                ->join('INTERNACION as i', 'i.COD_INT', '=', 'd.COD_INT')
                ->join('DETALLE_RECETA as dr', 'dr.COD_DRE', '=', 'd.COD_DRE')
                ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'dr.COD_MED')
                ->where('d.COD_DOS', $codDos)->lockForUpdate()
                ->first(['d.*', 'i.COD_PAC', 'i.COD_EMP as MEDICO', 'm.DES_MED']);
            if (!$d) {
                throw new DomainException('La dosis no existe.');
            }
            if ($d->EST_DOS !== 'Programada') {
                throw new DomainException("La dosis ya está «{$d->EST_DOS}» (RN38).");
            }
            DB::table('DOSIS')->where('COD_DOS', $codDos)->update(['EST_DOS' => 'Omitida', 'COD_EMP' => $actor->COD_EMP]);
            $codInc = sprintf('INC%07d', (int) DB::table('INCIDENCIA_DOSIS')->lockForUpdate()->max('id') + 1);
            DB::table('INCIDENCIA_DOSIS')->insert([
                'COD_INC' => $codInc, 'FEC_INC' => now(), 'MOT_INC' => $motivo, 'FECNOT_INC' => now(),
                'COD_DOS' => $codDos, 'COD_EMP' => $actor->COD_EMP,
            ]);
            $hora = Carbon::parse($d->FECPRG_DOS)->format('d/m H:i');
            Notificador::empleado($d->MEDICO, 'Dosis omitida',
                "Dosis de {$d->DES_MED} {$d->DOS_DOS} de las {$hora} omitida ({$d->COD_INT}): {$motivo}", $codInc);
            Auditoria::registrar('Omitir dosis', 'Alta', 'DOM03', 'INCIDENCIA_DOSIS', $codInc,
                "{$d->DES_MED} de las {$hora} omitida: {$motivo}. Se notificó al médico tratante", $actor, $d->COD_PAC);
        });
    }

    // ---------- RF035: notas, balance y cambio de turno ----------

    public function nota(string $codInt, string $tipo, string $texto, object $actor): string
    {
        $int = $this->internacion($codInt);
        if ($int->EST_INT !== 'Internado') {
            throw new DomainException('El paciente ya fue dado de alta.');
        }
        $cod = sprintf('NEN%07d', (int) DB::table('NOTA_ENFERMERIA')->max('id') + 1);
        DB::table('NOTA_ENFERMERIA')->insert([
            'COD_NEN' => $cod, 'FEC_NEN' => now(), 'TIP_NEN' => $tipo, 'TUR_NEN' => self::turno(),
            'TXT_NEN' => $texto, 'COD_INT' => $codInt, 'COD_EMP' => $actor->COD_EMP,
        ]);
        Auditoria::registrar($tipo === 'Nota' ? 'Nota enfermería' : 'Cambio de turno', 'Baja', 'DOM03', 'NOTA_ENFERMERIA', $cod,
            mb_substr($texto, 0, 200), $actor, $int->COD_PAC);
        return $cod;
    }

    public function registrarBalance(string $codInt, string $tipo, string $concepto, int $volumen, object $actor): string
    {
        $int = $this->internacion($codInt);
        if ($int->EST_INT !== 'Internado') {
            throw new DomainException('El paciente ya fue dado de alta.');
        }
        $validos = $tipo === 'Ingreso' ? self::CONCEPTOS_INGRESO : self::CONCEPTOS_EGRESO;
        if (!in_array($concepto, $validos, true)) {
            throw new DomainException("«{$concepto}» no es un concepto de {$tipo}.");
        }
        $cod = sprintf('BAL%07d', (int) DB::table('BALANCE_HIDRICO')->max('id') + 1);
        DB::table('BALANCE_HIDRICO')->insert([
            'COD_BAL' => $cod, 'FEC_BAL' => now(), 'TIP_BAL' => $tipo, 'CON_BAL' => $concepto,
            'VOL_BAL' => $volumen, 'COD_INT' => $codInt, 'COD_EMP' => $actor->COD_EMP,
        ]);
        return $cod;
    }

    /** Balance hídrico de las últimas 24 h y del turno en curso. */
    public function balance(string $codInt): array
    {
        $filas = DB::table('BALANCE_HIDRICO')->where('COD_INT', $codInt)
            ->where('FEC_BAL', '>=', now()->subDay())->orderByDesc('FEC_BAL')->get();
        $ini = self::inicioTurno();
        $suma = fn ($c, $t) => (int) $c->where('TIP_BAL', $t)->sum('VOL_BAL');
        $turno = $filas->filter(fn ($f) => Carbon::parse($f->FEC_BAL)->gte($ini));
        return [
            'filas'          => $filas,
            'ingresos'       => $suma($filas, 'Ingreso'),
            'egresos'        => $suma($filas, 'Egreso'),
            'saldo'          => $suma($filas, 'Ingreso') - $suma($filas, 'Egreso'),
            'turno_ingresos' => $suma($turno, 'Ingreso'),
            'turno_egresos'  => $suma($turno, 'Egreso'),
        ];
    }

    /** Borrador del resumen para el cambio de turno con lo registrado desde el inicio del turno. */
    public function borradorTurno(string $codInt): string
    {
        $int = $this->internacion($codInt);
        $ini = self::inicioTurno();
        $sig = DB::table('SIGNO_VITAL')->where('COD_PAC', $int->COD_PAC)->orderByDesc('FEC_SIV')->first();
        $dos = DB::table('DOSIS')->where('COD_INT', $codInt)->where(function ($w) use ($ini) {
            $w->where('FECADM_DOS', '>=', $ini)->orWhere(fn ($q) => $q->where('EST_DOS', 'Omitida')->where('FECPRG_DOS', '>=', $ini));
        })->get();
        $pend = DB::table('DOSIS as d')->join('DETALLE_RECETA as dr', 'dr.COD_DRE', '=', 'd.COD_DRE')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'dr.COD_MED')
            ->where('d.COD_INT', $codInt)->where('d.EST_DOS', 'Programada')
            ->where('d.FECPRG_DOS', '<=', now()->addHours(6))->orderBy('d.FECPRG_DOS')->limit(5)
            ->get(['d.FECPRG_DOS', 'm.DES_MED', 'd.DOS_DOS']);
        $bal = $this->balance($codInt);

        $l = ['Turno ' . self::turno() . ' · ' . $int->NOM_PAC . ' ' . $int->APE_PAC . ' · cama ' . $int->NRO_CAM . '.'];
        $l[] = $sig ? "Últimos signos ({$this->hora($sig->FEC_SIV)}): PA {$sig->SIS_SIV}/{$sig->DIA_SIV}, pulso {$sig->PUL_SIV}, T° {$sig->TEM_SIV} °C."
                    : 'Sin signos vitales registrados.';
        $l[] = 'Dosis del turno: ' . $dos->where('EST_DOS', 'Administrada')->count() . ' administradas, '
             . $dos->where('EST_DOS', 'Omitida')->count() . ' omitidas.';
        $l[] = "Balance del turno: ingresos {$bal['turno_ingresos']} mL, egresos {$bal['turno_egresos']} mL; 24 h: saldo {$bal['saldo']} mL.";
        if ($pend->isNotEmpty()) {
            $l[] = 'Próximas dosis: ' . $pend->map(fn ($p) => $this->hora($p->FECPRG_DOS) . ' ' . $p->DES_MED . ' ' . $p->DOS_DOS)->implode('; ') . '.';
        }
        if ($int->AIS_INT ?? null) {
            $l[] = "Aislamiento: {$int->AIS_INT}.";
        }
        $l[] = 'Pendientes / observaciones: ';
        return implode("\n", $l);
    }

    private function hora(string $f): string
    {
        return Carbon::parse($f)->format('H:i');
    }
}
