<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // Cargos de los empleados
        DB::table('CARGO')->insertOrIgnore([
            ['COD_CAR' => 'CAR0002', 'DES_CAR' => 'Recepcionista'],
            ['COD_CAR' => 'CAR0003', 'DES_CAR' => 'Medico'],
            ['COD_CAR' => 'CAR0004', 'DES_CAR' => 'Enfermero'],
            ['COD_CAR' => 'CAR0005', 'DES_CAR' => 'Cajero'],
            ['COD_CAR' => 'CAR0006', 'DES_CAR' => 'Laboratorista'],
            ['COD_CAR' => 'CAR0007', 'DES_CAR' => 'Farmaceutico'],
            ['COD_CAR' => 'CAR0008', 'DES_CAR' => 'Auditor'],
        ]);

        // [cod_emp, ci, nombre, titulo, cargo, usuario, cod_usr, rol, especialidad]
        $empleados = [
            ['EMP000002', '2000001', 'Rosa',   'Lic', 'CAR0002', 'recepcion',  'USR00002', 'ROL002'],
            ['EMP000003', '2000002', 'Carlos', 'Dr',  'CAR0003', 'medico',     'USR00003', 'ROL003', 'ESP0001'],
            ['EMP000004', '2000003', 'Lucia',  'Lic', 'CAR0004', 'enfermeria', 'USR00004', 'ROL004'],
            ['EMP000005', '2000004', 'Pedro',  'Lic', 'CAR0005', 'caja',       'USR00005', 'ROL005'],
            ['EMP000006', '2000005', 'Sofia',  'Lic', 'CAR0006', 'laboratorio', 'USR00007', 'ROL007'],
            ['EMP000007', '2000006', 'Marco',  'Lic', 'CAR0007', 'farmacia',    'USR00008', 'ROL008'],
            ['EMP000008', '2000007', 'Elena',  'Lic', 'CAR0008', 'auditor',     'USR00009', 'ROL009'],
        ];

        foreach ($empleados as $e) {
            [$codEmp, $ci, $nombre, $titulo, $cargo, $usr, $codUsr, $rol] = $e;
            $codEsp = $e[8] ?? null;
            DB::table('EMPLEADO')->insertOrIgnore([
                'COD_EMP'    => $codEmp,
                'CI_EMP'     => $ci,
                'NOM_EMP'    => $nombre,
                'APE_EMP'    => 'Prueba',
                'TIT_EMP'    => $titulo,
                'SEX_EMP'    => 'M',
                'FECNAC_EMP' => '1995-01-01',
                'TEL_EMP'    => '7000000' . substr($ci, -1),
                'COR_EMP'    => "$usr@cies.test",
                'DIR_EMP'    => 'La Paz',
                'MAT_EMP'    => null,
                'FECING_EMP' => '2026-01-01',
                'TURNO_EMP'  => 'Mañana',
                'COD_CAR'    => $cargo,
                'COD_ESP'    => $codEsp,
            ]);

            DB::table('USUARIO')->insertOrIgnore([
                'COD_USR' => $codUsr,
                'USR_USR' => $usr,
                'CON_USR' => Hash::make($usr . '123'),
                'COD_ROL' => $rol,
                'COD_EMP' => $codEmp,
                'COD_PAC' => null,
            ]);
        }

        // Horario del médico de prueba (EMP000003, Medicina General) en CON001
        $bloques = [['08:00:00', '12:00:00'], ['14:00:00', '18:00:00']];
        $n = 1;
        foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] as $dia) {
            foreach ($bloques as [$ini, $fin]) {
                if ($dia === 'Sábado' && $ini !== '08:00:00') {
                    continue; // sábado solo mañana
                }
                DB::table('HORARIO_MEDICO')->insertOrIgnore([
                    'COD_HOR'    => sprintf('HOR%05d', $n++),
                    'DIA_HOR'    => $dia,
                    'HORINI_HOR' => $ini,
                    'HORFIN_HOR' => $fin,
                    'COD_EMP'    => 'EMP000003',
                    'COD_CON'    => 'CON001',
                ]);
            }
        }

        // Paciente de prueba
        DB::table('PACIENTE')->insertOrIgnore([
            'COD_PAC'    => 'PAC0000001',
            'CI_PAC'     => '3000001',
            'NOM_PAC'    => 'Maria',
            'APE_PAC'    => 'Prueba',
            'SEX_PAC'    => 'F',
            'FECNAC_PAC' => '2000-05-10',
            'ESTCIV_PAC' => 'Soltera',
            'TEL_PAC'    => '71000001',
            'COR_PAC'    => 'paciente@cies.test',
            'DIR_PAC'    => 'La Paz',
        ]);

        DB::table('USUARIO')->insertOrIgnore([
            'COD_USR' => 'USR00006',
            'USR_USR' => 'paciente',
            'CON_USR' => Hash::make('paciente123'),
            'COD_ROL' => 'ROL006',
            'COD_EMP' => null,
            'COD_PAC' => 'PAC0000001',
        ]);
    }
}