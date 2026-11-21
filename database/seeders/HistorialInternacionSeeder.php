<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * RF050 · Historial de ocupación de la sala de internación (10 camas).
 *
 * Carga las internaciones de los últimos 36 meses completos y del mes en curso, de modo que la
 * ocupación media de cada mes sea la de la Figura 4 del informe (noviembre de 2023 a octubre de
 * 2026 cuando el mes en curso es noviembre de 2026). Es un historial de referencia construido para
 * el ejercicio de IO II; solo usa tablas existentes (TIPO_SALA, SALA, CAMA, PACIENTE, INTERNACION).
 *
 * Si ya existe un historial anterior (internaciones con prefijo INH), lo reemplaza.
 */
class HistorialInternacionSeeder extends Seeder
{
    /** Ocupación media (%) de los 36 meses completos, del más antiguo al más reciente. */
    private const OCUPACION = [
        64.2, 42.8, 38.5, 61.7, 66.3, 69.1, 81.4, 88.6, 86.2, 71.5, 63.8, 79.4,
        66.9, 58.4, 41.2, 45.9, 64.5, 79.3, 89.7, 84.1, 71.8, 65.2, 62.9, 68.4,
        46.1, 57.6, 36.9, 60.8, 67.4, 83.5, 90.8, 72.4, 80.2, 79.8, 64.1, 44.6,
    ];

    /** Ocupación del mes en curso hasta hoy (demanda media). */
    private const OCUPACION_ACTUAL = 66.0;

    private const CAMAS = 10;

    private const MOTIVOS = [
        'Neumonía adquirida en la comunidad', 'Bronquiolitis', 'Observación postoperatoria', 'Recuperación de cesárea',
        'Infección urinaria complicada', 'Deshidratación moderada', 'Crisis asmática', 'Gastroenteritis aguda',
        'Control de diabetes descompensada', 'Control de hipertensión', 'Apendicectomía', 'Celulitis en tratamiento',
    ];

    public function run(): void
    {
        mt_srand(2026);

        // Reemplaza el historial anterior, si existe
        DB::table('INTERNACION')->where('COD_INT', 'like', 'INH%')->delete();

        DB::table('TIPO_SALA')->insertOrIgnore(['COD_TIS' => 'TIS001', 'DES_TIS' => 'Internación']);
        DB::table('SALA')->insertOrIgnore([
            'COD_SAL' => 'SAL001', 'NOM_SAL' => 'Internación General', 'UBI_SAL' => 'Piso 2, ala norte', 'COD_TIS' => 'TIS001',
        ]);
        for ($c = 1; $c <= self::CAMAS; $c++) {
            DB::table('CAMA')->insertOrIgnore([
                'COD_CAM' => sprintf('CAM%04d', $c), 'NRO_CAM' => $c, 'EST_CAM' => 'Disponible', 'COD_SAL' => 'SAL001',
            ]);
        }
        DB::table('CAMA')->where('COD_SAL', 'SAL001')->update(['EST_CAM' => 'Disponible']);

        // Restos de la versión anterior del historial (pacientes PAC09xxxxx sin cuenta)
        DB::table('PACIENTE')->where('COD_PAC', 'like', 'PAC09%')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('USUARIO')->whereColumn('USUARIO.COD_PAC', 'PACIENTE.COD_PAC'))
            ->delete();

        // Pacientes del historial, registrados como en recepción (RF036, RN07): ficha + cuenta con
        // usuario = CI y clave temporal «Cies» + CI que se cambia en el primer ingreso (RN03).
        $nombres = ['Ana', 'Luis', 'María', 'Jorge', 'Carla', 'Diego', 'Paola', 'Raúl', 'Sonia', 'Hugo',
                    'Elena', 'Marco', 'Rocío', 'Iván', 'Gabriela', 'Óscar', 'Lidia', 'Pablo', 'Teresa', 'Mario'];
        $mujeres = ['Ana', 'María', 'Carla', 'Paola', 'Sonia', 'Elena', 'Rocío', 'Gabriela', 'Lidia', 'Teresa'];
        $apellidos = ['Mamani', 'Quispe', 'Flores', 'Choque', 'Condori', 'Vargas', 'Rojas', 'Gutiérrez', 'Limachi', 'Huanca'];
        $registro = Carbon::now()->startOfMonth()->subMonths(count(self::OCUPACION));
        $pacientes = [];
        foreach ($nombres as $i => $nom) {
            $ci = (string) (4810235 + $i * 7919);
            $codPac = DB::table('PACIENTE')->where('CI_PAC', $ci)->value('COD_PAC');
            if ($codPac === null) {
                $codPac = sprintf('PAC%07d', (int) DB::table('PACIENTE')->max(DB::raw('CAST(SUBSTRING(COD_PAC, 4) AS UNSIGNED)')) + 1);
                DB::table('PACIENTE')->insert([
                    'COD_PAC' => $codPac, 'CI_PAC' => $ci, 'NOM_PAC' => $nom,
                    'APE_PAC' => $apellidos[$i % 10] . ' ' . $apellidos[($i + 3) % 10],
                    'SEX_PAC' => in_array($nom, $mujeres) ? 'F' : 'M',
                    'FECNAC_PAC' => sprintf('%d-%02d-%02d', 1952 + $i * 3 % 60, ($i * 5) % 12 + 1, ($i * 7) % 27 + 1),
                    'ESTCIV_PAC' => $i % 3 === 0 ? 'Soltero' : 'Casado',
                    'TEL_PAC' => (string) (71234560 + $i * 37),
                    'COR_PAC' => strtolower($this->sinTildes($nom)) . '.' . strtolower($this->sinTildes(explode(' ', $apellidos[$i % 10])[0])) . ($i + 1) . '@gmail.com',
                    'DIR_PAC' => 'La Paz',
                ]);
            }
            if (! DB::table('USUARIO')->where('COD_PAC', $codPac)->exists() && ! DB::table('USUARIO')->where('USR_USR', $ci)->exists()) {
                DB::table('USUARIO')->insert([
                    'COD_USR'    => sprintf('USR%05d', (int) DB::table('USUARIO')->max(DB::raw('CAST(SUBSTRING(COD_USR, 4) AS UNSIGNED)')) + 1),
                    'USR_USR'    => $ci,
                    'CON_USR'    => Hash::make('Cies' . $ci),
                    'EST_USR'    => 'Activo',
                    'INTFAL_USR' => 0,
                    'CAMCON_USR' => 1,
                    'FECREG_USR' => $registro->copy()->addDays($i * 9 + 2)->setTime(10, 0)->toDateTimeString(),
                    'COD_ROL'    => 'ROL006',
                    'COD_EMP'    => null,
                    'COD_PAC'    => $codPac,
                ]);
            }
            $pacientes[] = $codPac;
        }

