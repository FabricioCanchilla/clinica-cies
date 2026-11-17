<?php

use App\Http\Controllers\Admin\AuditoriaController;
use Illuminate\Support\Facades\Route;


        // Auditoría: la ven el administrador y el auditor
    Route::middleware('rol:ROL001,ROL009')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria');
    });

    // El auditor entra directo a la auditoría
    Route::middleware('rol:ROL009')->get('/auditor', fn () => redirect()->route('admin.auditoria'))
        ->name('auditor.dashboard');
        Route::get('/admin/auditoria/excel', [\App\Http\Controllers\Admin\AuditoriaController::class, 'excel'])
    ->name('admin.auditoria.excel')->middleware('rol:ROL001,ROL009');

Route::get('/admin/auditoria/pdf', [\App\Http\Controllers\Admin\AuditoriaController::class, 'pdf'])
    ->name('admin.auditoria.pdf')->middleware('rol:ROL001,ROL009');
