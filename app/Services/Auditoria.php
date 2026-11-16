<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Auditoria
{
    /**
     * Registra un evento. Si no se pasa $actor, se usa el usuario autenticado.
     * $codPac es el paciente afectado por la acción (si aplica). Si no se pasa,
     * se usa el COD_PAC del actor (caso en que el propio paciente actúa).
     */
    public static function registrar(
        string $accion,
        string $criticidad,
        string $dominio,
        string $entidad,
        string $registro,
        string $detalle,
        ?object $actor = null,
        ?string $codPac = null
    ): void {
        $actor = $actor ?? Auth::user();

        $siguiente = (int) DB::table('AUDITORIA')->max('id') + 1;

        DB::table('AUDITORIA')->insert([
            'COD_AUD' => sprintf('AUD%08d', $siguiente),
            'FEC_AUD' => now(),
            'ACC_AUD' => mb_substr($accion, 0, 20),
            'CRI_AUD' => $criticidad,
            'ENT_AUD' => $entidad,
            'REG_AUD' => $registro,
            'DET_AUD' => mb_substr($detalle, 0, 255),
            'IP_AUD'  => request()->ip() ?? '0.0.0.0',
            'DIS_AUD' => mb_substr(request()->userAgent() ?? 'desconocido', 0, 60),
            'COD_DOM' => $dominio,
            'COD_USR' => $actor->COD_USR,
            'COD_ROL' => $actor->COD_ROL,
            'COD_PAC' => $codPac ?? ($actor->COD_PAC ?? null),
        ]);

        // RN46: un evento «Crítica» genera una notificación al administrador y al auditor
        if ($criticidad === 'Crítica') {
            self::notificarCritico($accion, $registro, $detalle);
        }
    }

    private static function notificarCritico(string $accion, string $registro, string $detalle): void
    {
        $destinatarios = DB::table('USUARIO')
            ->whereIn('COD_ROL', ['ROL001', 'ROL009'])
            ->where('EST_USR', 'Activo')
            ->pluck('COD_USR');

        foreach ($destinatarios as $codUsr) {
            DB::table('NOTIFICACION')->insert([
                'COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1),
                'FEC_NOT' => now(),
                'TIP_NOT' => 'Alerta crítica',
                'MSG_NOT' => mb_substr("{$accion}: {$detalle}", 0, 255),
                'CAN_NOT' => 'Sistema',
                'EST_NOT' => 'Enviada',
                'REF_NOT' => mb_substr($registro, 0, 15),
                'COD_USR' => $codUsr,
            ]);
        }
    }
}