        $ahora = Carbon::now();
        $inicioActual = $ahora->copy()->startOfMonth();
        $meses = count(self::OCUPACION);
        $filas = [];
        $activas = [];
        $num = 0;

        for ($idx = 0; $idx <= $meses; $idx++) {
            $mesInicio = $inicioActual->copy()->subMonths($meses - $idx);
            $actual = $idx === $meses;
            $ocupacion = ($actual ? self::OCUPACION_ACTUAL : self::OCUPACION[$idx]) / 100;

            // Días del periodo: el mes completo o, en el mes en curso, los días transcurridos
            $dias = $actual
                ? max(1, (int) floor($mesInicio->diffInHours($ahora) / 24))
                : $mesInicio->daysInMonth;

            foreach ($this->diasPorCama((int) round($ocupacion * $dias * self::CAMAS), $dias) as $c => $ocupados) {
                $estancias = $this->partir($ocupados);
                $huecos = $this->repartir($dias - $ocupados, count($estancias) + 1);

                // Mes en curso: en la proporción de la ocupación, la cama sigue ocupada hoy
                $sigueOcupada = $actual && $estancias && mt_rand(1, 100) <= (int) round($ocupacion * 100);
                if ($sigueOcupada) {
                    $huecos[0] += $huecos[count($estancias)];
                    $huecos[count($estancias)] = 0;
                }

                $dia = $huecos[0];
                foreach ($estancias as $e => $largo) {
                    $ingreso = $mesInicio->copy()->addDays($dia)->setTime(9, 0);
                    $alta = $ingreso->copy()->addDays($largo);
                    $dia += $largo + $huecos[$e + 1];

                    $num++;
                    $codCam = sprintf('CAM%04d', $c + 1);
                    // En el mes en curso, la última estancia de algunas camas sigue abierta
                    $enCurso = $actual && ($alta->gt($ahora) || ($sigueOcupada && $e === count($estancias) - 1));
                    $filas[] = [
                        'COD_INT'    => sprintf('INH%07d', $num),
                        'FECING_INT' => $ingreso->toDateTimeString(),
                        'FECALT_INT' => $enCurso ? null : $alta->toDateTimeString(),
                        'MOT_INT'    => self::MOTIVOS[mt_rand(0, count(self::MOTIVOS) - 1)],
                        'EST_INT'    => $enCurso ? 'Internado' : 'Alta',
                        'COD_PAC'    => $pacientes[$c * 2 + ($num % 2)],
                        'COD_CAM'    => $codCam,
                        'COD_EMP'    => 'EMP000003',
                    ];
                    if ($enCurso) {
                        $activas[] = $codCam;
                    }
                }
            }
        }

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('INTERNACION')->insert($lote);
        }

        // RN39: una cama con internación activa queda «Ocupada»
        if ($activas) {
            DB::table('CAMA')->whereIn('COD_CAM', array_unique($activas))->update(['EST_CAM' => 'Ocupada']);
        }
    }

    /** Reparte el total de días-cama del mes entre las camas, con pequeñas diferencias entre ellas. */
    private function diasPorCama(int $total, int $dias): array
    {
        $total = max(0, min($total, $dias * self::CAMAS));
        $base = intdiv($total, self::CAMAS);
        $out = array_fill(0, self::CAMAS, $base);
        for ($r = $total - $base * self::CAMAS; $r > 0; $r--) {
            $out[mt_rand(0, self::CAMAS - 1)]++;
        }
        // Mueve días entre camas sin cambiar el total, para que no todas tengan la misma ocupación
        for ($t = 0; $t < self::CAMAS; $t++) {
            $a = mt_rand(0, self::CAMAS - 1);
            $b = mt_rand(0, self::CAMAS - 1);
            $d = mt_rand(1, 3);
            if ($a !== $b && $out[$a] >= $d && $out[$b] + $d <= $dias) {
                $out[$a] -= $d;
                $out[$b] += $d;
            }
        }
        return $out;
    }

    /** Divide los días ocupados en estancias de 2 a 7 días. */
    private function partir(int $dias): array
    {
        $out = [];
        while ($dias > 0) {
            $largo = min($dias, mt_rand(2, 7));
            $out[] = $largo;
            $dias -= $largo;
        }
        return $out;
    }

    /** Reparte los días libres en $partes huecos aleatorios. */
    private function repartir(int $libres, int $partes): array
    {
        $h = array_fill(0, $partes, 0);
        for ($i = 0; $i < $libres; $i++) {
            $h[mt_rand(0, $partes - 1)]++;
        }
        return $h;
    }

    private function sinTildes(string $s): string
    {
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    }
}
