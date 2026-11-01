<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array($request->user()->COD_ROL, $roles, true)) {
            abort(403, 'No tienes permiso para entrar a esta sección.');
        }

        return $next($request);
    }
}