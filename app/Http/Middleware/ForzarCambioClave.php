<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForzarCambioClave
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si debe cambiar la clave, solo puede ver esa pantalla o cerrar sesión
        if ($user && $user->CAMCON_USR && !$request->routeIs('clave.*', 'logout')) {
            return redirect()->route('clave.cambiar');
        }

        return $next($request);
    }
}