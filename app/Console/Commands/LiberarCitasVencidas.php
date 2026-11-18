<?php

namespace App\Console\Commands;

use App\Services\CitaService;
use Illuminate\Console\Command;

class LiberarCitasVencidas extends Command
{
    protected $signature = 'citas:liberar-vencidas';
    protected $description = 'Libera los horarios de citas sin anticipo pagado pasados los 15 minutos (RNF007) y marca las inasistencias del día (RN23)';

    public function handle(CitaService $citas): int
    {
        $this->info('Citas liberadas: ' . $citas->liberarVencidas());
        // RN23: citas confirmadas de hoy sin check-in pasados 15 minutos
        $this->info('Inasistencias: ' . \App\Services\RecepcionService::inasistenciasAutomaticas());
        return self::SUCCESS;
    }
}