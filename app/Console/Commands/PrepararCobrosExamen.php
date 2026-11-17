<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararCobrosExamen extends Command
{
    protected $signature = 'caja:preparar-examenes';
    protected $description = 'Prepara los cobros independientes de exámenes';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/09_cobros_examen.sql')));
        DB::unprepared(file_get_contents(database_path('sql/10_cobros_medicamentos.sql')));
        $this->info('Cobros de exámenes preparados.');
        return self::SUCCESS;
    }
}
