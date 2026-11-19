<?php

namespace App\Support;

/**
 * RF050 · Motor de cálculo del Recomendador de capacidad de camas (sección 6 del avance final de IO II).
 *
 * Clase sin acceso a base de datos: recibe matrices y vectores y devuelve resultados.
 * Implementa:
 *  - Teoría de Decisiones bajo incertidumbre: Wald, Maximax, Laplace y Savage.
 *  - Cadenas de Markov: Pⁿ (Chapman-Kolmogorov), a(n) = a(0)·Pⁿ, estado estable π,
 *    tiempos medios de retorno μii = 1/πi y de primer paso μij.
 *  - Decisión bajo riesgo: beneficio esperado VE(Ak) = Σj qj·bkj, desviación estándar y VEIP.
 *  - Simulación Montecarlo para validar el modelo analítico.
 *
 * Índices 0..2 = estados E1 (baja), E2 (media), E3 (alta); filas de la matriz de pagos = alternativas.
 */
class ModeloCamas
{
    public const EPS = 1e-12;

    /**
     * Matriz de transición de referencia (sección 6.1.2 del informe): estimada con los 36 meses de
     * ocupación de nov-2023 a oct-2026 (p̂ij = nij / ni). Se usa cuando el historial del sistema no alcanza.
     */
    public const P_REFERENCIA = [
        [2 / 6, 4 / 6, 0.0],
        [5 / 18, 8 / 18, 5 / 18],
        [0.0, 5 / 11, 6 / 11],
    ];

    /**
     * Matriz de pagos de referencia en Bs. por mes (sección 6.1.1): bkj = (tarifa − costo de atención) · 30.4 · mín(Ck, demanda)
     * − costo fijo · Ck, con tarifa Bs. 410/día-cama, atención Bs. 105/día, costo fijo Bs. 2,300/cama-mes y demanda
     * de 4.0, 6.8 y 12.4 camas. Alternativas: 10, 12 y 15 camas.
     */
    public const PAGOS_REFERENCIA = [
        [14088, 40050, 69720],
        [9488, 35450, 83664],
        [2588, 28550, 80473],
    ];

    // ------------------------------------------------------------------
    // Teoría de Decisiones bajo incertidumbre
    // ------------------------------------------------------------------

    /**
     * Aplica los cuatro criterios sin probabilidades.
     * Devuelve, para cada criterio, el valor por alternativa y los índices elegidos (puede haber empate).
     */
    public static function criterios(array $pagos): array
    {
        $m = count($pagos);
        $s = count($pagos[0]);

        $wald = array_map(fn ($f) => min($f), $pagos);
        $maximax = array_map(fn ($f) => max($f), $pagos);
        $laplace = array_map(fn ($f) => array_sum($f) / count($f), $pagos);

        // Savage: arrepentimiento = mejor pago de la columna − pago de la celda
        $mejorCol = [];
        for ($j = 0; $j < $s; $j++) {
            $mejorCol[$j] = max(array_column($pagos, $j));
        }
        $arrep = [];
        for ($k = 0; $k < $m; $k++) {
            for ($j = 0; $j < $s; $j++) {
                $arrep[$k][$j] = $mejorCol[$j] - $pagos[$k][$j];
            }
        }
        $savage = array_map(fn ($f) => max($f), $arrep);

        return [
            'mejorColumna'    => $mejorCol,
            'arrepentimiento' => $arrep,
            'Wald'    => ['valores' => $wald,    'elegidas' => self::argExtremos($wald, true),     'postura' => 'Conservadora (mayor de los mínimos)'],
            'Maximax' => ['valores' => $maximax, 'elegidas' => self::argExtremos($maximax, true),  'postura' => 'Optimista (mayor de los máximos)'],
            'Laplace' => ['valores' => $laplace, 'elegidas' => self::argExtremos($laplace, true),  'postura' => 'Neutral (escenarios igual de probables)'],
            'Savage'  => ['valores' => $savage,  'elegidas' => self::argExtremos($savage, false),  'postura' => 'Minimiza el arrepentimiento máximo'],
        ];
    }

    /** Índices del máximo (o mínimo) con tolerancia, para detectar empates. */
    public static function argExtremos(array $v, bool $max = true): array
    {
        $ext = $max ? max($v) : min($v);
        $idx = [];
        foreach ($v as $i => $x) {
            if (abs($x - $ext) < 1e-6) {
                $idx[] = $i;
            }
        }
        return $idx;
    }

    // ------------------------------------------------------------------
    // Cadenas de Markov
    // ------------------------------------------------------------------

    /** Estimación por máxima verosimilitud: p̂ij = nij / ni a partir de una secuencia de estados. */
    public static function contarTransiciones(array $secuencia, int $s = 3): array
    {
        $n = array_fill(0, $s, array_fill(0, $s, 0));
        for ($t = 0; $t + 1 < count($secuencia); $t++) {
            $n[$secuencia[$t]][$secuencia[$t + 1]]++;
        }
        return $n;
    }

