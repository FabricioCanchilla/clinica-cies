<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RNF007 / RN20: liberación de reservas no pagadas cada minuto (y RN23 inasistencias)
Schedule::command('citas:liberar-vencidas')->everyMinute()->withoutOverlapping();

// RF015: recordatorios de las citas de mañana y de saldos pendientes
Schedule::command('citas:recordatorios')->dailyAt('18:00')->withoutOverlapping();

// RN48: copia completa diaria a las 02:00 (conserva 30 días) y prueba de restauración mensual
Schedule::command('respaldo:crear')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('respaldo:probar')->monthlyOn(1, '03:00')->withoutOverlapping();
