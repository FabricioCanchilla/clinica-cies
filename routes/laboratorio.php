<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Laboratorio\OrdenController as LabOrdenController;
    Route::middleware('rol:ROL007')->prefix('laboratorio')->name('laboratorio.')->group(function () {
        Route::get('/', [LabOrdenController::class, 'index'])->name('dashboard');
        Route::post('/ordenes/{codOrd}/avanzar', [LabOrdenController::class, 'avanzar'])->name('avanzar');
        Route::post('/ordenes/{codOrd}/cancelar', [LabOrdenController::class, 'cancelar'])->name('cancelar');
        Route::get('/ordenes/{codOrd}/resultado', [LabOrdenController::class, 'formResultado'])->name('resultado');
        Route::post('/ordenes/{codOrd}/resultado', [LabOrdenController::class, 'guardarResultado'])->name('resultado.guardar');
        Route::post('/ordenes/{codOrd}/validar', [LabOrdenController::class, 'validar'])->name('validar');
        Route::post('/ordenes/{codOrd}/publicar', [LabOrdenController::class, 'publicar'])->name('publicar');
    });
        // Panel de Farmacia (ROL008)