    /** Matriz estimada; las filas sin transiciones quedan en null. */
    public static function estimarP(array $conteos): array
    {
        $p = [];
        foreach ($conteos as $i => $fila) {
            $ni = array_sum($fila);
            $p[$i] = $ni > 0 ? array_map(fn ($x) => $x / $ni, $fila) : null;
        }
        return $p;
    }

    /** R5: cada fila con elementos ≥ 0 que suman 1. */
    public static function esEstocastica(array $p): bool
    {
        foreach ($p as $fila) {
            if ($fila === null) {
                return false;
            }
            foreach ($fila as $x) {
                if ($x < -self::EPS) {
                    return false;
                }
            }
            if (abs(array_sum($fila) - 1) > 1e-6) {
                return false;
            }
        }
        return true;
    }

    /** Ergódica (irreducible y aperiódica): existe una potencia de P con todos los elementos positivos. */
    public static function esErgodica(array $p): bool
    {
        $s = count($p);
        $q = $p;
        for ($k = 1; $k <= $s * $s; $k++) {
            $positiva = true;
            foreach ($q as $fila) {
                foreach ($fila as $x) {
                    if ($x <= self::EPS) {
                        $positiva = false;
                        break 2;
                    }
                }
            }
            if ($positiva) {
                return true;
            }
            $q = self::multiplicar($q, $p);
        }
        return false;
    }

