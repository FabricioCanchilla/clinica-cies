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
        ]);
    }
}