<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararModuloEnfermeria extends Command
{
    protected $signature = 'enfermeria:preparar';

    protected $description = 'Prepara las tablas adicionales de enfermería sin borrar datos';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/04_modulo_enfermeria.sql')));
        if (str_starts_with(DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario, 'root@')) {
            foreach (['ENFERMERIA_NOTA', 'ENFERMERIA_BALANCE', 'ENFERMERIA_FIRMA_DOSIS', 'ENFERMERIA_CIERRE_INSUMO'] as $tabla) {
                DB::unprepared("GRANT SELECT, INSERT ON `clinica_cies`.`{$tabla}` TO 'cies_app'@'%'");
            }
        }
        $this->info('Módulo de enfermería preparado.');
        return self::SUCCESS;
    }
}
