<?php

namespace Tests\Unit;

use App\Support\ModeloCamas as M;
use PHPUnit\Framework\TestCase;

/**
 * RF050: el motor de cálculo reproduce los resultados del avance final de IO II
 * (Tabla 6: matriz de pagos; Tabla 7: matriz de transición estimada con 36 meses).
 */
class ModeloCamasTest extends TestCase
{
    private const P = M::P_REFERENCIA;
    private const B = M::PAGOS_REFERENCIA;

    /** Historial de 36 meses (nov-2023 a oct-2026): 0 = baja, 1 = media, 2 = alta. */
    private const HISTORIAL = [1, 0, 0, 1, 1, 1, 2, 2, 2, 1, 1, 2, 1, 1, 0, 0, 1, 2, 2, 2, 1, 1, 1, 1, 0, 1, 0, 1, 1, 2, 2, 1, 2, 2, 1, 0];

    public function test_matriz_de_pagos_desde_tarifa_y_costos(): void
    {
        $b = M::pagosDesdeCostos([10, 12, 15], [4.0, 6.8, 12.4], 410, 2300, 105);
        $this->assertEquals(self::B, $b);
    }

    public function test_estimacion_de_p_con_36_meses(): void
    {
        $n = M::contarTransiciones(self::HISTORIAL);
        $this->assertSame([[2, 4, 0], [5, 8, 5], [0, 5, 6]], $n);
        $p = M::estimarP($n);
        foreach ($p as $i => $fila) {
            $this->assertEqualsWithDelta(self::P[$i], $fila, 1e-12);
        }
        $this->assertTrue(M::esEstocastica($p));
        $this->assertTrue(M::esErgodica($p));   // p13 = p31 = 0, pero P² es positiva
    }

    public function test_criterios_sin_probabilidades(): void
    {
        $c = M::criterios(self::B);
        $this->assertSame([0], $c['Wald']['elegidas']);
        $this->assertSame([1], $c['Maximax']['elegidas']);
        $this->assertSame([1], $c['Laplace']['elegidas']);
        $this->assertSame([1], $c['Savage']['elegidas']);
        $this->assertEquals([13944, 4600, 11500], $c['Savage']['valores']);
    }

    public function test_probabilidades_de_n_pasos_y_absolutas(): void
    {
        $p2 = M::potencia(self::P, 2);
        $this->assertEqualsWithDelta(0.1852, $p2[0][2], 1e-4);
        $a3 = M::absolutas([0, 1, 0], self::P, 3);
        $this->assertEqualsWithDelta([0.2134, 0.4952, 0.2914], $a3, 1e-4);
    }

    public function test_estado_estable_y_tiempos(): void
    {
        $pi = M::estadoEstable(self::P);
        $this->assertEqualsWithDelta([15 / 73, 36 / 73, 22 / 73], $pi, 1e-12);
        $mu = M::primerPaso(self::P, $pi);
        $this->assertEqualsWithDelta(6.6, $mu[0][2], 1e-9);      // μ13
        $this->assertEqualsWithDelta(5.1, $mu[1][2], 1e-9);      // μ23
        $this->assertEqualsWithDelta(5.8, $mu[1][0], 1e-9);      // μ21
        $this->assertEqualsWithDelta(73 / 15, $mu[0][0], 1e-9);  // μ11 = 1/π1
    }

    public function test_beneficio_esperado_recomienda_12_camas(): void
    {
        $pi = M::estadoEstable(self::P);
        $ve = M::beneficioEsperado(self::B, $pi);
        $this->assertEqualsWithDelta([43656.99, 44645.59, 38863.37], $ve, 0.01);
        $this->assertSame(1, array_keys($ve, max($ve))[0]);
        $this->assertEqualsWithDelta(3213.70, M::informacionPerfecta(self::B, $pi)['veip'], 0.01);
    }

    public function test_simulacion_de_500_escenarios(): void
    {
        $sim = M::simular(self::P, self::B, 500, 12, 1);
        $this->assertEqualsWithDelta([0.2147, 0.4818, 0.3035], $sim['frecuencias'], 1e-4);
        $this->assertEqualsWithDelta([0.356, 0.644, 0.0], $sim['gana'], 1e-9);
    }
}
