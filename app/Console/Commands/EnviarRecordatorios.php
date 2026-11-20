<?php

namespace App\Console\Commands;

use App\Services\Notificador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RF015: recordatorios automáticos.
 *  - Citas confirmadas de mañana (sistema + correo).
 *  - Saldos pendientes de citas confirmadas (sistema).
 * No repite un recordatorio ya enviado para la misma cita.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'citas:recordatorios';
    protected $description = 'Envía recordatorios de citas de mañana y de saldos pendientes (RF015)';

    public function handle(): int
    {
        $manana = now()->addDay()->toDateString();
        $citas = DB::table('CITA as c')
            ->join('USUARIO as u', 'u.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.FEC_CIT', $manana)->where('c.EST_CIT', 'Confirmada')
            ->get(['c.COD_CIT', 'c.HOR_CIT', 'u.COD_USR', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.PRE_SRV',
                   DB::raw('(SELECT COALESCE(SUM(p.MON_PAG), 0) FROM PAGO p WHERE p.COD_CIT = c.COD_CIT AND p.EST_PAG = "Confirmado") as PAGADO')]);

        $enviados = 0; $saldos = 0;
        foreach ($citas as $c) {
            if (DB::table('NOTIFICACION')->where('REF_NOT', $c->COD_CIT)->where('TIP_NOT', 'Recordatorio de cita')->exists()) {
                continue;
            }
            $hora = substr($c->HOR_CIT, 0, 5);
            Notificador::conCorreo($c->COD_USR, 'Recordatorio de cita',
                "Mañana a las {$hora} tienes cita ({$c->COD_CIT}) con {$c->TIT_EMP} {$c->NOM_EMP} {$c->APE_EMP}. "
                . 'Llega 15 minutos antes para tu registro en recepción.', $c->COD_CIT);
            $enviados++;

            $saldo = round((float) $c->PRE_SRV - (float) $c->PAGADO, 2);
            if ($saldo > 0) {
                Notificador::sistema($c->COD_USR, 'Saldo pendiente',
                    "Tu cita {$c->COD_CIT} tiene un saldo de Bs " . number_format($saldo, 2) . ' que se paga en caja antes de la atención (orden OP-' . $c->COD_CIT . ').', $c->COD_CIT);
                $saldos++;
            }
        }
        $this->info("Recordatorios enviados: {$enviados}; avisos de saldo: {$saldos}");
        return self::SUCCESS;
    }
}
