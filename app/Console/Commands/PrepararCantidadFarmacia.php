<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararCantidadFarmacia extends Command
{
    protected $signature = 'farmacia:preparar-cantidad';

    protected $description = 'Prepara el registro de cantidades confirmadas de recetas';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/07_cantidad_farmacia.sql')));
        $this->info('Registro de cantidades preparado.');
        return self::SUCCESS;
    }
}
