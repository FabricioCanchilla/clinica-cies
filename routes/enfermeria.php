<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Enfermeria\InsumoController;
use App\Http\Controllers\Enfermeria\AtencionController as EnfermeriaAtencionController;


    Route::middleware('rol:ROL004')->prefix('enfermeria')->name('enfermeria.')->group(function () {
        Route::get('/pacientes', [EnfermeriaAtencionController::class, 'index'])->name('pacientes');
        Route::get('/pacientes/{internacion}', [EnfermeriaAtencionController::class, 'show'])->name('paciente');
        Route::post('/pacientes/{internacion}/signos', [EnfermeriaAtencionController::class, 'signos'])->name('signos');
        Route::post('/pacientes/{internacion}/nota', [EnfermeriaAtencionController::class, 'nota'])->name('nota');
        Route::post('/pacientes/{internacion}/balance', [EnfermeriaAtencionController::class, 'balance'])->name('balance');
        Route::post('/pacientes/{internacion}/dosis', [EnfermeriaAtencionController::class, 'programar'])->name('dosis.programar');
        Route::post('/dosis/{dosis}/administrar', [EnfermeriaAtencionController::class, 'administrar'])->name('dosis.administrar');
        Route::post('/dosis/{dosis}/unidades-anteriores', [EnfermeriaAtencionController::class, 'unidadesAnteriores'])->name('dosis.unidades-anteriores');
        Route::post('/dosis/{dosis}/omitir', [EnfermeriaAtencionController::class, 'omitir'])->name('dosis.omitir');
        Route::post('/insumos/{codIns}/cierre', [EnfermeriaAtencionController::class, 'cerrarInsumo'])->name('insumos.cierre');
        Route::get('/', [InsumoController::class, 'index'])->name('dashboard');
        Route::get('/insumos/crear', [InsumoController::class, 'create'])->name('insumos.crear');
        Route::post('/insumos', [InsumoController::class, 'store'])->name('insumos.guardar');
        Route::get('/insumos/{codIns}/verificar', [InsumoController::class, 'verificarForm'])->name('insumos.verificar');
        Route::post('/insumos/{codIns}/otp', [InsumoController::class, 'enviarOtp'])->name('insumos.otp');
        Route::post('/insumos/{codIns}/verificar', [InsumoController::class, 'verificar'])->name('insumos.verificar.guardar');
        Route::post('/insumos/{codIns}/comprobante', [InsumoController::class, 'reintentar'])->name('insumos.comprobante');
        Route::get('/insumos/{codIns}/firma', [InsumoController::class, 'firma'])->name('insumos.firma');
        Route::get('/comprobantes/{nroCen}/pdf', [InsumoController::class, 'pdf'])->name('insumos.pdf');
    });

    // Panel de Caja (ROL005), RF039: apertura, cobros, anulación y cierre
