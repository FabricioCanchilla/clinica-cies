<?php

use Illuminate\Support\Facades\Route;
    Route::middleware('rol:ROL002')->prefix('recepcion')->name('recepcion.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'index'])->name('dashboard');
        Route::get('/pacientes/buscar', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'buscarPacientes'])->middleware('throttle:60,1')->name('pacientes.buscar');
        Route::get('/pacientes/{paciente}/especialidades', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'especialidadesPaciente'])->name('pacientes.especialidades');
        Route::post('/pacientes', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'paciente'])->name('pacientes.guardar');
        Route::post('/pacientes/{paciente}/activacion', [\App\Http\Controllers\Auth\ActivacionPacienteController::class, 'enviar'])->middleware('throttle:5,1')->name('pacientes.activacion');
        Route::post('/citas', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'reservar'])->name('citas.guardar');
        Route::post('/citas/{codigo}/llegada', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'llegada'])->name('citas.llegada');
        Route::post('/citas/{codigo}/reprogramar', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'reprogramar'])->name('citas.reprogramar');
        Route::post('/citas/{codigo}/cancelar', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'cancelar'])->name('citas.cancelar');
        Route::post('/citas/{codigo}/recordatorio', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'recordatorio'])->name('citas.recordatorio');
        Route::post('/seguros', [\App\Http\Controllers\Recepcion\RecepcionController::class, 'seguro'])->name('seguros.guardar');
    });
