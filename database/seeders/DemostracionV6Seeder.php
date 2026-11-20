<?php

namespace Database\Seeders;

use App\Services\FarmaciaService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración para los módulos del esquema v6, para que los paneles y gráficos
 * de laboratorio, farmacia y enfermería tengan historia:
 *  - Exámenes de laboratorio e imagenología de los últimos 90 días (órdenes, resultados, valores críticos)
 *  - Recetas de las consultas recientes (completadas, parciales y activas)
 *  - Ventas en mostrador de los últimos 30 días (con descuento PEPS de los lotes)
 *  - Pacientes internados: indicaciones, dosis con 5 correctos, omisiones, signos vitales,
 *    balance hídrico y notas de enfermería de los últimos 3 días
 * Cada bloque se salta si ya tiene datos (se puede ejecutar más de una vez).
 */
class DemostracionV6Seeder extends Seeder
{
    private const LAB = 'EMP000006';
    private const FARMACIA = 'EMP000007';
    private const ENFERMERIA = 'EMP000004';

    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('VENTA')) {
            $this->command?->warn('Falta el esquema v6: se omiten los datos de demostración v6.');
            return;
        }
        mt_srand(2026);
        $this->servicios();
        $this->laboratorio();
        $this->recetas();
        $this->ventas();
        $this->internados();
    }

    private function sig(string $tabla, string $prefijo, int $ancho): \Closure
    {
        $n = (int) DB::table($tabla)->max('id');
        return function () use (&$n, $prefijo, $ancho) {
            $n++;
            return $prefijo . str_pad((string) $n, $ancho, '0', STR_PAD_LEFT);
        };
    }

    private function servicios(): void
    {
        DB::table('CLASIFICACION_SERVICIO')->insertOrIgnore(['COD_CLA' => 'CLA0003', 'DES_CLA' => 'Imagenología']);
        $nuevos = [
            ['Examen general de orina', 30, 'CLA0002'], ['Urocultivo', 70, 'CLA0002'], ['Creatinina', 25, 'CLA0002'],
            ['TSH', 80, 'CLA0002'], ['Prueba de embarazo (beta HCG)', 45, 'CLA0002'], ['Papanicolaou', 60, 'CLA0002'],
            ['Ecografía obstétrica', 150, 'CLA0003'], ['Ecografía abdominal', 160, 'CLA0003'], ['Radiografía de tórax', 110, 'CLA0003'],
        ];
        foreach ($nuevos as [$nom, $pre, $cla]) {
            if (!DB::table('SERVICIO')->where('NOM_SRV', $nom)->exists()) {
                $cod = sprintf('SRV%05d', (int) DB::table('SERVICIO')->max(DB::raw('CAST(SUBSTRING(COD_SRV, 4) AS UNSIGNED)')) + 1);
                DB::table('SERVICIO')->insert(['COD_SRV' => $cod, 'NOM_SRV' => $nom, 'PRE_SRV' => $pre, 'COD_CLA' => $cla]);
            }
        }
    }

    private function laboratorio(): void
    {
        if (DB::table('ORDEN_EXAMEN')->count() > 50) {
            return;
        }
        $examenes = DB::table('SERVICIO')->whereIn('COD_CLA', ['CLA0002', 'CLA0003'])->pluck('NOM_SRV', 'COD_SRV');
        $peso = $examenes->keys()->mapWithKeys(fn ($c, $i) => [$c => max(1, 9 - $i)]); // los primeros se piden más
        $consultas = DB::table('CONSULTA')->where('FEC_CNS', '>=', now()->subDays(90))->where('FEC_CNS', '<=', now())
            ->inRandomOrder()->limit(420)->get(['COD_CNS', 'FEC_CNS', 'DIA_CNS']);
        $ord = $this->sig('ORDEN_EXAMEN', 'ORD', 7);
        $res = $this->sig('RESULTADO_EXAMEN', 'RES', 7);
        $indicaciones = ['Control de rutina', 'Descartar infección', 'Seguimiento de tratamiento', 'Valoración prequirúrgica', 'Control prenatal', 'Síntomas persistentes'];

        $ordenes = []; $resultados = [];
        foreach ($consultas as $c) {
            $srv = $this->ponderado($peso->all());
            $fecha = Carbon::parse($c->FEC_CNS);
            $dias = $fecha->diffInDays(now());
            $urg = mt_rand(1, 100) <= 12;
            // Las órdenes de los últimos días siguen en curso; las anteriores ya tienen resultado
            $estado = $dias > 3 ? (mt_rand(1, 100) <= 4 ? 'Cancelado' : 'Listo')
                : ['Pendiente', 'Muestra tomada', 'En proceso', 'Listo'][mt_rand(0, 3)];
            $cod = $ord();
            $ordenes[] = ['COD_ORD' => $cod, 'FEC_ORD' => $fecha->toDateString(), 'IND_ORD' => $indicaciones[mt_rand(0, 5)] . ' · ' . $c->DIA_CNS,
                          'EST_ORD' => $estado, 'PRI_ORD' => $urg ? 'Urgente' : 'Normal', 'COD_CNS' => $c->COD_CNS, 'COD_SRV' => $srv];
            if ($estado === 'Listo' || ($estado === 'En proceso' && mt_rand(0, 1))) {
                $critico = mt_rand(1, 100) <= ($urg ? 15 : 4);
                $fres = $fecha->copy()->addHours(mt_rand(4, $urg ? 12 : 48));
                if ($fres->gt(now())) {
                    $fres = now()->subMinutes(mt_rand(5, 120));
                }
                $resultados[] = ['COD_RES' => $res(), 'FEC_RES' => $fres, 'ARC_RES' => null, 'COD_ORD' => $cod,
                    'DES_RES' => ($critico ? 'VALOR CRÍTICO. ' : '') . $examenes[$srv] . ': ' . ($critico ? 'resultado fuera de rango, se avisó al médico.' : 'valores dentro de los rangos de referencia.'),
                    'EST_RES' => $estado === 'Listo' ? 'Publicado' : ['Cargado', 'Validado'][mt_rand(0, 1)], 'CRI_RES' => $critico ? 1 : 0, 'COD_EMP' => self::LAB];
            }
        }
        foreach (array_chunk($ordenes, 200) as $l) {
            DB::table('ORDEN_EXAMEN')->insert($l);
        }
        foreach (array_chunk($resultados, 200) as $l) {
            DB::table('RESULTADO_EXAMEN')->insert($l);
        }
    }

    private function recetas(): void
    {
        if (DB::table('RECETA')->count() > 20) {
            return;
        }
        $meds = DB::table('MEDICAMENTO')->pluck('DES_MED', 'COD_MED')->all();
        $orales = array_diff(array_keys($meds), ['MED00008', 'MED00009', 'MED00010', 'MED00014']);
        $consultas = DB::table('CONSULTA')->where('FEC_CNS', '>=', now()->subDays(60))->where('FEC_CNS', '<=', now())
            ->inRandomOrder()->limit(260)->get(['COD_CNS', 'FEC_CNS']);
        $rec = $this->sig('RECETA', 'REC', 7);
        $dre = $this->sig('DETALLE_RECETA', 'DRE', 7);
        foreach ($consultas as $c) {
            $fecha = Carbon::parse($c->FEC_CNS);
            $antigua = $fecha->diffInDays(now()) > 7;
            $estado = $antigua ? (mt_rand(1, 100) <= 85 ? 'Completada' : 'Activa') : (mt_rand(1, 100) <= 40 ? 'Completada' : 'Activa');
            $codRec = $rec();
            DB::table('RECETA')->insert(['COD_REC' => $codRec, 'FEC_REC' => $fecha->toDateString(), 'EST_REC' => $estado,
                'OBS_REC' => mt_rand(0, 1) ? 'Tomar con alimentos' : null, 'COD_CNS' => $c->COD_CNS]);
            $items = (array) array_rand(array_flip($orales), mt_rand(1, 3));
            foreach ($items as $med) {
                $fre = [8, 12, 24][mt_rand(0, 2)]; $dur = [3, 5, 7, 10][mt_rand(0, 3)];
                $req = FarmaciaService::requeridas($fre, $dur);
                DB::table('DETALLE_RECETA')->insert(['COD_DRE' => $dre(), 'DOS_DRE' => '1 tableta', 'FRE_DRE' => $fre, 'DUR_DRE' => $dur,
                    'CANENT_DRE' => $estado === 'Completada' ? $req : (mt_rand(0, 2) ? 0 : intdiv($req, 2)),
                    'COD_REC' => $codRec, 'COD_MED' => $med, 'COD_VIA' => 'VIA001']);
            }
        }
    }

    private function ventas(): void
    {
        if (DB::table('VENTA')->exists()) {
            return;
        }
        $meds = DB::table('MEDICAMENTO')->get()->keyBy('COD_MED');
        $metodos = DB::table('METODO_PAGO')->pluck('COD_MET')->all();
        $pacientes = DB::table('PACIENTE')->inRandomOrder()->limit(60)->get(['COD_PAC', 'NOM_PAC', 'APE_PAC'])->all();
        $internaciones = DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->where('i.EST_INT', 'Internado')->get(['i.COD_INT', 'i.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC'])->all();
        $nroVen = 0;
        $dve = $this->sig('DETALLE_VENTA', 'DVE', 7);

        for ($d = 29; $d >= 0; $d--) {
            $dia = Carbon::today()->subDays($d);
            $cuantas = $dia->isSunday() ? mt_rand(1, 3) : mt_rand(3, 8);
            for ($k = 0; $k < $cuantas; $k++) {
                $fecha = $dia->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59));
                if ($fecha->gt(now())) {
                    continue;
                }
                $hosp = $internaciones && mt_rand(1, 100) <= 15;
                $pac = $hosp ? $internaciones[array_rand($internaciones)] : (mt_rand(0, 1) ? $pacientes[array_rand($pacientes)] : null);
                $lineas = [];
                foreach ((array) array_rand($meds->all(), mt_rand(1, 3)) as $codMed) {
                    $cant = in_array($codMed, ['MED00008', 'MED00009', 'MED00010', 'MED00014'], true) ? mt_rand(1, 2) : mt_rand(2, 12);
                    $lotes = DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $codMed)->where('CANDIS_LOT', '>', 0)
                        ->where('FECVEN_LOT', '>=', $fecha->toDateString())->orderBy('FECVEN_LOT')->get();
                    if ($lotes->sum('CANDIS_LOT') < $cant + 20) { // deja stock para la demo en vivo
                        continue;
                    }
                    $resto = $cant;
                    foreach ($lotes as $l) {
                        if ($resto <= 0) break;
                        $toma = min($resto, (int) $l->CANDIS_LOT);
                        DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $l->COD_LOT)->decrement('CANDIS_LOT', $toma);
                        $lineas[] = ['COD_MED' => $codMed, 'COD_LOT' => $l->COD_LOT, 'CAN_DVE' => $toma, 'PRE_DVE' => $meds[$codMed]->PRE_MED];
                        $resto -= $toma;
                    }
                }
                if (!$lineas) {
                    continue;
                }
                $nro = sprintf('V-%08d', ++$nroVen);
                $anulada = mt_rand(1, 100) <= 3;
                DB::table('VENTA')->insert([
                    'NRO_VEN' => $nro, 'FEC_VEN' => $fecha, 'CLI_VEN' => $pac ? "{$pac->NOM_PAC} {$pac->APE_PAC}" : 'Cliente de mostrador',
                    'TOT_VEN' => round(collect($lineas)->sum(fn ($l) => $l['CAN_DVE'] * $l['PRE_DVE']), 2),
                    'EST_VEN' => $anulada ? 'Anulada' : 'Emitida', 'TIP_VEN' => $hosp ? 'Hospitalizado' : 'Ambulante',
                    'MOTANU_VEN' => $anulada ? 'Error en la cantidad registrada' : null,
                    'COD_PAC' => $pac->COD_PAC ?? null, 'COD_INT' => $hosp ? $pac->COD_INT : null,
                    'COD_MET' => $hosp ? null : $metodos[array_rand($metodos)], 'COD_EMP' => self::FARMACIA,
                ]);
                foreach ($lineas as $l) {
                    DB::table('DETALLE_VENTA')->insert(['COD_DVE' => $dve(), 'NRO_VEN' => $nro] + $l);
                    if ($anulada) {
                        DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $l['COD_LOT'])->increment('CANDIS_LOT', $l['CAN_DVE']);
                    }
                }
            }
        }
    }

    private function internados(): void
    {
        if (DB::table('DOSIS')->exists()) {
            return;
        }
        $indicaciones = [
            ['MED00009', '1 g', 'VIA002', 12, 7], ['MED00010', '1000 mL', 'VIA002', 8, 3],
            ['MED00001', '500 mg', 'VIA001', 8, 5], ['MED00014', '75 mg', 'VIA002', 12, 3], ['MED00004', '20 mg', 'VIA001', 24, 7],
        ];
        $cns = $this->sig('CONSULTA', 'CNS', 7); $rec = $this->sig('RECETA', 'REC', 7); $dre = $this->sig('DETALLE_RECETA', 'DRE', 7);
        $dos = $this->sig('DOSIS', 'DOS', 7); $inc = $this->sig('INCIDENCIA_DOSIS', 'INC', 7); $siv = $this->sig('SIGNO_VITAL', 'SIV', 7);
        $bal = $this->sig('BALANCE_HIDRICO', 'BAL', 7); $nen = $this->sig('NOTA_ENFERMERIA', 'NEN', 7);
        $motivosOmision = ['Paciente en ayunas para estudio', 'Paciente rechaza la medicación', 'Vómito previo a la dosis', 'Vía periférica obstruida'];

        foreach (DB::table('INTERNACION')->where('EST_INT', 'Internado')->get() as $i => $int) {
            $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $int->COD_PAC)->value('COD_HIS');
            $cit = DB::table('CITA')->where('COD_PAC', $int->COD_PAC)->orderByDesc('FEC_CIT')->value('COD_CIT');
            if (!$his || !$cit) {
                continue;
            }
            $inicio = Carbon::parse($int->FECING_INT)->max(now()->subDays(3))->startOfHour();
            $codCns = $cns();
            DB::table('CONSULTA')->insert(['COD_CNS' => $codCns, 'FEC_CNS' => $inicio, 'DIA_CNS' => $int->MOT_INT, 'COD_CIE' => 'Z48.8',
                'PLA_CNS' => 'Indicaciones de internación', 'COD_CIT' => $cit, 'COD_HIS' => $his, 'COD_EMP' => $int->COD_EMP]);
            $codRec = $rec();
            DB::table('RECETA')->insert(['COD_REC' => $codRec, 'FEC_REC' => $inicio->toDateString(), 'EST_REC' => 'Activa',
                'OBS_REC' => 'Indicaciones de internación', 'COD_CNS' => $codCns]);

            foreach ([$indicaciones[$i % 5], $indicaciones[($i + 2) % 5]] as [$med, $dosis, $via, $fre, $dur]) {
                $codDre = $dre();
                DB::table('DETALLE_RECETA')->insert(['COD_DRE' => $codDre, 'DOS_DRE' => $dosis, 'FRE_DRE' => $fre, 'DUR_DRE' => $dur,
                    'CANENT_DRE' => 0, 'COD_REC' => $codRec, 'COD_MED' => $med, 'COD_VIA' => $via]);
                for ($t = $inicio->copy(); $t->lte(now()->addHours(24)); $t->addHours($fre)) {
                    $pasada = $t->lt(now()->subMinutes(30));
                    $omitida = $pasada && mt_rand(1, 100) <= 8;
                    $codDos = $dos();
                    DB::table('DOSIS')->insert(['COD_DOS' => $codDos, 'FECPRG_DOS' => $t->copy(),
                        'FECADM_DOS' => $pasada && !$omitida ? $t->copy()->addMinutes(mt_rand(-20, 25)) : null,
                        'DOS_DOS' => $dosis, 'EST_DOS' => $pasada ? ($omitida ? 'Omitida' : 'Administrada') : 'Programada',
                        'COD_INT' => $int->COD_INT, 'COD_DRE' => $codDre, 'COD_INS' => null,
                        'COD_EMP' => $pasada ? self::ENFERMERIA : null]);
                    if ($omitida) {
                        DB::table('INCIDENCIA_DOSIS')->insert(['COD_INC' => $inc(), 'FEC_INC' => $t->copy(), 'FECNOT_INC' => $t->copy(),
                            'MOT_INC' => $motivosOmision[mt_rand(0, 3)], 'COD_DOS' => $codDos, 'COD_EMP' => self::ENFERMERIA]);
                    }
                }
            }

            for ($t = $inicio->copy(); $t->lt(now()); $t->addHours(6)) {
                $sis = mt_rand(105, 140);
                DB::table('SIGNO_VITAL')->insert(['COD_SIV' => $siv(), 'FEC_SIV' => $t->copy()->addMinutes(mt_rand(0, 40)),
                    'SIS_SIV' => $sis, 'DIA_SIV' => $sis - mt_rand(35, 50), 'PUL_SIV' => mt_rand(62, 98),
                    'TEM_SIV' => mt_rand(362, 384) / 10, 'COD_PAC' => $int->COD_PAC, 'COD_EMP' => self::ENFERMERIA]);
                DB::table('BALANCE_HIDRICO')->insert([
                    ['COD_BAL' => $bal(), 'FEC_BAL' => $t->copy()->addMinutes(10), 'TIP_BAL' => 'Ingreso', 'CON_BAL' => ['Oral', 'Intravenoso'][mt_rand(0, 1)],
                     'VOL_BAL' => mt_rand(200, 500), 'COD_INT' => $int->COD_INT, 'COD_EMP' => self::ENFERMERIA],
                    ['COD_BAL' => $bal(), 'FEC_BAL' => $t->copy()->addMinutes(50), 'TIP_BAL' => 'Egreso', 'CON_BAL' => 'Orina',
                     'VOL_BAL' => mt_rand(150, 450), 'COD_INT' => $int->COD_INT, 'COD_EMP' => self::ENFERMERIA],
                ]);
            }
            foreach ([[7, 'Mañana'], [13, 'Tarde'], [19, 'Noche']] as [$h, $turno]) {
                $f = now()->subDay()->setTime($h, 5);
                DB::table('NOTA_ENFERMERIA')->insert(['COD_NEN' => $nen(), 'FEC_NEN' => $f, 'TIP_NEN' => 'Cambio de turno', 'TUR_NEN' => $turno,
                    'TXT_NEN' => "Turno {$turno}: paciente estable, tolera la medicación indicada. Sin eventos adversos. Se mantiene vigilancia de signos.",
                    'COD_INT' => $int->COD_INT, 'COD_EMP' => self::ENFERMERIA]);
            }
        }
    }

    private function ponderado(array $pesos): string
    {
        $r = mt_rand(1, array_sum($pesos));
        foreach ($pesos as $k => $p) {
            if (($r -= $p) <= 0) {
                return $k;
            }
        }
        return array_key_first($pesos);
    }
}
