<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararSaldoPaciente extends Command
{
    protected $signature = 'paciente:preparar-saldo';
    protected $description = 'Prepara el registro de saldos de pacientes';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Este instalador requiere MySQL.');
            return self::FAILURE;
        }
        DB::unprepared(file_get_contents(database_path('sql/08_saldo_paciente.sql')));
        $this->info('Registro de saldos preparado.');
        return self::SUCCESS;
    }
}