    public static function multiplicar(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b[0]);
        $k = count($b);
        $c = array_fill(0, $n, array_fill(0, $m, 0.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                $sum = 0.0;
                for ($t = 0; $t < $k; $t++) {
                    $sum += $a[$i][$t] * $b[$t][$j];
                }
                $c[$i][$j] = $sum;
            }
        }
        return $c;
    }

    /** Chapman-Kolmogorov: P(n) = Pⁿ. */
    public static function potencia(array $p, int $n): array
    {
        $s = count($p);
        $r = [];
        for ($i = 0; $i < $s; $i++) {
            for ($j = 0; $j < $s; $j++) {
                $r[$i][$j] = $i === $j ? 1.0 : 0.0;
            }
        }
        for ($t = 0; $t < $n; $t++) {
            $r = self::multiplicar($r, $p);
        }
        return $r;
    }

    /** Probabilidades absolutas a(n) = a(0)·Pⁿ. */
    public static function absolutas(array $a0, array $p, int $n): array
    {
        return self::multiplicar([$a0], self::potencia($p, $n))[0];
    }

    /** Estado estable: πP = π, Σπ = 1 (se reemplaza una ecuación por la normalización). */
    public static function estadoEstable(array $p): array
    {
        $s = count($p);
        $A = [];
        $b = [];
        for ($j = 0; $j < $s - 1; $j++) {        // (Pᵀ − I)π = 0
            for ($i = 0; $i < $s; $i++) {
                $A[$j][$i] = $p[$i][$j] - ($i === $j ? 1 : 0);
            }
            $b[$j] = 0.0;
        }
        $A[$s - 1] = array_fill(0, $s, 1.0);     // Σπ = 1
        $b[$s - 1] = 1.0;
        return self::resolver($A, $b);
    }

    /** Tiempos medios de retorno μii = 1/πi. */
    public static function tiemposRetorno(array $pi): array
    {
        return array_map(fn ($x) => $x > self::EPS ? 1 / $x : INF, $pi);
    }

    /**
     * Tiempos medios de primer paso: para cada destino j se resuelve μij = 1 + Σk≠j pik·μkj.
     * En la diagonal se coloca el tiempo de retorno 1/πj.
     */
    public static function primerPaso(array $p, array $pi): array
    {
        $s = count($p);
        $mu = array_fill(0, $s, array_fill(0, $s, 0.0));
        for ($j = 0; $j < $s; $j++) {
            $orig = array_values(array_filter(range(0, $s - 1), fn ($i) => $i !== $j));
            $A = [];
            $b = [];
            foreach ($orig as $r => $i) {
                foreach ($orig as $c => $k) {
                    $A[$r][$c] = ($i === $k ? 1.0 : 0.0) - $p[$i][$k];
                }
                $b[$r] = 1.0;
            }
            $x = self::resolver($A, $b);
            foreach ($orig as $r => $i) {
                $mu[$i][$j] = $x[$r];
            }
            $mu[$j][$j] = $pi[$j] > self::EPS ? 1 / $pi[$j] : INF;
        }
        return $mu;
    }

    /** Eliminación de Gauss con pivoteo parcial. */
    public static function resolver(array $A, array $b): array
    {
        $n = count($b);
        for ($c = 0; $c < $n; $c++) {
            $piv = $c;
            for ($r = $c + 1; $r < $n; $r++) {
                if (abs($A[$r][$c]) > abs($A[$piv][$c])) {
                    $piv = $r;
                }
            }
            [$A[$c], $A[$piv]] = [$A[$piv], $A[$c]];
            [$b[$c], $b[$piv]] = [$b[$piv], $b[$c]];
            if (abs($A[$c][$c]) < self::EPS) {
                throw new \RuntimeException('El sistema de ecuaciones no tiene solución única.');
            }
            for ($r = $c + 1; $r < $n; $r++) {
                $f = $A[$r][$c] / $A[$c][$c];
                for ($k = $c; $k < $n; $k++) {
                    $A[$r][$k] -= $f * $A[$c][$k];
                }
                $b[$r] -= $f * $b[$c];
            }
        }
        $x = array_fill(0, $n, 0.0);
        for ($r = $n - 1; $r >= 0; $r--) {
            $sum = $b[$r];
            for ($k = $r + 1; $k < $n; $k++) {
                $sum -= $A[$r][$k] * $x[$k];
            }
            $x[$r] = $sum / $A[$r][$r];
        }
        return $x;
    }

    // ------------------------------------------------------------------
    // Decisión bajo riesgo
    // ------------------------------------------------------------------

    /** VE(Ak) = Σj qj·bkj. */
    public static function beneficioEsperado(array $pagos, array $q): array
    {
        return array_map(fn ($fila) => array_sum(array_map(fn ($b, $p) => $b * $p, $fila, $q)), $pagos);
    }

    /** Desviación estándar del beneficio de cada alternativa con las probabilidades q. */
    public static function desviacion(array $pagos, array $q): array
    {
        $ve = self::beneficioEsperado($pagos, $q);
        $out = [];
        foreach ($pagos as $k => $fila) {
            $var = 0.0;
            foreach ($fila as $j => $b) {
                $var += $q[$j] * ($b - $ve[$k]) ** 2;
            }
            $out[$k] = sqrt(max(0, $var));
        }
        return $out;
    }

    /** Valor esperado con información perfecta y VEIP. */
    public static function informacionPerfecta(array $pagos, array $q): array
    {
        $s = count($q);
        $veip = 0.0;
        for ($j = 0; $j < $s; $j++) {
            $veip += $q[$j] * max(array_column($pagos, $j));
        }
        $mejor = max(self::beneficioEsperado($pagos, $q));
        return ['conInformacion' => $veip, 'veip' => $veip - $mejor];
    }

    /** Días promedio de un mes. */
    public const DIAS_MES = 30.4;

    /**
     * Matriz de pagos (sección 4.2 del informe): bkj = (tarifa − costo de atención) · 30.4 · Ocup − costo fijo · Ck,
     * con Ocup = mín(Ck, demanda del estado j). Tarifa y costo de atención por día-cama; costo fijo por cama al mes.
     */
    public static function pagosDesdeCostos(array $capacidades, array $demandaCamas, float $tarifaDia, float $costoFijoMes, float $costoAtencionDia): array
    {
        $pagos = [];
        foreach ($capacidades as $k => $c) {
            foreach ($demandaCamas as $j => $d) {
                $ocup = min($c, $d);
                $pagos[$k][$j] = round(($tarifaDia - $costoAtencionDia) * self::DIAS_MES * $ocup - $costoFijoMes * $c);
            }
        }
        return $pagos;
    }

    // ------------------------------------------------------------------
    // Simulación Montecarlo (validación)
    // ------------------------------------------------------------------

    /**
     * Genera $escenarios años posibles de $meses meses cada uno, partiendo del estado $inicio.
     * Devuelve la frecuencia de cada nivel de demanda en todos los meses simulados, el resultado
     * mensual medio de cada alternativa y el porcentaje de escenarios en que cada alternativa fue la mejor.
     */
    public static function simular(array $p, array $pagos, int $escenarios = 500, int $meses = 12, int $inicio = 1, int $semilla = 2026): array
    {
        mt_srand($semilla);
        $s = count($p);
        $m = count($pagos);
        $frec = array_fill(0, $s, 0);
        $suma = array_fill(0, $m, 0.0);
        $gana = array_fill(0, $m, 0);
        for ($e = 0; $e < $escenarios; $e++) {
            $estado = $inicio;
            $tot = array_fill(0, $m, 0.0);
            for ($t = 0; $t < $meses; $t++) {
                $u = mt_rand() / mt_getrandmax();
                $acum = 0.0;
                $sig = $s - 1;
                for ($j = 0; $j < $s; $j++) {
                    $acum += $p[$estado][$j];
                    if ($u <= $acum) {
                        $sig = $j;
                        break;
                    }
                }
                $estado = $sig;
                $frec[$estado]++;
                foreach ($pagos as $k => $fila) {
                    $tot[$k] += $fila[$estado];
                }
            }
            foreach ($tot as $k => $v) {
                $suma[$k] += $v;
            }
            $gana[array_keys($tot, max($tot))[0]]++;
        }
        $total = $escenarios * $meses;
        return [
            'escenarios'  => $escenarios,
            'meses'       => $meses,
            'frecuencias' => array_map(fn ($f) => $f / $total, $frec),
            'beneficio'   => array_map(fn ($x) => $x / $total, $suma),
            'gana'        => array_map(fn ($g) => $g / $escenarios, $gana),
        ];
    }
}
