<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Los 6 roles
        DB::table('ROL')->insertOrIgnore([
            ['COD_ROL' => 'ROL001', 'DES_ROL' => 'Administrador'],
            ['COD_ROL' => 'ROL002', 'DES_ROL' => 'Recepcionista'],
            ['COD_ROL' => 'ROL003', 'DES_ROL' => 'Medico'],
            ['COD_ROL' => 'ROL004', 'DES_ROL' => 'Enfermeria'],
            ['COD_ROL' => 'ROL005', 'DES_ROL' => 'Caja'],
            ['COD_ROL' => 'ROL006', 'DES_ROL' => 'Paciente'],
        ]);

        // 2. Un cargo (el empleado lo exige)
        DB::table('CARGO')->insertOrIgnore([
            'COD_CAR' => 'CAR0001',
            'DES_CAR' => 'Administrador',
        ]);

        // 3. Un empleado administrador
        DB::table('EMPLEADO')->insertOrIgnore([
            'COD_EMP'    => 'EMP000001',
            'CI_EMP'     => '1234567',
            'NOM_EMP'    => 'Admin',
            'APE_EMP'    => 'Sistema',
            'TIT_EMP'    => 'Ing',
            'SEX_EMP'    => 'M',
            'FECNAC_EMP' => '1995-01-01',
            'TEL_EMP'    => '70000000',
            'COR_EMP'    => 'admin@cies.test',
            'DIR_EMP'    => 'La Paz',
            'MAT_EMP'    => null,
            'FECING_EMP' => '2026-01-01',
            'TURNO_EMP'  => 'Mañana',
            'COD_CAR'    => 'CAR0001',
            'COD_ESP'    => null,
        ]);

        // 4. Su usuario (contraseña hasheada)
        DB::table('USUARIO')->insertOrIgnore([
            'COD_USR'    => 'USR00001',
            'USR_USR'    => 'admin',
            'CON_USR'    => Hash::make('admin123'),
            'COD_ROL'    => 'ROL001',
            'COD_EMP'    => 'EMP000001',
            'COD_PAC'    => null,
        ]);
    }
}