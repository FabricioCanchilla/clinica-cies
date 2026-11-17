<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class Cie10Seeder extends Seeder
{
    public function run(): void
    {
        if (Artisan::call('medico:importar-cie10', ['archivo' => database_path('sql/cie10.csv')]) !== 0) {
            throw new RuntimeException(Artisan::output());
        }
    }
}
