<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Caja\CajaController;
    Route::middleware('rol:ROL005')->prefix('caja')->name('caja.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('dashboard');
        Route::post('/examenes/{orden}/cobrar', [CajaController::class, 'cobrarExamen'])->name('examen.cobrar');
        Route::post('/medicamentos/cobrar', [CajaController::class, 'cobrarMedicamento'])->name('medicamento.cobrar');
        Route::get('/medicamentos/{codigo}/pdf', [CajaController::class, 'comprobanteMedicamento'])->name('medicamento.pdf');
        Route::post('/medicamentos/{codigo}/anular', [CajaController::class, 'anularMedicamento'])->name('medicamento.anular');
        Route::post('/examenes/cobros/{codigo}/anular', [CajaController::class, 'anularExamen'])->name('examen.anular');
        Route::get('/examenes/cobros/{codigo}/pdf', [CajaController::class, 'comprobanteExamen'])->name('examen.pdf');
        Route::post('/abrir', [CajaController::class, 'abrir'])->name('abrir');
        Route::post('/cerrar', [CajaController::class, 'cerrar'])->name('cerrar');
        Route::get('/citas/{codCit}/cobrar', [CajaController::class, 'formCobro'])->name('cobrar');
        Route::post('/citas/{codCit}/cobrar', [CajaController::class, 'cobrar'])->name('cobrar.guardar');
        Route::post('/pagos/{codPag}/comprobante', [CajaController::class, 'reintentar'])->name('comprobante.reintentar');
        Route::post('/comprobantes/{nroCom}/anular', [CajaController::class, 'anular'])->name('anular');
        Route::get('/comprobantes/{nroCom}/pdf', [CajaController::class, 'pdf'])->name('pdf');
    });
