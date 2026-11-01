<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatosInicialesSeeder::class,   // roles, cargo, empleado y usuario admin
            UsuariosPruebaSeeder::class,   // recepcion, medico, enfermeria, caja y paciente
        ]);
    }
}