<?php

namespace App\Services;

use App\Support\ModeloCamas as M;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF050 · Recomendador de capacidad de camas (aporte de Investigación Operativa II, sección 7 del avance final).
 *
 * Toma el historial de las tablas CAMA e INTERNACION (no usa tablas nuevas), calcula la ocupación
 * media de cada mes, la clasifica en E1/E2/E3, estima la matriz de transición y combina la Cadena de
 * Markov con la Teoría de Decisiones para recomendar la capacidad de camas.
 */
class RecomendadorCamasService
{
    public const ESTADOS = ['E1 (baja)', 'E2 (media)', 'E3 (alta)'];
    public const MESES_MAX = 36;

    /** Capacidad de referencia por defecto: número de camas registradas (o 10 si aún no hay). */
    public function capacidadRegistrada(): int
    {
        $n = DB::table('CAMA')->count();
        return $n > 0 ? $n : 10;
    }

    /** Clasificación del mes según los umbrales: E1 < u1; u1 ≤ E2 ≤ u2; E3 > u2. */
    public static function clasificar(float $ocupacion, float $u1, float $u2): int
    {
        if ($ocupacion < $u1) {
            return 0;
        }
        return $ocupacion <= $u2 ? 1 : 2;
    }

    /**
     * Ocupación media mensual = días-cama ocupados / (camas de referencia × días del mes) × 100.
     * Devuelve los meses completos (para estimar P) y el mes en curso (estado inicial a(0)).
     */
    public function historialMensual(int $capRef, float $u1, float $u2, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        $inicioMesActual = $ahora->copy()->startOfMonth();
        $primero = DB::table('INTERNACION')->min('FECING_INT');

        if ($primero === null) {
            return ['meses' => [], 'actual' => null];
        }

        $desde = Carbon::parse($primero)->startOfMonth();
        $limite = $inicioMesActual->copy()->subMonths(self::MESES_MAX);
        if ($desde->lt($limite)) {
            $desde = $limite;
        }

        $internaciones = DB::table('INTERNACION')
            ->where('FECING_INT', '<', $ahora)
            ->where(function ($q) use ($desde) {
                $q->whereNull('FECALT_INT')->orWhere('FECALT_INT', '>', $desde);
            })
            ->get(['FECING_INT', 'FECALT_INT']);

        $tramos = $internaciones->map(fn ($i) => [
            Carbon::parse($i->FECING_INT)->getTimestamp(),
            ($i->FECALT_INT ? Carbon::parse($i->FECALT_INT) : $ahora)->getTimestamp(),
        ]);

        $meses = [];
        for ($m = $desde->copy(); $m->lt($inicioMesActual); $m->addMonth()) {
            $meses[] = $this->resumenMes($m->copy(), $m->copy()->addMonth(), $tramos, $capRef, $u1, $u2);
        }

        $actual = $ahora->gt($inicioMesActual)
            ? $this->resumenMes($inicioMesActual->copy(), $ahora->copy(), $tramos, $capRef, $u1, $u2, true)
            : null;

        return ['meses' => $meses, 'actual' => $actual];
    }

    private function resumenMes(Carbon $ini, Carbon $fin, $tramos, int $capRef, float $u1, float $u2, bool $enCurso = false): array
    {
        $a = $ini->getTimestamp();
        $b = $fin->getTimestamp();
        $segundos = 0;
        foreach ($tramos as [$x, $y]) {
            $segundos += max(0, min($b, $y) - max($a, $x));
        }
        $diasCama = $segundos / 86400;
        $diasPeriodo = ($b - $a) / 86400;
        $ocupacion = $diasPeriodo > 0 ? $diasCama / ($capRef * $diasPeriodo) * 100 : 0;

        return [
            'mes'       => $ini->locale('es')->isoFormat('MMM YYYY'),
            'clave'     => $ini->format('Y-m'),
            'diasCama'  => round($diasCama, 1),
            'dias'      => round($diasPeriodo, 1),
            'ocupacion' => round($ocupacion, 1),
            'estado'    => self::clasificar($ocupacion, $u1, $u2),
            'enCurso'   => $enCurso,
        ];
    }

