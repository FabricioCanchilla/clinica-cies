<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;

/** RN48: copia de seguridad completa (programada a las 02:00) y depuración de las de más de 30 días. */
class RespaldoCrear extends Command
{
    protected $signature = 'respaldo:crear {--tipo=Automático}';
    protected $description = 'Crea una copia de seguridad completa de la base y elimina las que superan la retención (RN48)';

    public function handle(RespaldoService $respaldos): int
    {
        $r = $respaldos->crear($this->option('tipo') === 'Manual' ? 'Manual' : 'Automático');
        $this->info("Respaldo {$r->COD_RES}: {$r->TAB_RES} tablas, {$r->FIL_RES} filas, " . number_format($r->TAM_RES / 1024, 1) . ' KB');
        $this->info('Copias eliminadas por retención: ' . $respaldos->depurar());
        return self::SUCCESS;
    }
}
