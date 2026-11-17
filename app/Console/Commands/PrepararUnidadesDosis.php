<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararUnidadesDosis extends Command
{
    protected $signature = 'enfermeria:preparar-unidades';
    protected $description = 'Prepara el registro de unidades de cada dosis';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/11_unidades_dosis.sql')));
        $this->info('Registro de unidades preparado.');
        return self::SUCCESS;
    }
}
