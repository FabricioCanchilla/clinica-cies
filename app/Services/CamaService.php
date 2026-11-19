<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Camas de internación (RF035, RN39) con control de limpieza y bioseguridad (AJ07, AJ08).
 *
 * Reglas:
 *  - Solo se interna en una cama «Disponible» y sin limpieza pendiente.
 *  - Al dar de alta, la cama pasa a «Limpieza»: no se puede volver a usar hasta completar la limpieza.
 *  - Si el paciente tenía aislamiento (p. ej. COVID-19), la limpieza es TERMINAL: además de ropa de cama,
 *    superficies y colchón, exige ventilar el ambiente y esperar el tiempo mínimo de acción del desinfectante.
 *  - Ropa de cama sucia en una cama libre la saca de servicio; en una cama ocupada genera una tarea de cambio.
 *  - Un derrame de fluidos o el contacto con un paciente infeccioso exigen limpieza terminal.
 */
class CamaService
{
    public const AISLAMIENTOS = [
        'Respiratorio (COVID-19)',
        'Respiratorio (tuberculosis)',
        'Respiratorio (influenza)',
        'Por gotas (sarampión, varicela)',
        'De contacto (bacteria multirresistente)',
        'De contacto (diarrea infecciosa)',
    ];

    /** motivo => tipo de limpieza que exige */
    public const MOTIVOS = [
        'Ropa de cama sucia'                        => 'Rutinaria',
        'Ropa de cama húmeda o manchada'            => 'Rutinaria',
        'Derrame de fluidos corporales'             => 'Terminal',
        'Contacto con paciente infeccioso'          => 'Terminal',
        'Desinfección programada de la sala'        => 'Terminal',
    ];

    public function minutosTerminal(): int
    {
        return (int) config('cies.limpieza_terminal_min', 45);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public function mapa()
    {
        $camas = DB::table('CAMA as c')
            ->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->leftJoin('INTERNACION as i', function ($j) {
                $j->on('i.COD_CAM', '=', 'c.COD_CAM')->where('i.EST_INT', 'Internado');
            })
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')
            ->get(['c.COD_CAM', 'c.NRO_CAM', 'c.EST_CAM', 's.COD_SAL', 's.NOM_SAL', 's.UBI_SAL',
                   'i.COD_INT', 'i.FECING_INT', 'i.MOT_INT', 'i.AIS_INT', 'p.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC',
                   'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);

        $abiertas = DB::table('LIMPIEZA_CAMA as l')
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'l.EJE_EMP')
            ->where('l.EST_LIM', '<>', 'Completada')
            ->orderBy('l.FECSOL_LIM')
            ->get(['l.*', 'e.NOM_EMP as EJE_NOM', 'e.APE_EMP as EJE_APE'])
            ->groupBy('COD_CAM');

        $min = $this->minutosTerminal();
        return $camas->each(function ($c) use ($abiertas, $min) {
            $c->LIMPIEZAS = $abiertas[$c->COD_CAM] ?? collect();
            $c->LIMPIEZAS->each(function ($l) use ($min) {
                $l->FALTAN_MIN = null;
                if ($l->TIP_LIM === 'Terminal' && $l->FECINI_LIM) {
                    $listo = Carbon::parse($l->FECINI_LIM)->addMinutes($min);
                    $l->FALTAN_MIN = Carbon::now()->lt($listo) ? (int) ceil(Carbon::now()->diffInMinutes($listo)) : 0;
                    $l->LISTO_EN = $listo->format('H:i');
                }
            });
        });
    }

    public function historialLimpiezas(int $limite = 15)
    {
        return DB::table('LIMPIEZA_CAMA as l')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'l.COD_CAM')
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'l.EJE_EMP')
            ->where('l.EST_LIM', 'Completada')
            ->orderByDesc('l.FECFIN_LIM')->limit($limite)
            ->get(['l.*', 'c.NRO_CAM', 'e.NOM_EMP', 'e.APE_EMP']);
    }

    // ------------------------------------------------------------------
    // Internación y alta
    // ------------------------------------------------------------------

