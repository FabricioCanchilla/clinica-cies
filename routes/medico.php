<?php

use App\Http\Controllers\Medico\DashboardController as MedicoDashboardController;
use Illuminate\Support\Facades\Route;
    Route::middleware('rol:ROL003')->prefix('medico')->name('medico.')->group(function () {
        Route::get('/', [MedicoDashboardController::class, 'index'])->name('dashboard');
        Route::get('/cie10', [\App\Http\Controllers\Medico\ConsultaController::class, 'catalogo'])->name('cie10');
        Route::get('/historias', [\App\Http\Controllers\Medico\HistoriaClinicaController::class, 'index'])->name('historias');
        Route::post('/emergencia', [\App\Http\Controllers\Medico\HistoriaClinicaController::class, 'emergencia'])->middleware('throttle:5,1')->name('emergencia');
        Route::get('/historias/{paciente}', [\App\Http\Controllers\Medico\HistoriaClinicaController::class, 'show'])->name('historias.ver');
        Route::post('/historias/{paciente}', [\App\Http\Controllers\Medico\HistoriaClinicaController::class, 'guardar'])->name('historias.guardar');
        Route::post('/historias/{paciente}/adendas', [\App\Http\Controllers\Medico\HistoriaClinicaController::class, 'adenda'])->name('historias.adenda');
        Route::get('/citas/{cita}', [\App\Http\Controllers\Medico\ConsultaController::class, 'cita'])->name('citas.ver');
        Route::post('/citas/{cita}/estado', [\App\Http\Controllers\Medico\ConsultaController::class, 'estado'])->name('citas.estado');
        Route::post('/citas/{cita}/consulta', [\App\Http\Controllers\Medico\ConsultaController::class, 'store'])->name('consultas.guardar');
        Route::get('/consultas/{consulta}', [\App\Http\Controllers\Medico\ConsultaController::class, 'show'])->name('consultas.ver');
        Route::post('/consultas/{consulta}/recetas', [\App\Http\Controllers\Medico\ConsultaController::class, 'receta'])->name('consultas.receta');
        Route::post('/consultas/{consulta}/examenes', [\App\Http\Controllers\Medico\ConsultaController::class, 'examen'])->name('consultas.examen');
        Route::post('/consultas/{consulta}/interconsultas', [\App\Http\Controllers\Medico\ConsultaController::class, 'interconsulta'])->name('consultas.interconsulta');
        Route::post('/consultas/{consulta}/consentimientos', [\App\Http\Controllers\Medico\ConsultaController::class, 'consentimiento'])->name('consultas.consentimiento');
        Route::post('/interconsultas/{interconsulta}/respuesta', [\App\Http\Controllers\Medico\ConsultaController::class, 'responder'])->name('interconsultas.responder');
        Route::get('/recetas/{receta}/pdf', [\App\Http\Controllers\Medico\ConsultaController::class, 'pdf'])->name('recetas.pdf');
        Route::get('/actividad/{seccion}', [\App\Http\Controllers\Medico\ActividadController::class, 'index'])->name('actividad');

    });
    // Panel de Laboratorio (ROL007)