    /**
     * Ejecuta el modelo completo con los parámetros del formulario.
     *
     * @param array $p capRef, u1, u2, n, nmin, fuente (auto|historial|referencia), inicio (auto|0|1|2),
     *                 capacidades[3], pagos[3][3]
     */
    public function calcular(array $p): array
    {
        $avisos = [];

        // 1. Datos del sistema: historial mensual de ocupación (CAMA + INTERNACION)
        $hist = $this->historialMensual($p['capRef'], $p['u1'], $p['u2']);
        $secuencia = array_column($hist['meses'], 'estado');

        // 2. Estimación de P: p̂ij = nij / ni
        $conteos = M::contarTransiciones($secuencia);
        $pEstimada = M::estimarP($conteos);
        $insuficientes = [];
        foreach ($conteos as $i => $fila) {
            if (array_sum($fila) < $p['nmin']) {
                $insuficientes[] = $i;
            }
        }
        $historialValido = empty($insuficientes) && M::esEstocastica($pEstimada) && M::esErgodica($pEstimada);

        if ($p['fuente'] === 'referencia') {
            $P = M::P_REFERENCIA;
            $fuente = 'Matriz de referencia del informe (elegida por el administrador)';
        } elseif ($historialValido) {
            $P = $pEstimada;
            $fuente = 'Matriz estimada con el historial del sistema';
        } else {
            $P = M::P_REFERENCIA;
            $fuente = 'Matriz de referencia del informe (el historial no es suficiente)';
            $niveles = ['baja', 'media', 'alta'];
            if (count($secuencia) < 2) {
                $avisos[] = 'Aún no hay meses completos de internación registrados; la sugerencia usa valores de referencia.';
            } elseif (! empty($insuficientes)) {
                $nombres = implode(' y ', array_map(fn ($i) => $niveles[$i], $insuficientes));
                $avisos[] = "El historial todavía tiene pocos meses de demanda {$nombres}; la sugerencia usa valores de referencia hasta contar con más datos.";
            } elseif (! M::esErgodica($pEstimada)) {
                $avisos[] = 'Con el historial actual algunos niveles de demanda nunca se alternan; la sugerencia usa valores de referencia.';
            }
        }

        // 3. Estado inicial a(0): mes en curso o el que indique el administrador
        if ($p['inicio'] !== 'auto') {
            $e0 = (int) $p['inicio'];
            $origenA0 = 'Indicado por el administrador';
        } elseif ($hist['actual'] !== null) {
            $e0 = $hist['actual']['estado'];
            $origenA0 = "Ocupación del mes en curso ({$hist['actual']['ocupacion']} %)";
        } else {
            $e0 = 1;
            $origenA0 = 'Sin datos del mes en curso: se supone demanda media';
        }
        $a0 = [0, 0, 0];
        $a0[$e0] = 1;

        // 4. Teoría de Decisiones sin probabilidades
        $pagos = $p['pagos'];
        $criterios = M::criterios($pagos);

        // 5. Cadena de Markov
        $n = $p['n'];
        $Pn = M::potencia($P, $n);
        $pi = M::estadoEstable($P);
        $retorno = M::tiemposRetorno($pi);
        $primerPaso = M::primerPaso($P, $pi);
        $horizontes = array_values(array_unique(array_filter([1, 2, 3, 6, $n, 12], fn ($h) => $h <= max(12, $n))));
        sort($horizontes);
        $absolutas = [];
        foreach ($horizontes as $h) {
            $absolutas[$h] = M::absolutas($a0, $P, $h);
        }
        $serie = [];
        for ($k = 0; $k <= max(12, $n); $k++) {
            $serie[$k] = M::absolutas($a0, $P, $k);
        }

        // 6. Decisión bajo riesgo: beneficio esperado
        $fuentesProb = [
            'Laplace (1/3 c/u)' => [1 / 3, 1 / 3, 1 / 3],
            'a(1)'              => M::absolutas($a0, $P, 1),
            "a({$n})"           => M::absolutas($a0, $P, $n),
            'π (largo plazo)'   => $pi,
        ];
        $ve = [];
        foreach ($fuentesProb as $nombre => $q) {
            $ve[$nombre] = M::beneficioEsperado($pagos, $q);
        }
        $vePi = $ve['π (largo plazo)'];
        $veN = $ve["a({$n})"];
        $recLargo = array_keys($vePi, max($vePi))[0];
        $recCorto = array_keys($veN, max($veN))[0];
        $ordenados = $vePi;
        rsort($ordenados);
        $margen = $ordenados[0] - ($ordenados[1] ?? $ordenados[0]);

        // Recomendación según el estado inicial y el horizonte
        $porEstado = [];
        foreach ([0, 1, 2] as $e) {
            $ae = [0, 0, 0];
            $ae[$e] = 1;
            foreach ([1, $n] as $h) {
                $v = M::beneficioEsperado($pagos, M::absolutas($ae, $P, $h));
                $porEstado[$e][$h] = ['alt' => array_keys($v, max($v))[0], 'valor' => max($v)];
            }
            $porEstado[$e]['pi'] = ['alt' => $recLargo, 'valor' => max($vePi)];
        }

        // 7. Indicadores y validación Montecarlo
        $desv = M::desviacion($pagos, $pi);
        $info = M::informacionPerfecta($pagos, $pi);
        $simulacion = M::simular($P, $pagos, 500, 12, $e0);

        return [
            'historial'      => $hist,
            'secuencia'      => $secuencia,
            'conteos'        => $conteos,
            'pEstimada'      => $pEstimada,
            'insuficientes'  => $insuficientes,
            'P'              => $P,
            'fuente'         => $fuente,
            'usaHistorial'   => $P === $pEstimada,
            'ergodica'       => M::esErgodica($P),
            'avisos'         => $avisos,
            'e0'             => $e0,
            'a0'             => $a0,
            'origenA0'       => $origenA0,
            'pagos'          => $pagos,
            'capacidades'    => $p['capacidades'],
            'criterios'      => $criterios,
            'n'              => $n,
            'Pn'             => $Pn,
            'absolutas'      => $absolutas,
            'serie'          => $serie,
            'pi'             => $pi,
            'retorno'        => $retorno,
            'primerPaso'     => $primerPaso,
            've'             => $ve,
            'recLargo'       => $recLargo,
            'recCorto'       => $recCorto,
            'margen'         => $margen,
            'porEstado'      => $porEstado,
            'desviacion'     => $desv,
            'informacion'    => $info,
            'simulacion'     => $simulacion,
        ];
    }

    /** Últimas decisiones registradas en AUDITORIA (RN44: solo lectura). */
    public function decisiones(int $limite = 10)
    {
        return DB::table('AUDITORIA as a')
            ->join('USUARIO as u', 'u.COD_USR', '=', 'a.COD_USR')
            ->where('a.ACC_AUD', 'Decisión capacidad')
            ->orderByDesc('a.FEC_AUD')
            ->limit($limite)
            ->get(['a.COD_AUD', 'a.FEC_AUD', 'a.DET_AUD', 'u.USR_USR']);
    }
}
