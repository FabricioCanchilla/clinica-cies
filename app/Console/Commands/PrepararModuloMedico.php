<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararModuloMedico extends Command
{
    protected $signature = 'medico:preparar';

    protected $description = 'Instala las tablas adicionales del portal médico sin borrar datos (requiere usuario administrador de BD)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador utiliza el esquema MySQL del proyecto.');

            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/03_modulo_medico.sql')));
        $usuario = DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario;
        // Mantiene el usuario de aplicación con permisos por tabla, sin privilegios DDL.
        if (str_starts_with($usuario, 'root@')) {
            foreach (['MEDICO_ATENCION', 'CATALOGO_CIE10', 'DIAGNOSTICO_CONSULTA', 'ADENDA_EVOLUCION', 'INTERCONSULTA', 'CONSENTIMIENTO_MEDICO'] as $tabla) {
                DB::unprepared("GRANT SELECT, INSERT, UPDATE, DELETE ON `clinica_cies`.`{$tabla}` TO 'cies_app'@'%'");
            }
        }
        $this->call('medico:importar-cie10', ['archivo' => database_path('sql/cie10.csv')]);
        $this->info('Estructura médica preparada. No se eliminaron datos.');

        return self::SUCCESS;
    }
}
