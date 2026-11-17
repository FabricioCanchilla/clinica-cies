<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Paciente\DashboardController as PacienteDashboardController;
use App\Http\Controllers\Paciente\CitaController as PacienteCitaController;
    Route::middleware('rol:ROL006')->prefix('paciente')->name('paciente.')->group(function () {
        Route::get('/', [PacienteDashboardController::class, 'index'])->name('dashboard');
        Route::get('/recetas/{receta}/pdf', [PacienteDashboardController::class, 'recetaPdf'])->name('recetas.pdf');
        Route::get('/comprobantes/{numero}/pdf', [PacienteDashboardController::class, 'comprobantePdf'])->name('comprobantes.pdf');
        Route::get('/cobros/{tipo}/{codigo}/pdf', [PacienteDashboardController::class, 'comprobanteCobro'])->name('cobros.pdf');
        Route::post('/citas/{codCit}/cancelar-reserva', [PacienteCitaController::class, 'cancelarReserva'])->name('citas.cancelar-reserva');
        Route::post('/citas/{codCit}/cancelar', [PacienteCitaController::class, 'cancelarConfirmada'])->name('citas.cancelar');
        Route::post('/citas/{codCit}/aplicar-saldo', [PacienteCitaController::class, 'aplicarSaldo'])->name('citas.aplicar-saldo');
        Route::post('/citas/{codCit}/reprogramar', [PacienteCitaController::class, 'reprogramar'])->name('citas.reprogramar');

        // RF007: agendar
        Route::get('/citas/crear', [PacienteCitaController::class, 'create'])->name('citas.crear');
        Route::get('/citas/horarios', [PacienteCitaController::class, 'horarios'])->name('citas.horarios');
        Route::post('/citas', [PacienteCitaController::class, 'store'])->name('citas.guardar');

        // RF008: pagar el anticipo con OTP
        Route::get('/citas/{codCit}/pagar', [PacienteCitaController::class, 'pagar'])->name('citas.pagar');
        Route::post('/citas/{codCit}/otp', [PacienteCitaController::class, 'enviarOtp'])->name('citas.otp');
        Route::post('/citas/{codCit}/pagar', [PacienteCitaController::class, 'confirmar'])->name('citas.confirmar');

        // Fase 5: comprobante del anticipo (Quarkus) y reintento si falló después del pago
        Route::post('/citas/{codCit}/comprobante', [PacienteCitaController::class, 'reintentarComprobante'])->name('citas.comprobante.reintentar');
        Route::get('/citas/{codCit}/comprobante', [PacienteCitaController::class, 'comprobantePdf'])->name('citas.comprobante');
        // RF016: comprobantes de los insumos que entregaron sus familiares
        Route::get('/insumos/{nroCen}/comprobante', [PacienteCitaController::class, 'comprobanteInsumoPdf'])->name('insumos.comprobante');
    });

    // Panel de Enfermería (ROL004): RF031 recepción de insumos y RF032 firma + OTP + comprobante (RN36)
