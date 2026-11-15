<?php

namespace App\Console\Commands;

use App\Services\CitaService;
use Illuminate\Console\Command;

class LiberarCitasVencidas extends Command
{
    protected $signature = 'citas:liberar-vencidas';
    protected $description = 'Libera los horarios de citas sin anticipo pagado pasados los 15 minutos (RNF007)';

    public function handle(CitaService $citas): int
    {
        $this->info('Citas liberadas: ' . $citas->liberarVencidas());
        return self::SUCCESS;
    }
}