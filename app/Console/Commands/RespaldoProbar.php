<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** RN48: prueba de restauración (programada una vez al mes) sobre la última copia vigente. */
class RespaldoProbar extends Command
{
    protected $signature = 'respaldo:probar {codigo? : COD_RES; por defecto la última copia vigente}';
    protected $description = 'Restaura una copia en la base de pruebas y verifica hash y filas (RN48)';

    public function handle(RespaldoService $respaldos): int
    {
        $cod = $this->argument('codigo')
            ?? DB::table('RESPALDO')->where('EST_RES', 'Vigente')->orderByDesc('id')->value('COD_RES');
        if (!$cod) {
            $this->warn('No hay respaldos vigentes: se crea uno antes de probar.');
            $cod = $respaldos->crear('Automático')->COD_RES;
        }
        $r = $respaldos->probar($cod);
        $r['ok'] ? $this->info($r['detalle']) : $this->error($r['detalle']);
        if (!$r['ok']) {
            // RN46: una restauración fallida es un evento crítico para el administrador y el auditor
            $admin = DB::table('USUARIO')->where('COD_ROL', 'ROL001')->where('EST_USR', 'Activo')->first();
            if ($admin) {
                \App\Services\Auditoria::registrar('Probar restauración', 'Crítica', 'DOM02', 'RESPALDO', $cod, $r['detalle'], $admin);
            }
        }
        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
