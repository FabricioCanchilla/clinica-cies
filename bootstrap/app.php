<?php
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\VerificarActivo;
use App\Http\Middleware\ForzarCambioClave;
use App\Http\Middleware\VerificarPermiso;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
        ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => VerificarRol::class,
        ]);
                $middleware->appendToGroup('web', VerificarActivo::class);
        $middleware->appendToGroup('web', ForzarCambioClave::class);
        // RF043 / RNF001: permisos por módulo (ROL_PERMISO)
        $middleware->appendToGroup('web', VerificarPermiso::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
