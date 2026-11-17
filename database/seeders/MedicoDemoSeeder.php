<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Datos ficticios de demostración; se carga explícitamente y nunca reemplaza pacientes existentes. */
class MedicoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $medico = DB::table('USUARIO')->where('USR_USR', 'medico')->where('COD_ROL', 'ROL003')->value('COD_EMP');
        $consultorio = DB::table('HORARIO_MEDICO')->where('COD_EMP', $medico)->value('COD_CON')
            ?? DB::table('CONSULTORIO')->value('COD_CON');
        $servicio = DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
            ->where('c.DES_CLA', 'Consulta')->value('s.COD_SRV');
        if (! $medico || ! $consultorio || ! $servicio) {
            throw new RuntimeException('Se necesita la cuenta medico, un consultorio y un servicio de consulta.');
        }
        $nombres = ['Valeria', 'Mateo', 'Camila', 'Diego', 'Luciana', 'Gabriel', 'Sofía', 'Andrés'];
        $apellidos = ['Rojas', 'Mamani', 'Vargas', 'Flores', 'Quispe', 'Arce', 'Paredes', 'Salazar'];
        DB::transaction(function () use ($medico, $consultorio, $servicio, $nombres, $apellidos) {
            DB::table('EMPLEADO')->where('COD_EMP', $medico)->lockForUpdate()->first();
            foreach ($nombres as $i => $nombre) {
                $numero = $i + 1;
                $paciente = sprintf('PACDEMO%03d', $numero);
                $historia = sprintf('HISDM%04d', $numero);
                $existente = DB::table('PACIENTE')->where('COD_PAC', $paciente)->first();
                if ($existente && $existente->NOM_PAC !== $nombre.' (Demo)') {
                    throw new RuntimeException('El código de demostración ya pertenece a otro paciente: '.$paciente);
                }
                DB::table('PACIENTE')->insertOrIgnore([
                    'COD_PAC' => $paciente, 'CI_PAC' => (string) (99000100 + $numero), 'NOM_PAC' => $nombre.' (Demo)',
                    'APE_PAC' => $apellidos[$i], 'SEX_PAC' => $i % 2 === 0 ? 'F' : 'M',
                    'FECNAC_PAC' => sprintf('%d-05-12', 1987 + $i * 3), 'ESTCIV_PAC' => 'Soltero',
                    'TEL_PAC' => (string) (80000100 + $numero), 'COR_PAC' => 'paciente'.$numero.'@demo.invalid',
                    'DIR_PAC' => 'Dirección ficticia '.$numero.' · Demostración local',
                ]);
                DB::table('HISTORIA_CLINICA')->insertOrIgnore([
                    'COD_HIS' => $historia, 'NRO_HIS' => sprintf('DEMO%06d', $numero),
                    'FECAPE_HIS' => today()->subDays(15)->toDateString(), 'EST_HIS' => 'Activa', 'COD_PAC' => $paciente,
                    'NOTADM_HIS' => 'PACIENTE FICTICIO · Solo demostración local. No usar para atención real.',
                ]);
                DB::table('ANTECEDENTE')->insertOrIgnore([
                    'COD_ANT' => sprintf('ANTDM%03d', $numero), 'TIP_ANT' => 'Personal',
                    'DES_ANT' => 'Antecedente de ejemplo para comprobar la visualización del expediente. Datos ficticios.', 'COD_HIS' => $historia,
                ]);
                if ($numero === 1 || $numero === 3) {
                    DB::table('ALERGIA')->insertOrIgnore([
                        'COD_ALE' => sprintf('ALEDM%03d', $numero), 'DES_ALE' => 'Penicilina (dato ficticio de prueba)',
                        'SEV_ALE' => $numero === 1 ? 'Grave' : 'Moderada', 'COD_HIS' => $historia,
                    ]);
                }
                DB::table('NOTA_EVOLUCION')->insertOrIgnore([
                    'COD_NEV' => sprintf('NEVDM%05d', $numero), 'FEC_NEV' => now()->subDays(7),
                    'TXT_NEV' => 'Nota ficticia de evolución. Este registro permite probar la historia clínica y sus adendas.',
                    'COD_HIS' => $historia, 'COD_EMP' => $medico,
                ]);
                DB::table('SIGNO_VITAL')->insertOrIgnore([
                    'COD_SIV' => sprintf('SIVDM%05d', $numero), 'FEC_SIV' => now()->subDays(7),
                    'SIS_SIV' => 120, 'DIA_SIV' => 80, 'PUL_SIV' => 72, 'TEM_SIV' => 36.5,
                    'COD_PAC' => $paciente, 'COD_EMP' => $medico,
                ]);
                $citaAnterior = sprintf('CITDM%05d', $numero);
                DB::table('CITA')->insertOrIgnore([
                    'COD_CIT' => $citaAnterior, 'FEC_CIT' => today()->subDays(7)->toDateString(), 'HOR_CIT' => '08:00:00', 'DUR_CIT' => 30,
                    'EST_CIT' => 'Atendida', 'MOT_CIT' => 'Consulta anterior ficticia', 'COD_PAC' => $paciente,
                    'COD_EMP' => $medico, 'COD_CON' => $consultorio, 'COD_SRV' => $servicio,
                ]);
                DB::table('CONSULTA')->insertOrIgnore([
                    'COD_CNS' => sprintf('CNSDM%05d', $numero), 'FEC_CNS' => now()->subDays(7)->setTime(8, 0),
                    'DIA_CNS' => 'Registro ficticio de consulta de demostración',
                    'PLA_CNS' => 'Plan de ejemplo. Estos datos no corresponden a una atención real.',
                    'COD_CIT' => $citaAnterior, 'COD_HIS' => $historia, 'COD_EMP' => $medico,
                ]);
            }
            $agenda = [
                [0, 1, '08:30', 'Primera consulta (demo)'], [0, 2, '09:15', 'Control de rutina (demo)'],
                [0, 3, '10:00', 'Revisión de resultados (demo)'], [0, 4, '11:30', 'Seguimiento (demo)'],
                [1, 5, '09:00', 'Control preventivo (demo)'], [1, 6, '10:30', 'Primera consulta (demo)'],
                [2, 7, '08:45', 'Revisión general (demo)'], [2, 8, '11:00', 'Seguimiento (demo)'],
                [4, 1, '09:00', 'Control programado (demo)'], [7, 3, '10:00', 'Revisión (demo)'],
                [10, 5, '09:30', 'Control de rutina (demo)'], [14, 7, '11:00', 'Seguimiento (demo)'],
            ];
            foreach ($agenda as $indice => [$dias, $numero, $hora, $motivo]) {
                $fecha = today()->addDays($dias);
                $codigo = 'CD'.$fecha->format('ymd').sprintf('%02d', $indice);
                DB::table('CITA')->insertOrIgnore([
                    'COD_CIT' => $codigo, 'FEC_CIT' => $fecha->toDateString(), 'HOR_CIT' => $hora.':00', 'DUR_CIT' => 30,
                    'EST_CIT' => 'Confirmada', 'MOT_CIT' => $motivo, 'COD_PAC' => sprintf('PACDEMO%03d', $numero),
                    'COD_EMP' => $medico, 'COD_CON' => $consultorio, 'COD_SRV' => $servicio,
                ]);
                if ($indice === 1) {
                    DB::table('MEDICO_ATENCION')->insertOrIgnore(['COD_CIT' => $codigo, 'ESTADO' => 'En espera', 'LLEGADA' => $fecha->copy()->setTime(9, 0)]);
                }
            }
        });
        $this->command?->info('8 pacientes ficticios preparados, con 12 citas desde hoy y 8 consultas anteriores. No se reemplazaron registros existentes.');
    }
}
