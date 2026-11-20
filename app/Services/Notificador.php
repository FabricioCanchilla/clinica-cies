<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * RF015: avisos al usuario en la bandeja del sistema y, si se pide, también por correo.
 * Un fallo del correo no detiene la operación: la notificación queda «Fallida» y la del sistema se conserva.
 */
class Notificador
{
    public static function sistema(string $codUsr, string $tipo, string $mensaje, string $ref): void
    {
        self::insertar($codUsr, $tipo, $mensaje, 'Sistema', 'Enviada', $ref);
    }

    /** Notificación en el sistema + copia por correo al correo del paciente o empleado. */
    public static function conCorreo(string $codUsr, string $tipo, string $mensaje, string $ref): void
    {
        self::sistema($codUsr, $tipo, $mensaje, $ref);

        $correo = DB::table('USUARIO as u')
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'u.COD_PAC')
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'u.COD_EMP')
            ->where('u.COD_USR', $codUsr)
            ->value(DB::raw('COALESCE(p.COR_PAC, e.COR_EMP)'));
        if (!$correo) {
            return;
        }
        try {
            Mail::raw($mensaje . "\n\nClínica CIES · Av. Tejada Sorzano #393, Miraflores, La Paz", function ($m) use ($correo, $tipo) {
                $m->to($correo)->subject("Clínica CIES · {$tipo}");
            });
            self::insertar($codUsr, $tipo, $mensaje, 'Correo', 'Enviada', $ref);
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar el correo de «{$tipo}» a {$codUsr}: " . $e->getMessage());
            self::insertar($codUsr, $tipo, $mensaje, 'Correo', 'Fallida', $ref);
        }
    }

    /** Notifica al paciente (por su COD_PAC) si tiene cuenta. */
    public static function paciente(string $codPac, string $tipo, string $mensaje, string $ref, bool $correo = false): void
    {
        $codUsr = DB::table('USUARIO')->where('COD_PAC', $codPac)->value('COD_USR');
        if ($codUsr) {
            $correo ? self::conCorreo($codUsr, $tipo, $mensaje, $ref) : self::sistema($codUsr, $tipo, $mensaje, $ref);
        }
    }

    /** Notifica al empleado (por su COD_EMP) si tiene cuenta. */
    public static function empleado(string $codEmp, string $tipo, string $mensaje, string $ref): void
    {
        $codUsr = DB::table('USUARIO')->where('COD_EMP', $codEmp)->value('COD_USR');
        if ($codUsr) {
            self::sistema($codUsr, $tipo, $mensaje, $ref);
        }
    }

    private static function insertar(string $codUsr, string $tipo, string $mensaje, string $canal, string $estado, string $ref): void
    {
        DB::table('NOTIFICACION')->insert([
            'COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1),
            'FEC_NOT' => now(),
            'TIP_NOT' => mb_substr($tipo, 0, 25),
            'MSG_NOT' => mb_substr($mensaje, 0, 255),
            'CAN_NOT' => $canal,
            'EST_NOT' => $estado,
            'REF_NOT' => mb_substr($ref, 0, 15),
            'COD_USR' => $codUsr,
        ]);
    }
}
