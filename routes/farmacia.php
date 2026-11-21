<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Farmacia\RecetaController as FarmRecetaController;
use App\Http\Controllers\Farmacia\LoteController as FarmLoteController;
    Route::middleware('rol:ROL008')->prefix('farmacia')->name('farmacia.')->group(function () {
        Route::get('/', [FarmRecetaController::class, 'index'])->name('dashboard');
        Route::get('/recetas/{codRec}', [FarmRecetaController::class, 'show'])->name('receta');
        Route::post('/recetas/{codRec}/items/{codDre}/cantidad', [FarmRecetaController::class, 'cantidad'])->name('cantidad');
        Route::post('/recetas/{codRec}/items/{codDre}/despachar', [FarmRecetaController::class, 'despachar'])->name('despachar');

        Route::post('/medicamentos', [FarmLoteController::class, 'crearMedicamento'])->name('medicamentos.crear');
        Route::get('/lotes', [FarmLoteController::class, 'index'])->name('lotes');
        Route::post('/precios/{medicamento}', [FarmLoteController::class, 'precio'])->name('precios.guardar');
        Route::post('/lotes', [FarmLoteController::class, 'entrada'])->name('lotes.entrada');
        Route::get('/lotes/{codLot}/baja', [FarmLoteController::class, 'formBaja'])->name('lotes.baja');
        Route::post('/lotes/{codLot}/baja', [FarmLoteController::class, 'guardarBaja'])->name('lotes.baja.guardar');
    });

    // Panel del paciente (ROL006): sus datos, sus citas, agendamiento y pago con OTP
