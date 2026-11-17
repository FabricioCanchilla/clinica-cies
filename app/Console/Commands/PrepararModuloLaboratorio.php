<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararModuloLaboratorio extends Command
{
    protected $signature = 'laboratorio:preparar';

    protected $description = 'Prepara el registro de muestras y validaciones sin borrar datos';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/06_modulo_laboratorio.sql')));
        $this->info('Módulo de laboratorio preparado.');
        return self::SUCCESS;
    }
}
