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
        if ($accion === 'Leer' && $entidad === 'HISTORIA_CLINICA' && $actor->COD_ROL === 'ROL003' && $actor->COD_EMP) {
            self::revisarHorarioMedico($actor);
            $cantidad = DB::table('AUDITORIA')->where('COD_USR', $actor->COD_USR)->where('ENT_AUD', 'HISTORIA_CLINICA')
                ->where('ACC_AUD', 'Leer')->where('FEC_AUD', '>=', now()->subMinutes(15))->whereNotNull('COD_PAC')->distinct()->count('COD_PAC');
            if ($cantidad >= 10) {
                self::alertaSeguridad($actor, 'Consulta masiva', 'Se consultaron al menos 10 pacientes distintos en 15 minutos.', 15);
            }
        }
    }

    private static function revisarHorarioMedico(object $actor): void
    {
        if (!DB::table('HORARIO_MEDICO')->where('COD_EMP', $actor->COD_EMP)->exists()) { return; }
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $enHorario = DB::table('HORARIO_MEDICO')->where('COD_EMP', $actor->COD_EMP)->where('DIA_HOR', $dias[now()->dayOfWeek])
            ->where('HORINI_HOR', '<=', now()->format('H:i:s'))->where('HORFIN_HOR', '>', now()->format('H:i:s'))->exists();
        if ($enHorario) { return; }
        self::alertaSeguridad($actor, 'Acceso fuera horario', 'Acceso a historia fuera del horario médico registrado.', 60);
    }

    private static function alertaSeguridad(object $actor, string $tipo, string $mensaje, int $minutos): void
    {
        $yaAvisado = DB::table('NOTIFICACION')->where('TIP_NOT', $tipo)->where('REF_NOT', $actor->COD_USR)
            ->where('FEC_NOT', '>=', now()->subMinutes($minutos))->exists();
        if (!$yaAvisado) {
            $destinatarios = DB::table('USUARIO')->whereIn('COD_ROL', ['ROL001', 'ROL009'])->where('EST_USR', 'Activo')->pluck('COD_USR');
            foreach ($destinatarios as $destinatario) {
                DB::table('NOTIFICACION')->insert(['COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1), 'FEC_NOT' => now(),
                    'TIP_NOT' => $tipo, 'MSG_NOT' => $mensaje.' Usuario: '.$actor->USR_USR.'.',
                    'CAN_NOT' => 'Sistema', 'EST_NOT' => 'Enviada', 'REF_NOT' => $actor->COD_USR, 'COD_USR' => $destinatario]);
            }
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
