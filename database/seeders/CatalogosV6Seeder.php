<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos de la versión 6 del esquema (05_ajustes_v6.sql):
 *  - CIE-10 (RF022, RNF015) y codificación de las consultas ya registradas (RN43)
 *  - Medicamentos con precio de venta y lotes (RF041, RF042)
 *  - Permisos por módulo y su asignación a los roles (RF043, RNF001)
 * Es idempotente: se puede ejecutar sobre una base ya cargada.
 */
class CatalogosV6Seeder extends Seeder
{
    /** Diagnóstico de los datos de demostración → código CIE-10 */
    public const MAPA_DIAGNOSTICOS = [
        'Acné' => 'L70.0', 'Amigdalitis' => 'J03.9', 'Arritmia en estudio' => 'I49.9',
        'Asesoría en anticoncepción' => 'Z30.0', 'Bronquiolitis' => 'J21.9', 'Caries dental' => 'K02.9',
        'Colelitiasis' => 'K80.2', 'Control de antígeno prostático' => 'Z12.5',
        'Control de crecimiento y desarrollo' => 'Z00.1', 'Control de enfermedad crónica' => 'Z09.8',
        'Control de fractura' => 'Z09.4', 'Control de lunares' => 'D22.9', 'Control de niño sano' => 'Z00.1',
        'Control de puerperio' => 'Z39.2', 'Control de salud general' => 'Z00.0', 'Control general' => 'Z00.0',
        'Control ginecológico y Papanicolaou' => 'Z01.4', 'Control post endoscopía' => 'Z09.8',
        'Control postoperatorio' => 'Z48.8', 'Control prenatal' => 'Z34.9', 'Dermatitis atópica' => 'L20.9',
        'Diabetes mellitus tipo 2' => 'E11.9', 'Diarrea aguda sin deshidratación' => 'A09.9',
        'Dislipidemia' => 'E78.5', 'Esguince de tobillo' => 'S93.4', 'Extracción dental' => 'K08.8',
        'Faringitis aguda' => 'J02.9', 'Gastritis aguda' => 'K29.1', 'Gastritis crónica' => 'K29.5',
        'Gingivitis' => 'K05.1', 'Gonartrosis' => 'M17.9', 'Hernia inguinal' => 'K40.9',
        'Hiperplasia prostática benigna' => 'N40', 'Hipertensión arterial' => 'I10',
        'Hipoacusia en estudio' => 'H91.9', 'Hipotiroidismo' => 'E03.9', 'Infección respiratoria aguda' => 'J06.9',
        'Infección urinaria' => 'N39.0', 'Infección urinaria baja' => 'N30.0',
        'Insuficiencia cardiaca en control' => 'I50.9', 'Lipoma' => 'D17.9', 'Litiasis renal' => 'N20.0',
        'Lumbalgia' => 'M54.5', 'Lumbalgia mecánica' => 'M54.5', 'Micosis cutánea' => 'B36.9',
        'Nódulo tiroideo' => 'E04.1', 'Obesidad' => 'E66.9', 'Orientación familiar' => 'Z63.8',
        'Otitis media aguda' => 'H66.9', 'Proceso de duelo' => 'Z63.4', 'Profilaxis y limpieza dental' => 'Z01.2',
        'Reflujo gastroesofágico' => 'K21.9', 'Resfrío común' => 'J00', 'Rinitis alérgica' => 'J30.4',
        'Seguimiento psicoterapéutico' => 'Z50.4', 'Síndrome de colon irritable' => 'K58.9',
        'Síndrome de ovario poliquístico' => 'E28.2', 'Sinusitis aguda' => 'J01.9',
        'Trastorno de ansiedad' => 'F41.9', 'Vaginosis bacteriana' => 'N76.0',
        'Valoración cardiológica prequirúrgica' => 'Z01.8',
    ];

    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('CIE10')) {
            $this->command?->warn('Falta el esquema v6 (database/sql/05_ajustes_v6.sql): se omiten los catálogos.');
            return;
        }
        $this->cie10();
        $this->medicamentos();
        $this->permisos();
    }

    private function cie10(): void
    {
        $c = [
            // [código, descripción, capítulo]
            ['A09.9', 'Gastroenteritis y colitis de origen no especificado', 'Infecciosas y parasitarias'],
            ['A15.0', 'Tuberculosis del pulmón', 'Infecciosas y parasitarias'],
            ['A53.9', 'Sífilis, no especificada', 'Infecciosas y parasitarias'],
            ['A54.9', 'Infección gonocócica, no especificada', 'Infecciosas y parasitarias'],
            ['A56.0', 'Infección por clamidias de las vías genitourinarias inferiores', 'Infecciosas y parasitarias'],
            ['B20', 'Enfermedad por VIH resultante en enfermedades infecciosas y parasitarias', 'Infecciosas y parasitarias'],
            ['B36.9', 'Micosis superficial, sin otra especificación', 'Infecciosas y parasitarias'],
            ['B37.3', 'Candidiasis de la vulva y de la vagina', 'Infecciosas y parasitarias'],
            ['B86', 'Escabiosis', 'Infecciosas y parasitarias'],
            ['U07.1', 'COVID-19, virus identificado', 'Códigos para propósitos especiales'],
            ['D17.9', 'Tumor benigno lipomatoso, de sitio no especificado', 'Tumores'],
            ['D22.9', 'Nevo melanocítico, sitio no especificado', 'Tumores'],
            ['D25.9', 'Leiomioma del útero, sin otra especificación', 'Tumores'],
            ['D50.9', 'Anemia por deficiencia de hierro sin otra especificación', 'Sangre'],
            ['E03.9', 'Hipotiroidismo, no especificado', 'Endocrinas y metabólicas'],
            ['E04.1', 'Nódulo tiroideo solitario no tóxico', 'Endocrinas y metabólicas'],
            ['E11.9', 'Diabetes mellitus tipo 2 sin mención de complicación', 'Endocrinas y metabólicas'],
            ['E28.2', 'Síndrome de ovario poliquístico', 'Endocrinas y metabólicas'],
            ['E66.9', 'Obesidad, no especificada', 'Endocrinas y metabólicas'],
            ['E78.5', 'Hiperlipidemia no especificada', 'Endocrinas y metabólicas'],
            ['E86', 'Depleción del volumen (deshidratación)', 'Endocrinas y metabólicas'],
            ['F32.9', 'Episodio depresivo, no especificado', 'Trastornos mentales'],
            ['F41.9', 'Trastorno de ansiedad, no especificado', 'Trastornos mentales'],
            ['G43.9', 'Migraña, no especificada', 'Sistema nervioso'],
            ['H10.9', 'Conjuntivitis, no especificada', 'Ojo'],
            ['H66.9', 'Otitis media, no especificada', 'Oído'],
            ['H91.9', 'Hipoacusia, no especificada', 'Oído'],
            ['I10', 'Hipertensión esencial (primaria)', 'Sistema circulatorio'],
            ['I20.9', 'Angina de pecho, no especificada', 'Sistema circulatorio'],
            ['I21.9', 'Infarto agudo del miocardio, sin otra especificación', 'Sistema circulatorio'],
            ['I49.9', 'Arritmia cardíaca, no especificada', 'Sistema circulatorio'],
            ['I50.9', 'Insuficiencia cardíaca, no especificada', 'Sistema circulatorio'],
            ['I83.9', 'Venas varicosas de los miembros inferiores', 'Sistema circulatorio'],
            ['J00', 'Rinofaringitis aguda (resfriado común)', 'Sistema respiratorio'],
            ['J01.9', 'Sinusitis aguda, no especificada', 'Sistema respiratorio'],
            ['J02.9', 'Faringitis aguda, no especificada', 'Sistema respiratorio'],
            ['J03.9', 'Amigdalitis aguda, no especificada', 'Sistema respiratorio'],
            ['J06.9', 'Infección aguda de las vías respiratorias superiores', 'Sistema respiratorio'],
            ['J18.9', 'Neumonía, no especificada', 'Sistema respiratorio'],
            ['J20.9', 'Bronquitis aguda, no especificada', 'Sistema respiratorio'],
            ['J21.9', 'Bronquiolitis aguda, no especificada', 'Sistema respiratorio'],
            ['J30.4', 'Rinitis alérgica, no especificada', 'Sistema respiratorio'],
            ['J45.9', 'Asma, no especificada', 'Sistema respiratorio'],
            ['K02.9', 'Caries dental, no especificada', 'Sistema digestivo'],
            ['K05.1', 'Gingivitis crónica', 'Sistema digestivo'],
            ['K08.8', 'Otras afecciones especificadas de los dientes', 'Sistema digestivo'],
            ['K21.9', 'Enfermedad del reflujo gastroesofágico sin esofagitis', 'Sistema digestivo'],
            ['K29.1', 'Otras gastritis agudas', 'Sistema digestivo'],
            ['K29.5', 'Gastritis crónica, no especificada', 'Sistema digestivo'],
            ['K35.8', 'Apendicitis aguda, otra y la no especificada', 'Sistema digestivo'],
            ['K40.9', 'Hernia inguinal unilateral, sin obstrucción ni gangrena', 'Sistema digestivo'],
            ['K58.9', 'Síndrome del colon irritable sin diarrea', 'Sistema digestivo'],
            ['K59.0', 'Constipación', 'Sistema digestivo'],
            ['K80.2', 'Cálculo de la vesícula biliar sin colecistitis', 'Sistema digestivo'],
            ['L03.9', 'Celulitis de sitio no especificado', 'Piel'],
            ['L20.9', 'Dermatitis atópica, no especificada', 'Piel'],
            ['L70.0', 'Acné vulgar', 'Piel'],
            ['M17.9', 'Gonartrosis, no especificada', 'Sistema osteomuscular'],
            ['M54.5', 'Lumbago no especificado', 'Sistema osteomuscular'],
            ['M79.1', 'Mialgia', 'Sistema osteomuscular'],
            ['N20.0', 'Cálculo del riñón', 'Sistema genitourinario'],
            ['N30.0', 'Cistitis aguda', 'Sistema genitourinario'],
            ['N39.0', 'Infección de vías urinarias, sitio no especificado', 'Sistema genitourinario'],
            ['N40', 'Hiperplasia de la próstata', 'Sistema genitourinario'],
            ['N73.9', 'Enfermedad inflamatoria pélvica femenina, no especificada', 'Sistema genitourinario'],
            ['N76.0', 'Vaginitis aguda', 'Sistema genitourinario'],
            ['N92.0', 'Menstruación excesiva y frecuente con ciclo regular', 'Sistema genitourinario'],
            ['N94.6', 'Dismenorrea, no especificada', 'Sistema genitourinario'],
            ['N95.1', 'Estados menopáusicos y climatéricos femeninos', 'Sistema genitourinario'],
            ['O14.9', 'Preeclampsia, no especificada', 'Embarazo, parto y puerperio'],
            ['O21.0', 'Hiperemesis gravídica leve', 'Embarazo, parto y puerperio'],
            ['O80', 'Parto único espontáneo', 'Embarazo, parto y puerperio'],
            ['O82', 'Parto único por cesárea', 'Embarazo, parto y puerperio'],
            ['R07.4', 'Dolor en el pecho, no especificado', 'Síntomas y signos'],
            ['R10.4', 'Otros dolores abdominales y los no especificados', 'Síntomas y signos'],
            ['R50.9', 'Fiebre, no especificada', 'Síntomas y signos'],
            ['R51', 'Cefalea', 'Síntomas y signos'],
            ['S52.5', 'Fractura de la epífisis inferior del radio', 'Traumatismos'],
            ['S82.6', 'Fractura del maléolo externo', 'Traumatismos'],
            ['S93.4', 'Esguinces y torceduras del tobillo', 'Traumatismos'],
            ['T78.4', 'Alergia no especificada', 'Traumatismos'],
            ['Z00.0', 'Examen médico general', 'Factores que influyen en la salud'],
            ['Z00.1', 'Control de salud de rutina del niño', 'Factores que influyen en la salud'],
            ['Z01.2', 'Examen odontológico', 'Factores que influyen en la salud'],
            ['Z01.4', 'Examen ginecológico (general) (de rutina)', 'Factores que influyen en la salud'],
            ['Z01.8', 'Otros exámenes especiales especificados', 'Factores que influyen en la salud'],
            ['Z09.4', 'Examen de seguimiento consecutivo al tratamiento de fractura', 'Factores que influyen en la salud'],
            ['Z09.8', 'Examen de seguimiento consecutivo a otros tratamientos', 'Factores que influyen en la salud'],
            ['Z12.5', 'Examen de pesquisa especial para tumor de la próstata', 'Factores que influyen en la salud'],
            ['Z23', 'Necesidad de inmunización contra enfermedad bacteriana única', 'Factores que influyen en la salud'],
            ['Z30.0', 'Consejo y asesoramiento general sobre la anticoncepción', 'Factores que influyen en la salud'],
            ['Z32.1', 'Embarazo confirmado', 'Factores que influyen en la salud'],
            ['Z34.9', 'Supervisión de embarazo normal no especificado', 'Factores que influyen en la salud'],
            ['Z39.2', 'Seguimiento postparto, de rutina', 'Factores que influyen en la salud'],
            ['Z48.8', 'Otros cuidados especificados posteriores a la cirugía', 'Factores que influyen en la salud'],
            ['Z50.4', 'Psicoterapia, no clasificada en otra parte', 'Factores que influyen en la salud'],
            ['Z63.4', 'Desaparición o muerte de un miembro de la familia', 'Factores que influyen en la salud'],
            ['Z63.8', 'Otros problemas especificados relacionados con el grupo primario de apoyo', 'Factores que influyen en la salud'],
        ];
        foreach ($c as [$cod, $des, $cap]) {
            DB::table('CIE10')->updateOrInsert(['COD_CIE' => $cod], ['DES_CIE' => $des, 'CAP_CIE' => $cap]);
        }

        // RN43: las consultas ya registradas quedan codificadas con su CIE-10
        foreach (self::MAPA_DIAGNOSTICOS as $dia => $cod) {
            DB::table('CONSULTA')->where('DIA_CNS', $dia)->whereNull('COD_CIE')->update(['COD_CIE' => $cod]);
        }
    }

    private function medicamentos(): void
    {
        $meds = [
            'MED00001' => ['Paracetamol 500 mg', 0.50],
            'MED00002' => ['Amoxicilina 500 mg', 1.20],
            'MED00003' => ['Ibuprofeno 400 mg', 0.80],
            'MED00004' => ['Omeprazol 20 mg', 1.00],
            'MED00005' => ['Metformina 850 mg', 0.70],
            'MED00006' => ['Losartán 50 mg', 0.90],
            'MED00007' => ['Loratadina 10 mg', 0.60],
            'MED00008' => ['Salbutamol inhalador 100 mcg', 35.00],
            'MED00009' => ['Ceftriaxona 1 g ampolla', 18.00],
            'MED00010' => ['Suero fisiológico 0,9 % 1000 mL', 22.00],
            'MED00011' => ['Metronidazol 500 mg', 0.90],
            'MED00012' => ['Ácido fólico 5 mg', 0.30],
            'MED00013' => ['Sulfato ferroso 300 mg', 0.40],
            'MED00014' => ['Diclofenaco 75 mg ampolla', 6.50],
            'MED00015' => ['Levotiroxina 100 mcg', 0.80],
            'MED00016' => ['Enalapril 10 mg', 0.50],
        ];
        foreach ($meds as $cod => [$des, $pre]) {
            if (DB::table('MEDICAMENTO')->where('COD_MED', $cod)->exists()) {
                DB::table('MEDICAMENTO')->where('COD_MED', $cod)->update(['PRE_MED' => $pre]);
            } else {
                DB::table('MEDICAMENTO')->insert(['COD_MED' => $cod, 'DES_MED' => $des, 'PRE_MED' => $pre]);
            }
        }

        // Un lote vigente por medicamento nuevo (fechas relativas a hoy para que la demo no envejezca)
        $n = (int) DB::table('LOTE_MEDICAMENTO')->max('id');
        foreach (array_keys($meds) as $i => $codMed) {
            if ($i < 3 || DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $codMed)->exists()) {
                continue;
            }
            $n++;
            DB::table('LOTE_MEDICAMENTO')->insert([
                'COD_LOT'    => sprintf('LOT%07d', $n),
                'NRO_LOT'    => 'L-' . now()->year . '-' . chr(65 + $i),
                'FECING_LOT' => now()->subMonths(2)->toDateString(),
                'FECVEN_LOT' => now()->addMonths(6 + $i)->toDateString(),
                'CANING_LOT' => 200,
                'CANDIS_LOT' => 200,
                'COD_MED'    => $codMed,
            ]);
        }
    }

    private function permisos(): void
    {
        foreach (config('permisos.modulos') as $cod => $m) {
            DB::table('PERMISO')->updateOrInsert(['COD_PER' => $cod], ['DES_PER' => $m['nombre']]);
        }
        if (DB::table('ROL_PERMISO')->exists()) {
            return; // ya configurados por el administrador: no se pisan
        }
        foreach (config('permisos.por_defecto') as $rol => $permisos) {
            foreach ($permisos as $per) {
                DB::table('ROL_PERMISO')->insertOrIgnore(['COD_ROL' => $rol, 'COD_PER' => $per]);
            }
        }
    }
}