    public function internar(string $codCam, string $codPac, string $codMedico, string $motivo, ?string $aislamiento, object $actor): string
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            throw new DomainException('Indica el motivo de la internación (de 5 a 200 caracteres).');
        }
        if ($aislamiento !== null && $aislamiento !== '' && !in_array($aislamiento, self::AISLAMIENTOS, true)) {
            throw new DomainException('Tipo de aislamiento no válido.');
        }

        return DB::transaction(function () use ($codCam, $codPac, $codMedico, $motivo, $aislamiento, $actor) {
            $cama = DB::table('CAMA')->where('COD_CAM', $codCam)->lockForUpdate()->first();
            if (!$cama) {
                throw new DomainException('La cama no existe.');
            }
            if ($cama->EST_CAM !== 'Disponible') {
                throw new DomainException("La cama {$cama->NRO_CAM} está «{$cama->EST_CAM}»: solo se interna en una cama disponible y limpia.");
            }
            if ($this->limpiezaAbierta($codCam)) {
                throw new DomainException("La cama {$cama->NRO_CAM} tiene una limpieza pendiente: no se puede usar hasta completarla.");
            }
            if (!DB::table('PACIENTE')->where('COD_PAC', $codPac)->exists()) {
                throw new DomainException('El paciente no existe.');
            }
            $ya = DB::table('INTERNACION')->where('COD_PAC', $codPac)->where('EST_INT', 'Internado')->first();
            if ($ya) {
                throw new DomainException("El paciente ya está internado ({$ya->COD_INT}).");
            }
            if (!DB::table('EMPLEADO')->where('COD_EMP', $codMedico)->whereNotNull('COD_ESP')->exists()) {
                throw new DomainException('Elige el médico tratante.');
            }

            $n = (int) DB::table('INTERNACION')->where('COD_INT', 'like', 'INT%')
                ->max(DB::raw('CAST(SUBSTRING(COD_INT, 4) AS UNSIGNED)')) + 1;
            $codInt = sprintf('INT%07d', $n);

            DB::table('INTERNACION')->insert([
                'COD_INT' => $codInt, 'FECING_INT' => now(), 'FECALT_INT' => null,
                'MOT_INT' => $motivo, 'AIS_INT' => $aislamiento ?: null, 'EST_INT' => 'Internado',
                'COD_PAC' => $codPac, 'COD_CAM' => $codCam, 'COD_EMP' => $codMedico,
            ]);
            DB::table('CAMA')->where('COD_CAM', $codCam)->update(['EST_CAM' => 'Ocupada']);

            Auditoria::registrar('Internación', 'Media', 'DOM03', 'INTERNACION', $codInt,
                "Ingreso a la cama {$cama->NRO_CAM}: {$motivo}" . ($aislamiento ? " · aislamiento {$aislamiento}" : ''),
                $actor, $codPac);

            return $codInt;
        });
    }

    public function marcarAislamiento(string $codInt, ?string $aislamiento, object $actor): void
    {
        if ($aislamiento !== null && $aislamiento !== '' && !in_array($aislamiento, self::AISLAMIENTOS, true)) {
            throw new DomainException('Tipo de aislamiento no válido.');
        }
        $i = DB::table('INTERNACION')->where('COD_INT', $codInt)->where('EST_INT', 'Internado')->first();
        if (!$i) {
            throw new DomainException('La internación no está activa.');
        }
        DB::table('INTERNACION')->where('COD_INT', $codInt)->update(['AIS_INT' => $aislamiento ?: null]);

        Auditoria::registrar('Aislamiento', $aislamiento ? 'Alta' : 'Media', 'DOM03', 'INTERNACION', $codInt,
            $aislamiento ? "Paciente en aislamiento: {$aislamiento}. Al alta la cama exigirá desinfección terminal"
                         : 'Se retira el aislamiento del paciente', $actor, $i->COD_PAC);
    }

    public function alta(string $codInt, object $actor): string
    {
        return DB::transaction(function () use ($codInt, $actor) {
            $i = DB::table('INTERNACION')->where('COD_INT', $codInt)->lockForUpdate()->first();
            if (!$i || $i->EST_INT !== 'Internado') {
                throw new DomainException('La internación no está activa.');
            }
            $cama = DB::table('CAMA')->where('COD_CAM', $i->COD_CAM)->lockForUpdate()->first();

            DB::table('INTERNACION')->where('COD_INT', $codInt)->update(['EST_INT' => 'Alta', 'FECALT_INT' => now()]);
            DB::table('CAMA')->where('COD_CAM', $i->COD_CAM)->update(['EST_CAM' => 'Limpieza']);

            // Las tareas abiertas mientras estaba ocupada se reemplazan por la limpieza de alta
            DB::table('LIMPIEZA_CAMA')->where('COD_CAM', $i->COD_CAM)->where('EST_LIM', 'Pendiente')->delete();

            $terminal = !empty($i->AIS_INT);
            $codLim = $this->nuevaLimpieza(
                $i->COD_CAM,
                $terminal ? 'Terminal' : 'Rutinaria',
                $terminal ? "Alta de paciente con aislamiento: {$i->AIS_INT}" : 'Alta del paciente',
                null,
                $codInt,
                $actor->COD_EMP
            );

            Auditoria::registrar('Alta internación', 'Media', 'DOM03', 'INTERNACION', $codInt,
                "Alta del paciente. La cama {$cama->NRO_CAM} queda en limpieza " . ($terminal ? 'TERMINAL' : 'rutinaria') . " ({$codLim})",
                $actor, $i->COD_PAC);

            return $terminal ? 'Terminal' : 'Rutinaria';
        });
    }

    // ------------------------------------------------------------------
    // Limpieza
    // ------------------------------------------------------------------

    /** Reporta ropa sucia, derrame, etc. Devuelve el tipo de limpieza generado. */
    public function reportar(string $codCam, string $motivo, ?string $observacion, object $actor): string
    {
        if (!array_key_exists($motivo, self::MOTIVOS)) {
            throw new DomainException('Elige el motivo.');
        }
        $tipo = self::MOTIVOS[$motivo];

        return DB::transaction(function () use ($codCam, $motivo, $tipo, $observacion, $actor) {
            $cama = DB::table('CAMA')->where('COD_CAM', $codCam)->lockForUpdate()->first();
            if (!$cama) {
                throw new DomainException('La cama no existe.');
            }
            $int = DB::table('INTERNACION')->where('COD_CAM', $codCam)->where('EST_INT', 'Internado')->first();
            if ($int && $tipo === 'Terminal') {
                throw new DomainException("La cama {$cama->NRO_CAM} está ocupada: la desinfección terminal se hace cuando la cama queda libre. "
                    . 'Si el paciente es infeccioso, registre su aislamiento: al darle de alta el sistema exigirá la desinfección terminal.');
            }
            if ($int && !empty($int->AIS_INT)) {
                // Con el paciente adentro también se cambia la ropa, pero con precauciones de aislamiento
                $observacion = trim(($observacion ?? '') . " · Paciente en aislamiento ({$int->AIS_INT}): usar EPP y embolsar la ropa como contaminada");
            }

            $abierta = $this->limpiezaAbierta($codCam);
            if ($abierta) {
                // Si ya hay una rutinaria abierta y ahora se exige terminal, se eleva a terminal
                if ($abierta->TIP_LIM === 'Rutinaria' && $tipo === 'Terminal') {
                    DB::table('LIMPIEZA_CAMA')->where('COD_LIM', $abierta->COD_LIM)->update([
                        'TIP_LIM' => 'Terminal', 'MOT_LIM' => mb_substr($motivo . ' (antes: ' . $abierta->MOT_LIM . ')', 0, 100),
                    ]);
                    Auditoria::registrar('Limpieza elevada', 'Alta', 'DOM03', 'CAMA', $codCam,
                        "Cama {$cama->NRO_CAM}: la limpieza {$abierta->COD_LIM} pasa a TERMINAL por {$motivo}", $actor);
                    return 'Terminal';
                }
                throw new DomainException("La cama {$cama->NRO_CAM} ya tiene una limpieza {$abierta->EST_LIM} ({$abierta->COD_LIM}).");
            }

            $this->nuevaLimpieza($codCam, $tipo, $motivo, $observacion, $int->COD_INT ?? null, $actor->COD_EMP);

            // Una cama libre sale de servicio; una ocupada sigue ocupada pero con la tarea pendiente
            if ($cama->EST_CAM === 'Disponible') {
                DB::table('CAMA')->where('COD_CAM', $codCam)->update(['EST_CAM' => 'Limpieza']);
            }

            Auditoria::registrar('Reporte limpieza', $tipo === 'Terminal' ? 'Alta' : 'Baja', 'DOM03', 'CAMA', $codCam,
                "Cama {$cama->NRO_CAM}: {$motivo}. Limpieza {$tipo}" . ($cama->EST_CAM === 'Disponible' ? ' (sale de servicio)' : ''), $actor);

            return $tipo;
        });
    }

    public function iniciar(string $codLim, object $actor): void
    {
        $l = DB::table('LIMPIEZA_CAMA')->where('COD_LIM', $codLim)->first();
        if (!$l || $l->EST_LIM !== 'Pendiente') {
            throw new DomainException('La limpieza no está pendiente.');
        }
        DB::table('LIMPIEZA_CAMA')->where('COD_LIM', $codLim)->update([
            'EST_LIM' => 'En proceso', 'FECINI_LIM' => now(), 'EJE_EMP' => $actor->COD_EMP,
        ]);
        Auditoria::registrar('Inicio limpieza', 'Baja', 'DOM03', 'CAMA', $l->COD_CAM, "Inicio de la limpieza {$l->TIP_LIM} {$codLim}", $actor);
    }

    /** $checks: ['sabanas' => bool, 'superficies' => bool, 'colchon' => bool, 'ventilacion' => bool] */
    public function completar(string $codLim, array $checks, ?string $observacion, object $actor): string
    {
        return DB::transaction(function () use ($codLim, $checks, $observacion, $actor) {
            $l = DB::table('LIMPIEZA_CAMA')->where('COD_LIM', $codLim)->lockForUpdate()->first();
            if (!$l || $l->EST_LIM !== 'En proceso') {
                throw new DomainException('Primero inicia la limpieza.');
            }

            $faltan = [];
            if (empty($checks['sabanas'])) {
                $faltan[] = 'cambio de sábanas y ropa de cama';
            }
            if (empty($checks['superficies'])) {
                $faltan[] = 'desinfección de superficies y barandas';
            }
            if (empty($checks['colchon'])) {
                $faltan[] = 'limpieza del colchón y la almohada';
            }
            if ($l->TIP_LIM === 'Terminal' && empty($checks['ventilacion'])) {
                $faltan[] = 'ventilación del ambiente';
            }
            if ($faltan) {
                throw new DomainException('No se puede liberar la cama: falta ' . implode(', ', $faltan) . '.');
            }

            if ($l->TIP_LIM === 'Terminal') {
                $listo = Carbon::parse($l->FECINI_LIM)->addMinutes($this->minutosTerminal());
                if (Carbon::now()->lt($listo)) {
                    $faltanMin = (int) ceil(Carbon::now()->diffInMinutes($listo));
                    throw new DomainException("Desinfección terminal: el desinfectante y la ventilación necesitan al menos {$this->minutosTerminal()} minutos. "
                        . "Faltan {$faltanMin} min (la cama estará lista a las {$listo->format('H:i')}).");
                }
            }

            DB::table('LIMPIEZA_CAMA')->where('COD_LIM', $codLim)->update([
                'EST_LIM' => 'Completada', 'FECFIN_LIM' => now(),
                'SAB_LIM' => 1, 'SUP_LIM' => 1, 'COL_LIM' => 1, 'VEN_LIM' => empty($checks['ventilacion']) ? 0 : 1,
                'OBS_LIM' => $observacion ? mb_substr(trim(($l->OBS_LIM ? $l->OBS_LIM . ' · ' : '') . $observacion), 0, 255) : $l->OBS_LIM,
                'EJE_EMP' => $l->EJE_EMP ?? $actor->COD_EMP,
            ]);

            $cama = DB::table('CAMA')->where('COD_CAM', $l->COD_CAM)->lockForUpdate()->first();
            $ocupada = DB::table('INTERNACION')->where('COD_CAM', $l->COD_CAM)->where('EST_INT', 'Internado')->exists();
            $resultado = 'La tarea quedó completada; la cama sigue ocupada por el paciente.';
            if (!$ocupada && !$this->limpiezaAbierta($l->COD_CAM)) {
                DB::table('CAMA')->where('COD_CAM', $l->COD_CAM)->update(['EST_CAM' => 'Disponible']);
                $resultado = "La cama {$cama->NRO_CAM} quedó limpia y DISPONIBLE.";
            }

            $minutos = (int) Carbon::parse($l->FECSOL_LIM)->diffInMinutes(Carbon::now());
            Auditoria::registrar('Limpieza completa', 'Baja', 'DOM03', 'CAMA', $l->COD_CAM,
                "Limpieza {$l->TIP_LIM} {$codLim} completada en {$minutos} min. {$resultado}", $actor);

            return $resultado;
        });
    }

    // ------------------------------------------------------------------

    private function limpiezaAbierta(string $codCam): ?object
    {
        return DB::table('LIMPIEZA_CAMA')->where('COD_CAM', $codCam)->where('EST_LIM', '<>', 'Completada')
            ->orderBy('FECSOL_LIM')->first();
    }

    private function nuevaLimpieza(string $codCam, string $tipo, string $motivo, ?string $obs, ?string $codInt, string $codEmp): string
    {
        $codLim = sprintf('LIM%07d', (int) DB::table('LIMPIEZA_CAMA')->max('id') + 1);
        DB::table('LIMPIEZA_CAMA')->insert([
            'COD_LIM' => $codLim, 'TIP_LIM' => $tipo, 'MOT_LIM' => mb_substr($motivo, 0, 100),
            'FECSOL_LIM' => now(), 'FECINI_LIM' => null, 'FECFIN_LIM' => null, 'EST_LIM' => 'Pendiente',
            'OBS_LIM' => $obs ? mb_substr($obs, 0, 255) : null,
            'COD_CAM' => $codCam, 'COD_INT' => $codInt, 'COD_EMP' => $codEmp, 'EJE_EMP' => null,
        ]);
        return $codLim;
    }
}
