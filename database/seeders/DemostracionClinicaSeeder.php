<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración coherentes con la Clínica CIES La Paz (Av. Tejada Sorzano #393, Miraflores).
 *
 *  1. Especialidades que ofrece la clínica (14) con su servicio de consulta y precio.
 *  2. Consultorios (10) y 17 médicos (el de prueba + 16) que cubren todas las especialidades,
 *     con turno, consultorio y horario semanal. Cada médico tiene su cuenta:
 *     usuario = inicial del nombre + apellido (p. ej. amamani) y clave = usuario + 123.
 *  3. Pacientes adicionales (adultos y niños) registrados como en recepción (usuario = CI, clave «Cies» + CI).
 *  4. Historial de citas de los últimos 12 meses y las próximas 2 semanas, respetando el horario de cada
 *     médico: atendidas (con consulta y pagos), inasistencias, cancelaciones y confirmadas.
 *     Sirve para los reportes y gráficos (pacientes por médico, especialidad más frecuentada por mes…).
 *  5. Estado de camas de ejemplo: un paciente internado con aislamiento respiratorio (COVID-19),
 *     una cama con ropa sucia y otra en desinfección terminal (AJ07, AJ08).
 *
 * Es idempotente: si los médicos o el historial ya existen, no los duplica.
 */
class DemostracionClinicaSeeder extends Seeder
{
    /** [COD_ESP, especialidad, COD_SRV, servicio de consulta, precio Bs, demanda por bloque de 4 h, estacional] */
    private const ESPECIALIDADES = [
        ['ESP0001', 'Medicina General',            'SRV00001', 'Consulta general',                    80.00, 4.0, true],
        ['ESP0002', 'Ginecología y Obstetricia',   'SRV00005', 'Consulta de Ginecología y Obstetricia', 120.00, 4.2, false],
        ['ESP0003', 'Pediatría',                   'SRV00006', 'Consulta de Pediatría',               100.00, 3.8, true],
        ['ESP0004', 'Psicología',                  'SRV00007', 'Consulta de Psicología',              100.00, 2.0, false],
        ['ESP0005', 'Odontología',                 'SRV00008', 'Consulta de Odontología',              90.00, 2.8, false],
        ['ESP0006', 'Urología',                    'SRV00009', 'Consulta de Urología',                130.00, 2.0, false],
        ['ESP0007', 'Medicina Interna',            'SRV00010', 'Consulta de Medicina Interna',        120.00, 2.4, true],
        ['ESP0008', 'Traumatología',               'SRV00011', 'Consulta de Traumatología',           130.00, 2.4, false],
        ['ESP0009', 'Cardiología',                 'SRV00012', 'Consulta de Cardiología',             150.00, 1.9, false],
        ['ESP0010', 'Dermatología',                'SRV00013', 'Consulta de Dermatología',            120.00, 1.9, false],
        ['ESP0011', 'Otorrinolaringología',        'SRV00014', 'Consulta de Otorrinolaringología',    120.00, 1.7, true],
        ['ESP0012', 'Endocrinología',              'SRV00015', 'Consulta de Endocrinología',          140.00, 1.6, false],
        ['ESP0013', 'Gastroenterología',           'SRV00016', 'Consulta de Gastroenterología',       140.00, 1.6, false],
        ['ESP0014', 'Cirugía General',             'SRV00017', 'Consulta de Cirugía General',         130.00, 1.4, false],
    ];

    /** Consultorios: [COD_CON, nombre, ubicación] */
    private const CONSULTORIOS = [
        ['CON002', 'Consultorio 2 · Ginecología',   'Planta baja'],
        ['CON003', 'Consultorio 3 · Pediatría',     'Planta baja'],
        ['CON004', 'Consultorio 4',                 'Planta baja'],
        ['CON005', 'Gabinete odontológico',         'Primer piso'],
        ['CON006', 'Consultorio 6',                 'Primer piso'],
        ['CON007', 'Consultorio 7',                 'Primer piso'],
        ['CON008', 'Consultorio 8',                 'Primer piso'],
        ['CON009', 'Consultorio 9',                 'Primer piso'],
        ['CON010', 'Consultorio de Cirugía',        'Segundo piso'],
    ];

    /** [COD_EMP, CI, título, nombre, apellidos, sexo, COD_ESP, turno, COD_CON, matrícula, sábado] */
    private const MEDICOS = [
        ['EMP000009', '4521367', 'Dra', 'Ana Gabriela',  'Mamani Quispe',     'F', 'ESP0002', 'Mañana', 'CON002', 'MP-M-3121', true],
        ['EMP000010', '3987412', 'Dr',  'Jorge Luis',    'Vargas Choque',     'M', 'ESP0002', 'Tarde',  'CON002', 'MP-V-2874', false],
        ['EMP000011', '5123698', 'Dra', 'Carla',         'Rojas Flores',      'F', 'ESP0003', 'Mañana', 'CON003', 'MP-R-4410', true],
        ['EMP000012', '4785123', 'Dr',  'Marco Antonio', 'Gutiérrez Limachi', 'M', 'ESP0003', 'Tarde',  'CON003', 'MP-G-3956', false],
        ['EMP000013', '6012457', 'Dra', 'Patricia',      'Condori Huanca',    'F', 'ESP0001', 'Mañana', 'CON004', 'MP-C-5102', true],
        ['EMP000014', '5698741', 'Lic', 'Daniela',       'Salazar Rivera',    'F', 'ESP0004', 'Tarde',  'CON004', 'CPB-1187',  false],
        ['EMP000015', '4302589', 'Dr',  'Rodrigo',       'Paredes Ticona',    'M', 'ESP0005', 'Mañana', 'CON005', 'COB-2290',  true],
        ['EMP000016', '3654128', 'Dr',  'Fernando',      'Aliaga Mendoza',    'M', 'ESP0006', 'Mañana', 'CON006', 'MP-A-2215', false],
        ['EMP000017', '4896321', 'Dr',  'Sergio',        'Calle Poma',        'M', 'ESP0008', 'Tarde',  'CON006', 'MP-C-3377', false],
        ['EMP000018', '3741852', 'Dra', 'Mónica',        'Ríos Zeballos',     'F', 'ESP0007', 'Mañana', 'CON007', 'MP-R-2563', false],
        ['EMP000019', '3215698', 'Dr',  'Gonzalo',       'Arce Villarroel',   'M', 'ESP0009', 'Tarde',  'CON007', 'MP-A-1904', false],
        ['EMP000020', '5874123', 'Dra', 'Lorena',        'Apaza Cruz',        'F', 'ESP0010', 'Mañana', 'CON008', 'MP-A-4682', false],
        ['EMP000021', '4123987', 'Dr',  'Álvaro',        'Siñani Laura',      'M', 'ESP0011', 'Tarde',  'CON008', 'MP-S-3518', false],
        ['EMP000022', '4659832', 'Dra', 'Verónica',      'Mercado Loza',      'F', 'ESP0012', 'Mañana', 'CON009', 'MP-M-3745', false],
        ['EMP000023', '3896547', 'Dr',  'Ricardo',       'Tapia Guzmán',      'M', 'ESP0013', 'Tarde',  'CON009', 'MP-T-2981', false],
        ['EMP000024', '3478521', 'Dr',  'Hernán',        'Blanco Medina',     'M', 'ESP0014', 'Mañana', 'CON010', 'MP-B-2140', false],
    ];

    /** Diagnóstico / motivo de consulta frecuente por especialidad */
    private const DIAGNOSTICOS = [
        'ESP0001' => ['Resfrío común', 'Faringitis aguda', 'Infección urinaria baja', 'Gastritis aguda', 'Lumbalgia mecánica', 'Control de salud general'],
        'ESP0002' => ['Control prenatal', 'Control ginecológico y Papanicolaou', 'Vaginosis bacteriana', 'Asesoría en anticoncepción', 'Síndrome de ovario poliquístico', 'Control de puerperio'],
        'ESP0003' => ['Control de niño sano', 'Infección respiratoria aguda', 'Diarrea aguda sin deshidratación', 'Otitis media aguda', 'Bronquiolitis', 'Control de crecimiento y desarrollo'],
        'ESP0004' => ['Trastorno de ansiedad', 'Orientación familiar', 'Proceso de duelo', 'Seguimiento psicoterapéutico'],
        'ESP0005' => ['Caries dental', 'Profilaxis y limpieza dental', 'Gingivitis', 'Extracción dental'],
        'ESP0006' => ['Hiperplasia prostática benigna', 'Infección urinaria', 'Litiasis renal', 'Control de antígeno prostático'],
        'ESP0007' => ['Hipertensión arterial', 'Diabetes mellitus tipo 2', 'Dislipidemia', 'Control de enfermedad crónica'],
        'ESP0008' => ['Esguince de tobillo', 'Lumbalgia', 'Gonartrosis', 'Control de fractura'],
        'ESP0009' => ['Hipertensión arterial', 'Arritmia en estudio', 'Valoración cardiológica prequirúrgica', 'Insuficiencia cardiaca en control'],
        'ESP0010' => ['Acné', 'Dermatitis atópica', 'Micosis cutánea', 'Control de lunares'],
        'ESP0011' => ['Rinitis alérgica', 'Sinusitis aguda', 'Amigdalitis', 'Hipoacusia en estudio'],
        'ESP0012' => ['Hipotiroidismo', 'Diabetes mellitus tipo 2', 'Obesidad', 'Nódulo tiroideo'],
        'ESP0013' => ['Gastritis crónica', 'Reflujo gastroesofágico', 'Síndrome de colon irritable', 'Control post endoscopía'],
        'ESP0014' => ['Hernia inguinal', 'Colelitiasis', 'Control postoperatorio', 'Lipoma'],
    ];

    private const PLANES = [
        'Tratamiento indicado y control en 30 días',
        'Exámenes de laboratorio y control con resultados',
        'Tratamiento sintomático, signos de alarma explicados',
        'Control en 15 días',
        'Seguimiento en la próxima consulta',
    ];

    public function run(): void
    {
        mt_srand(1983); // CIES fue fundado en 1987; cualquier semilla fija da los mismos datos

        $this->catalogos();
        $this->medicos();
        $pacientes = $this->pacientes();

        $yaHayHistorial = DB::table('CITA')->where('COD_EMP', 'EMP000009')->exists();
        if (! $yaHayHistorial) {
            $this->historialCitas($pacientes);
        }

        $this->estadoCamas();
    }

    // ------------------------------------------------------------------
    // 1. Especialidades, servicios y consultorios
    // ------------------------------------------------------------------
    private function catalogos(): void
    {
        foreach (self::ESPECIALIDADES as [$codEsp, $desEsp, $codSrv, $nomSrv, $precio]) {
            DB::table('ESPECIALIDAD')->insertOrIgnore(['COD_ESP' => $codEsp, 'DES_ESP' => $desEsp]);
            DB::table('SERVICIO')->insertOrIgnore([
                'COD_SRV' => $codSrv, 'NOM_SRV' => $nomSrv, 'PRE_SRV' => $precio, 'COD_CLA' => 'CLA0001',
            ]);
        }
        // Nombres oficiales de la clínica para el consultorio y el servicio que ya existían
        DB::table('CONSULTORIO')->where('COD_CON', 'CON001')->update(['NOM_CON' => 'Consultorio 1 · Med. General']);

        foreach (self::CONSULTORIOS as [$cod, $nom, $ubi]) {
            DB::table('CONSULTORIO')->insertOrIgnore(['COD_CON' => $cod, 'NOM_CON' => $nom, 'UBI_CON' => $ubi]);
        }
    }

    // ------------------------------------------------------------------
    // 2. Médicos, cuentas y horarios
    // ------------------------------------------------------------------
    private function medicos(): void
    {
        // El médico de prueba (EMP000003) pasa a tener un nombre real dentro de la clínica
        DB::table('EMPLEADO')->where('COD_EMP', 'EMP000003')->where('APE_EMP', 'Prueba')
            ->update(['NOM_EMP' => 'Carlos', 'APE_EMP' => 'Fernández Copa', 'MAT_EMP' => 'MP-F-2034', 'COR_EMP' => 'medico@cies.test']);

        $siguienteHorario = (int) DB::table('HORARIO_MEDICO')->max('id') + 1;

        foreach (self::MEDICOS as $m) {
            [$codEmp, $ci, $tit, $nom, $ape, $sexo, $codEsp, $turno, $codCon, $mat, $sabado] = $m;

            if (DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->exists()) {
                continue;
            }

            $usuario = $this->usuarioDe($nom, $ape);

            DB::table('EMPLEADO')->insert([
                'COD_EMP'    => $codEmp,
                'CI_EMP'     => $ci,
                'NOM_EMP'    => $nom,
                'APE_EMP'    => $ape,
                'TIT_EMP'    => $tit,
                'SEX_EMP'    => $sexo,
                'FECNAC_EMP' => sprintf('%d-%02d-%02d', 1968 + mt_rand(0, 20), mt_rand(1, 12), mt_rand(1, 28)),
                'TEL_EMP'    => (string) (76500000 + (int) substr($ci, -5)),
                'COR_EMP'    => $usuario . '@cies.test',
                'DIR_EMP'    => 'La Paz',
                'MAT_EMP'    => $mat,
                'FECING_EMP' => sprintf('%d-%02d-01', 2015 + mt_rand(0, 9), mt_rand(1, 12)),
                'TURNO_EMP'  => $turno,
                'COD_CAR'    => 'CAR0003',
                'COD_ESP'    => $codEsp,
            ]);

            if (! DB::table('USUARIO')->where('USR_USR', $usuario)->exists()) {
                DB::table('USUARIO')->insert([
                    'COD_USR' => sprintf('USR%05d', $this->siguienteNumero('USUARIO', 'COD_USR')),
                    'USR_USR' => $usuario,
                    'CON_USR' => Hash::make($usuario . '123', ['rounds' => 10]),
                    'COD_ROL' => 'ROL003',
                    'COD_EMP' => $codEmp,
                    'COD_PAC' => null,
                ]);
            }

            [$ini, $fin] = $turno === 'Mañana' ? ['08:00:00', '12:00:00'] : ['14:00:00', '18:00:00'];
            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
            if ($sabado) {
                $dias[] = 'Sábado';
            }
            foreach ($dias as $dia) {
                DB::table('HORARIO_MEDICO')->insert([
                    'COD_HOR'    => sprintf('HOR%05d', $siguienteHorario++),
                    'DIA_HOR'    => $dia,
                    'HORINI_HOR' => $ini,
                    'HORFIN_HOR' => $fin,
                    'COD_EMP'    => $codEmp,
                    'COD_CON'    => $codCon,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // 3. Pacientes (registrados como en recepción)
    // ------------------------------------------------------------------
    private function pacientes(): array
    {
        $nuevos = [
            // Adultas
            ['Valeria', 'Quispe Mamani', 'F', 1994], ['Roxana', 'Choque Apaza', 'F', 1988], ['Silvia', 'Huanca Ticona', 'F', 1979],
            ['Daniela', 'Flores Rojas', 'F', 1999], ['Marisol', 'Limachi Cruz', 'F', 1991], ['Jhanet', 'Condori Laura', 'F', 1985],
            ['Gladys', 'Poma Vargas', 'F', 1972], ['Fabiola', 'Ramos Callisaya', 'F', 2001], ['Lucía', 'Torrez Mendoza', 'F', 1996],
            ['Noemí', 'Copa Alanoca', 'F', 1983], ['Wendy', 'Gutiérrez Nina', 'F', 2003], ['Ximena', 'Salinas Pérez', 'F', 1990],
            ['Beatriz', 'Mendoza Aruquipa', 'F', 1966], ['Karen', 'Villca Rocha', 'F', 1998], ['Patricia', 'Aguilar Chura', 'F', 1987],
            ['Miriam', 'Yujra Tarqui', 'F', 1993], ['Carmen', 'Rocha Machaca', 'F', 1958], ['Andrea', 'Mollinedo Paz', 'F', 2000],
            ['Estela', 'Mamani Coaquira', 'F', 1976], ['Shirley', 'Tola Calle', 'F', 1995],
            // Adultos
            ['Juan Carlos', 'Mamani Choque', 'M', 1975], ['René', 'Quispe Ramos', 'M', 1963], ['Freddy', 'Cruz Huanca', 'M', 1981],
            ['Wilson', 'Apaza Flores', 'M', 1958], ['Javier', 'Ticona Rojas', 'M', 1990], ['Edwin', 'Laura Poma', 'M', 1969],
            ['Alejandro', 'Vargas Nina', 'M', 1997], ['Rubén', 'Callisaya Copa', 'M', 1954], ['Henry', 'Alanoca Torrez', 'M', 1986],
            ['Gustavo', 'Chura Salinas', 'M', 1978], ['Miguel Ángel', 'Rocha Limachi', 'M', 1960], ['Ronald', 'Nina Gutiérrez', 'M', 1992],
            ['Víctor', 'Machaca Yujra', 'M', 1971],
            // Niñas y niños (los registra su madre, padre o tutor)
            ['Mateo', 'Quispe Mamani', 'M', 2021], ['Valentina', 'Choque Apaza', 'F', 2019], ['Santiago', 'Flores Rojas', 'M', 2023],
            ['Camila', 'Limachi Cruz', 'F', 2017], ['Thiago', 'Condori Laura', 'M', 2020], ['Luciana', 'Ramos Callisaya', 'F', 2024],
            ['Sebastián', 'Torrez Mendoza', 'M', 2015], ['Isabella', 'Copa Alanoca', 'F', 2022], ['Benjamín', 'Salinas Pérez', 'M', 2018],
            ['Abigail', 'Villca Rocha', 'F', 2016], ['Gael', 'Aguilar Chura', 'M', 2025],
        ];

        $calles = ['Av. 6 de Agosto', 'Calle Jaén', 'Av. Busch', 'Calle Illampu', 'Av. Arce', 'Calle Murillo', 'Av. Saavedra',
                   'Calle Yanacocha', 'Av. Buenos Aires', 'Calle Sagárnaga', 'Av. Tejada Sorzano', 'Calle Linares'];
        $zonas = ['Miraflores', 'Sopocachi', 'San Pedro', 'Villa Fátima', 'Obrajes', 'Calacoto', 'Max Paredes', 'El Alto, Villa Adela', 'Achumani', 'Tembladerani'];

        $ci = 6100417;
        foreach ($nuevos as $i => [$nom, $ape, $sexo, $anio]) {
            $ci += 7919 + $i * 13;
            $ciTxt = (string) $ci;
            if (DB::table('PACIENTE')->where('NOM_PAC', $nom)->where('APE_PAC', $ape)->exists()) {
                continue;
            }
            $codPac = sprintf('PAC%07d', (int) DB::table('PACIENTE')->max(DB::raw('CAST(SUBSTRING(COD_PAC, 4) AS UNSIGNED)')) + 1);
            $menor = $anio >= 2008;
            DB::table('PACIENTE')->insert([
                'COD_PAC'    => $codPac,
                'CI_PAC'     => $ciTxt,
                'NOM_PAC'    => $nom,
                'APE_PAC'    => $ape,
                'SEX_PAC'    => $sexo,
                'FECNAC_PAC' => sprintf('%d-%02d-%02d', $anio, mt_rand(1, 12), mt_rand(1, 28)),
                'ESTCIV_PAC' => $menor ? ($sexo === 'F' ? 'Soltera' : 'Soltero') : (mt_rand(0, 2) ? ($sexo === 'F' ? 'Casada' : 'Casado') : ($sexo === 'F' ? 'Soltera' : 'Soltero')),
                'TEL_PAC'    => (string) (60000000 + mt_rand(1000000, 9999999)),
                'COR_PAC'    => strtolower($this->sinTildes(explode(' ', $nom)[0] . '.' . explode(' ', $ape)[0])) . ($i + 11) . '@gmail.com',
                'DIR_PAC'    => $calles[$i % count($calles)] . ' #' . mt_rand(100, 2500) . ', ' . $zonas[$i % count($zonas)],
            ]);

            if (! DB::table('USUARIO')->where('USR_USR', $ciTxt)->exists()) {
                DB::table('USUARIO')->insert([
                    'COD_USR'    => sprintf('USR%05d', $this->siguienteNumero('USUARIO', 'COD_USR')),
                    'USR_USR'    => $ciTxt,
                    'CON_USR'    => Hash::make('Cies' . $ciTxt, ['rounds' => 10]),
                    'EST_USR'    => 'Activo',
                    'CAMCON_USR' => 1,
                    'COD_ROL'    => 'ROL006',
                    'COD_EMP'    => null,
                    'COD_PAC'    => $codPac,
                ]);
            }
        }

        // Grupos de pacientes para que cada especialidad atienda a quien corresponde
        $hoy = Carbon::today();
        $grupos = ['nino' => [], 'mujer' => [], 'hombre' => []];
        foreach (DB::table('PACIENTE')->orderBy('COD_PAC')->get(['COD_PAC', 'SEX_PAC', 'FECNAC_PAC']) as $p) {
            $edad = Carbon::parse($p->FECNAC_PAC)->diffInYears($hoy);
            $grupo = $edad < 15 ? 'nino' : ($p->SEX_PAC === 'F' ? 'mujer' : 'hombre');
            // Peso de visitas: unos pocos pacientes vienen muy seguido (control prenatal, crónicos)
            $grupos[$grupo][] = $p->COD_PAC;
        }

        $pesos = [];
        foreach ($grupos as $g => $lista) {
            $lista = array_values($lista);
            shuffle($lista);
            // La paciente de prueba (PAC0000001) es de las que más visita la clínica
            usort($lista, fn ($a, $b) => ($b === 'PAC0000001') <=> ($a === 'PAC0000001'));
            $acum = 0.0;
            $pesos[$g] = [];
            foreach ($lista as $rank => $codPac) {
                $acum += 1 / pow($rank + 1, 0.75);
                $pesos[$g][] = [$acum, $codPac];
            }
        }
        return $pesos;
    }

    // ------------------------------------------------------------------
    // 4. Historial de citas (12 meses atrás y 2 semanas adelante)
    // ------------------------------------------------------------------
    private function historialCitas(array $pesos): void
    {
        $especialidades = [];
        foreach (self::ESPECIALIDADES as [$codEsp, , $codSrv, , $precio, $demanda, $estacional]) {
            $especialidades[$codEsp] = compact('codSrv', 'precio', 'demanda', 'estacional');
        }

        $medicos = DB::table('EMPLEADO')->whereNotNull('COD_ESP')->get(['COD_EMP', 'COD_ESP'])->keyBy('COD_EMP');
        $horarios = DB::table('HORARIO_MEDICO')->get()->groupBy('COD_EMP');

        // Historias clínicas: una por paciente
        $historias = DB::table('HISTORIA_CLINICA')->pluck('COD_HIS', 'COD_PAC')->all();
        $nHis = (int) DB::table('HISTORIA_CLINICA')->max('id');
        foreach (DB::table('PACIENTE')->pluck('COD_PAC') as $codPac) {
            if (! isset($historias[$codPac])) {
                $nHis++;
                DB::table('HISTORIA_CLINICA')->insert([
                    'COD_HIS' => sprintf('HIS%06d', $nHis), 'NRO_HIS' => sprintf('HC-%06d', $nHis),
                    'FECAPE_HIS' => Carbon::today()->subYear()->subDays(mt_rand(1, 200))->toDateString(),
                    'EST_HIS' => 'Activa', 'NOTADM_HIS' => null, 'COD_PAC' => $codPac,
                ]);
                $historias[$codPac] = sprintf('HIS%06d', $nHis);
            }
        }

        // Horas ya tomadas (citas existentes) para no duplicar
        $tomadas = [];
        foreach (DB::table('CITA')->get(['COD_EMP', 'FEC_CIT', 'HOR_CIT']) as $c) {
            $tomadas[$c->COD_EMP . '|' . $c->FEC_CIT . '|' . substr($c->HOR_CIT, 0, 5)] = true;
        }

        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $ahora = Carbon::now();
        $desde = Carbon::today()->subMonths(12)->startOfMonth();
        $hasta = Carbon::today()->addDays(14);
        $totalDias = $desde->diffInDays($hasta);

        $nCit = (int) DB::table('CITA')->max('id');
        $nCns = (int) DB::table('CONSULTA')->max('id');
        $nPag = (int) DB::table('PAGO')->max('id');
        $anticipo = (int) config('cies.anticipo_porcentaje', 30) / 100;

        $citas = $consultas = $pagos = [];

        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            if ($d->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }
            $dia = $diasSemana[$d->dayOfWeek];
            $mes = $d->month;
            // Invierno (mayo–agosto): más consultas respiratorias
            $invierno = in_array($mes, [5, 6, 7, 8]) ? 1.35 : (in_array($mes, [4, 9]) ? 1.12 : 0.95);
            // La clínica crece un poco a lo largo del año
            $tendencia = 0.88 + 0.22 * ($desde->diffInDays($d) / max(1, $totalDias));
            $semana = match ($d->dayOfWeek) { 1 => 1.15, 6 => 0.8, 5 => 0.95, default => 1.0 };
            $fecha = $d->toDateString();

            foreach ($medicos as $codEmp => $med) {
                $esp = $especialidades[$med->COD_ESP] ?? null;
                if (! $esp) {
                    continue;
                }
                foreach (($horarios[$codEmp] ?? collect())->where('DIA_HOR', $dia) as $bloque) {
                    $slots = [];
                    $t = Carbon::parse($fecha . ' ' . $bloque->HORINI_HOR);
                    $fin = Carbon::parse($fecha . ' ' . $bloque->HORFIN_HOR);
                    while ($t->copy()->addMinutes(30)->lte($fin)) {
                        $slots[] = $t->format('H:i');
                        $t->addMinutes(30);
                    }
                    $esperado = $esp['demanda'] * ($esp['estacional'] ? $invierno : 1.0) * $tendencia * $semana;
                    $n = max(0, min(count($slots), (int) round($esperado * (0.65 + mt_rand(0, 70) / 100))));
                    shuffle($slots);
                    foreach (array_slice($slots, 0, $n) as $hora) {
                        $clave = $codEmp . '|' . $fecha . '|' . $hora;
                        if (isset($tomadas[$clave])) {
                            continue;
                        }
                        $tomadas[$clave] = true;

                        $grupo = match ($med->COD_ESP) {
                            'ESP0003' => 'nino',
                            'ESP0002' => 'mujer',
                            'ESP0006' => mt_rand(1, 100) <= 85 ? 'hombre' : 'mujer',
                            'ESP0001', 'ESP0005', 'ESP0010', 'ESP0011' => ['nino', 'mujer', 'mujer', 'hombre', 'hombre'][mt_rand(0, 4)],
                            default => mt_rand(0, 1) ? 'mujer' : 'hombre',
                        };
                        $codPac = $this->elegir($pesos[$grupo]);

                        $inicio = Carbon::parse($fecha . ' ' . $hora);
                        $estado = $this->estadoCita($inicio, $ahora);
                        $diag = self::DIAGNOSTICOS[$med->COD_ESP][mt_rand(0, count(self::DIAGNOSTICOS[$med->COD_ESP]) - 1)];

                        $nCit++;
                        $codCit = sprintf('CIT%07d', $nCit);
                        $citas[] = [
                            'COD_CIT' => $codCit, 'FEC_CIT' => $fecha, 'HOR_CIT' => $hora . ':00', 'DUR_CIT' => 30,
                            'EST_CIT' => $estado,
                            'MOT_CIT' => $estado === 'Atendida' || mt_rand(0, 1) ? $diag : 'Consulta de control',
                            'FECEXP_CIT' => $estado === 'Expirada' ? $inicio->copy()->subDays(3)->toDateTimeString() : null,
                            'COD_PAC' => $codPac, 'COD_EMP' => $codEmp,
                            'COD_CON' => $bloque->COD_CON, 'COD_SRV' => $esp['codSrv'],
                        ];

                        $precio = (float) $esp['precio'];
                        $adelanto = round($precio * $anticipo, 2);
                        // Anticipo pagado en línea (RF008) para las citas que llegaron a confirmarse
                        if (in_array($estado, ['Atendida', 'Confirmada', 'No asistio'])) {
                            $nPag++;
                            $pagos[] = [
                                'COD_PAG' => sprintf('PAG%07d', $nPag),
                                'FEC_PAG' => $inicio->copy()->subDays(mt_rand(1, 6))->setTime(mt_rand(8, 21), mt_rand(0, 59))->toDateTimeString(),
                                'MON_PAG' => $adelanto, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado',
                                'COD_CIT' => $codCit, 'COD_MET' => mt_rand(0, 3) ? 'MET0002' : 'MET0003',
                                'COD_OTP' => null, 'COD_CAJ' => null, 'FIR_PAG' => null,
                            ];
                        }
                        // Saldo cobrado en caja el día de la atención (RN24)
                        if ($estado === 'Atendida') {
                            $nPag++;
                            $pagos[] = [
                                'COD_PAG' => sprintf('PAG%07d', $nPag),
                                'FEC_PAG' => $inicio->copy()->addMinutes(35)->toDateTimeString(),
                                'MON_PAG' => round($precio - $adelanto, 2), 'TIP_PAG' => 'Total', 'EST_PAG' => 'Confirmado',
                                'COD_CIT' => $codCit, 'COD_MET' => ['MET0001', 'MET0001', 'MET0002', 'MET0003'][mt_rand(0, 3)],
                                'COD_OTP' => null, 'COD_CAJ' => null, 'FIR_PAG' => null,
                            ];

                            $nCns++;
                            $consultas[] = [
                                'COD_CNS' => sprintf('CNS%07d', $nCns),
                                'FEC_CNS' => $inicio->copy()->addMinutes(mt_rand(0, 10))->toDateTimeString(),
                                'DIA_CNS' => $diag,
                                'PLA_CNS' => self::PLANES[mt_rand(0, count(self::PLANES) - 1)],
                                'COD_CIT' => $codCit, 'COD_HIS' => $historias[$codPac], 'COD_EMP' => $codEmp,
                            ];
                        }
                    }
                }
            }

            // Inserta por lotes para no acumular demasiado en memoria
            if (count($citas) >= 600) {
                $this->volcar($citas, $consultas, $pagos);
            }
        }
        $this->volcar($citas, $consultas, $pagos);
    }

    private function volcar(array &$citas, array &$consultas, array &$pagos): void
    {
        foreach (array_chunk($citas, 300) as $l) {
            DB::table('CITA')->insert($l);
        }
        foreach (array_chunk($consultas, 300) as $l) {
            DB::table('CONSULTA')->insert($l);
        }
        foreach (array_chunk($pagos, 300) as $l) {
            DB::table('PAGO')->insert($l);
        }
        $citas = $consultas = $pagos = [];
    }

    private function estadoCita(Carbon $inicio, Carbon $ahora): string
    {
        $r = mt_rand(1, 100);
        if ($inicio->lt($ahora)) {
            return match (true) {
                $r <= 79 => 'Atendida',
                $r <= 87 => 'No asistio',
                $r <= 96 => 'Cancelada',
                default  => 'Expirada',
            };
        }
        return $r <= 90 ? 'Confirmada' : 'Cancelada';
    }

    /** Elige un paciente según su peso de visitas (unos pocos vienen muy seguido). */
    private function elegir(array $acumulados): string
    {
        $total = end($acumulados)[0];
        $x = mt_rand() / mt_getrandmax() * $total;
        foreach ($acumulados as [$hasta, $codPac]) {
            if ($x <= $hasta) {
                return $codPac;
            }
        }
        return end($acumulados)[1];
    }

    // ------------------------------------------------------------------
    // 5. Camas de ejemplo: aislamiento, ropa sucia y desinfección terminal
    // ------------------------------------------------------------------
    private function estadoCamas(): void
    {
        try {
            if (DB::table('LIMPIEZA_CAMA')->exists()) {
                return;
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return; // falta el script 04_ajustes_defensa.sql
        }

        // Un paciente internado con aislamiento respiratorio
        $activa = DB::table('INTERNACION')->where('EST_INT', 'Internado')->orderBy('FECING_INT')->first();
        if ($activa) {
            DB::table('INTERNACION')->where('COD_INT', $activa->COD_INT)
                ->update(['AIS_INT' => 'Respiratorio (COVID-19)']);
        }

        $libres = DB::table('CAMA')->where('EST_CAM', 'Disponible')->orderByDesc('NRO_CAM')->limit(2)->pluck('COD_CAM')->all();
        $ahora = Carbon::now();

        if (isset($libres[0])) {
            DB::table('LIMPIEZA_CAMA')->insert([
                'COD_LIM' => 'LIM0000001', 'TIP_LIM' => 'Rutinaria', 'MOT_LIM' => 'Ropa de cama sucia',
                'FECSOL_LIM' => $ahora->copy()->subMinutes(40)->toDateTimeString(), 'FECINI_LIM' => null, 'FECFIN_LIM' => null,
                'EST_LIM' => 'Pendiente', 'OBS_LIM' => 'Reportado en la ronda de enfermería',
                'COD_CAM' => $libres[0], 'COD_INT' => null, 'COD_EMP' => 'EMP000004', 'EJE_EMP' => null,
            ]);
            DB::table('CAMA')->where('COD_CAM', $libres[0])->update(['EST_CAM' => 'Limpieza']);
        }
        if (isset($libres[1])) {
            DB::table('LIMPIEZA_CAMA')->insert([
                'COD_LIM' => 'LIM0000002', 'TIP_LIM' => 'Terminal', 'MOT_LIM' => 'Paciente con aislamiento: Respiratorio (COVID-19)',
                'FECSOL_LIM' => $ahora->copy()->subHours(3)->toDateTimeString(),
                'FECINI_LIM' => $ahora->copy()->subHours(2)->toDateTimeString(), 'FECFIN_LIM' => null,
                'EST_LIM' => 'En proceso', 'SAB_LIM' => 1, 'SUP_LIM' => 1, 'COL_LIM' => 0, 'VEN_LIM' => 0,
                'OBS_LIM' => 'Paciente trasladado; ropa de cama embolsada como ropa contaminada',
                'COD_CAM' => $libres[1], 'COD_INT' => null, 'COD_EMP' => 'EMP000004', 'EJE_EMP' => 'EMP000004',
            ]);
            DB::table('CAMA')->where('COD_CAM', $libres[1])->update(['EST_CAM' => 'Limpieza']);
        }
    }

    // ------------------------------------------------------------------
    private function usuarioDe(string $nombre, string $apellidos): string
    {
        $u = strtolower($this->sinTildes(mb_substr($nombre, 0, 1) . explode(' ', $apellidos)[0]));
        return preg_replace('/[^a-z]/', '', $u);
    }

    private function siguienteNumero(string $tabla, string $columna): int
    {
        return (int) DB::table($tabla)->max(DB::raw("CAST(SUBSTRING({$columna}, 4) AS UNSIGNED)")) + 1;
    }

    private function sinTildes(string $s): string
    {
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E',
                          'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }
}
