<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararModuloCaja extends Command
{
    protected $signature = 'caja:preparar';

    protected $description = 'Prepara el registro de descuentos de caja sin borrar información';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/05_modulo_caja.sql')));
        if (str_starts_with(DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario, 'root@')) {
            DB::unprepared("GRANT SELECT, INSERT ON `clinica_cies`.`CAJA_DETALLE_PAGO` TO 'cies_app'@'%'");
        }
        $this->info('Módulo de caja preparado.');
        return self::SUCCESS;
    }
}
