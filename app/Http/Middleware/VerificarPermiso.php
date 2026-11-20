<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * RF043 / RNF001: además del panel por rol, cada grupo de rutas exige el permiso de su módulo
 * (config/permisos.php). Gana el prefijo más largo, así un permiso más específico (por ejemplo
 * «medico.historia») se exige por sobre el general («medico.»).
 */
class VerificarPermiso
{
    private static ?array $cache = null;

    public function handle(Request $request, Closure $next): Response
    {
        $user  = $request->user();
        $ruta  = $request->route()?->getName();
        if (!$user || !$ruta || str_ends_with($ruta, '.dashboard')) {
            return $next($request);
        }

        $permiso = self::permisoDeRuta($ruta);
        if ($permiso && !self::tiene($user->COD_ROL, $permiso)) {
            abort(403, 'Tu rol no tiene el permiso «' . config("permisos.modulos.$permiso.nombre") . '».');
        }

        return $next($request);
    }

    public static function permisoDeRuta(string $ruta): ?string
    {
        $mejor = null; $largo = 0;
        foreach (config('permisos.modulos') as $cod => $m) {
            foreach ($m['rutas'] as $prefijo) {
                if (str_starts_with($ruta, $prefijo) && strlen($prefijo) > $largo) {
                    $mejor = $cod; $largo = strlen($prefijo);
                }
            }
        }
        return $mejor;
    }

    public static function tiene(string $rol, string $permiso): bool
    {
        if (self::$cache === null) {
            try {
                $filas = Schema::hasTable('ROL_PERMISO') ? DB::table('ROL_PERMISO')->get() : collect();
            } catch (\Throwable) {
                $filas = collect();
            }
            // Sin matriz cargada se usan los permisos por defecto (no se bloquea a nadie por falta de datos)
            self::$cache = $filas->isEmpty()
                ? config('permisos.por_defecto')
                : $filas->groupBy('COD_ROL')->map(fn ($g) => $g->pluck('COD_PER')->all())->all();
        }
        return in_array($permiso, self::$cache[$rol] ?? [], true);
    }

    public static function limpiarCache(): void
    {
        self::$cache = null;
    }
}
