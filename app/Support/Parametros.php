<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * RF044: parámetros de configuración guardados en la tabla PARAMETRO. Al arrancar la aplicación
 * pisan los valores por defecto de config/cies.php y config/session.php, así el administrador
 * cambia el anticipo, la inactividad de la sesión, etc. sin tocar archivos.
 */
class Parametros
{
    /** COD_PAR → clave de configuración */
    public const MAPA = [
        'anticipo_porcentaje'        => 'cies.anticipo_porcentaje',
        'minutos_inactividad'        => 'session.lifetime',
        'dias_max_anticipacion'      => 'cies.dias_max_anticipacion',
        'descuento_sin_autorizacion' => 'cies.descuento_sin_autorizacion',
        'limpieza_terminal_min'      => 'cies.limpieza_terminal_min',
        'respaldo_retencion_dias'    => 'cies.respaldo_retencion_dias',
    ];

    public static function olvidar(): void
    {
        Cache::forget('cies.parametros');
    }

    public static function aplicar(): void
    {
        try {
            $valores = Cache::remember('cies.parametros', 300,
                fn () => DB::table('PARAMETRO')->pluck('VAL_PAR', 'COD_PAR')->all());
            foreach ($valores as $cod => $val) {
                if (isset(self::MAPA[$cod])) {
                    config([self::MAPA[$cod] => (int) $val]);
                }
            }
        } catch (\Throwable) {
            // Sin base de datos (instalación, pruebas sin MySQL) se usan los valores por defecto
        }
    }
}
