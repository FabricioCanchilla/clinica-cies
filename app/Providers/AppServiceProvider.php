<?php

namespace App\Providers;

use App\Support\Parametros;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // RF044: parámetros que el administrador cambia desde Configuración
        Parametros::aplicar();
    }
}
