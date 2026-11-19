<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatosInicialesSeeder::class,   // roles, admin, dominios, especialidades, métodos de pago, servicios, consultorio
            UsuariosPruebaSeeder::class,   // empleados de prueba (médico con especialidad y horario) y paciente
            DatosClinicosPruebaSeeder::class,
            HistorialInternacionSeeder::class,   // RF050: sala, camas e historial de internaciones de demostración
            DemostracionClinicaSeeder::class,    // especialidades, 17 médicos, pacientes e historial de citas para los reportes
        ]);
    }
}