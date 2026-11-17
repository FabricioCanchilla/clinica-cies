<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Los 9 roles
        DB::table('ROL')->insertOrIgnore([
            ['COD_ROL' => 'ROL001', 'DES_ROL' => 'Administrador'],
            ['COD_ROL' => 'ROL002', 'DES_ROL' => 'Recepcionista'],
            ['COD_ROL' => 'ROL003', 'DES_ROL' => 'Medico'],
            ['COD_ROL' => 'ROL004', 'DES_ROL' => 'Enfermeria'],
            ['COD_ROL' => 'ROL005', 'DES_ROL' => 'Caja'],
            ['COD_ROL' => 'ROL006', 'DES_ROL' => 'Paciente'],
            ['COD_ROL' => 'ROL007', 'DES_ROL' => 'Laboratorio'],
            ['COD_ROL' => 'ROL008', 'DES_ROL' => 'Farmacia'],
            ['COD_ROL' => 'ROL009', 'DES_ROL' => 'Auditor'],
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
                // 5. Dominios de auditoría
        DB::table('DOMINIO_AUD')->insertOrIgnore([
            ['COD_DOM' => 'DOM01', 'DES_DOM' => 'Usuarios y accesos'],
            ['COD_DOM' => 'DOM02', 'DES_DOM' => 'Seguridad y autenticación'],
            ['COD_DOM' => 'DOM03', 'DES_DOM' => 'Clínico'],
            ['COD_DOM' => 'DOM04', 'DES_DOM' => 'Financiero'],
        ]);

        // 6. Especialidades (el agendamiento filtra médicos por COD_ESP)
        DB::table('ESPECIALIDAD')->insertOrIgnore([
            ['COD_ESP' => 'ESP0001', 'DES_ESP' => 'Medicina General'],
            ['COD_ESP' => 'ESP0002', 'DES_ESP' => 'Ginecología y Obstetricia'],
            ['COD_ESP' => 'ESP0003', 'DES_ESP' => 'Pediatría'],
            ['COD_ESP' => 'ESP0004', 'DES_ESP' => 'Psicología'],
            ['COD_ESP' => 'ESP0005', 'DES_ESP' => 'Odontología'],
            ['COD_ESP' => 'ESP0006', 'DES_ESP' => 'Urología'],
        ]);

        // 7. Métodos de pago
        DB::table('METODO_PAGO')->insertOrIgnore([
            ['COD_MET' => 'MET0001', 'DES_MET' => 'Efectivo'],
            ['COD_MET' => 'MET0002', 'DES_MET' => 'QR Simple'],
            ['COD_MET' => 'MET0003', 'DES_MET' => 'Tarjeta de débito/crédito'],
            ['COD_MET' => 'MET0004', 'DES_MET' => 'Transferencia bancaria'],
        ]);

        // 8. Clasificaciones, servicios (con precio) y consultorio
        DB::table('CLASIFICACION_SERVICIO')->insertOrIgnore([
            ['COD_CLA' => 'CLA0001', 'DES_CLA' => 'Consulta'],
            ['COD_CLA' => 'CLA0002', 'DES_CLA' => 'Laboratorio'],
        ]);

        DB::table('SERVICIO')->insertOrIgnore([
            ['COD_SRV' => 'SRV00001', 'NOM_SRV' => 'Consulta general',   'PRE_SRV' => 80.00, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV00002', 'NOM_SRV' => 'Hemograma completo', 'PRE_SRV' => 60.00, 'COD_CLA' => 'CLA0002'],
            ['COD_SRV' => 'SRV00003', 'NOM_SRV' => 'Glucosa en ayunas',  'PRE_SRV' => 25.00, 'COD_CLA' => 'CLA0002'],
            ['COD_SRV' => 'SRV00004', 'NOM_SRV' => 'Perfil lipidico',    'PRE_SRV' => 90.00, 'COD_CLA' => 'CLA0002'],
        ]);

        DB::table('CONSULTORIO')->insertOrIgnore([
            'COD_CON' => 'CON001', 'NOM_CON' => 'Consultorio 1', 'UBI_CON' => 'Planta baja',
        ]);

        // Aseguradoras para la validación de seguros en recepción (RF038)
        DB::table('ASEGURADORA')->insertOrIgnore([
            ['COD_ASE' => 'ASE0001', 'DES_ASE' => 'Seguro privado de prueba A'],
            ['COD_ASE' => 'ASE0002', 'DES_ASE' => 'Seguro privado de prueba B'],
            ['COD_ASE' => 'ASE0003', 'DES_ASE' => 'Seguro de convenio institucional'],
        ]);
    }
}