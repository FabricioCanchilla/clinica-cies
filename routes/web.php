<?php
use App\Http\Controllers\Auth\CambioClaveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Auth\RegistroController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Laboratorio\OrdenController as LabOrdenController;
Route::get('/', fn () => redirect()->route('panel'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    
    // RF005 / RN04: recuperación con enlace firmado de 30 min y un solo uso
    Route::get('/recuperar-contrasena', [RecuperacionController::class, 'form'])->name('password.request');
    Route::post('/recuperar-contrasena', [RecuperacionController::class, 'enviar'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer/{codUsr}', [RecuperacionController::class, 'formReset'])
        ->middleware('signed')->name('password.reset');
    Route::post('/restablecer/{codUsr}', [RecuperacionController::class, 'reset'])
        ->middleware(['signed', 'throttle:5,1'])->name('password.update');
    
    // RF003 / RN07: registro de paciente nuevo
    Route::post('/register', [RegistroController::class, 'store'])
        ->middleware('throttle:5,1')->name('register');
    Route::get('/activar-paciente/{paciente}', [\App\Http\Controllers\Auth\ActivacionPacienteController::class, 'form'])->middleware('signed')->name('paciente.activacion.form');
    Route::post('/activar-paciente/{paciente}', [\App\Http\Controllers\Auth\ActivacionPacienteController::class, 'activar'])->middleware(['signed', 'throttle:5,1'])->name('paciente.activacion.guardar');
});

// 1. Protegemos todo con el middleware 'auth'
Route::middleware('auth')->group(function () {
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Bandeja de notificaciones del usuario (RN46 alertas críticas)
    Route::get('/notificaciones', [\App\Http\Controllers\Comun\NotificacionController::class, 'index'])->name('notificaciones');
    Route::get('/resultados/{codRes}/archivo', [LabOrdenController::class, 'archivo'])->name('resultados.archivo');

    Route::get('/cambiar-clave', [CambioClaveController::class, 'form'])->name('clave.cambiar');
    Route::post('/cambiar-clave', [CambioClaveController::class, 'actualizar'])->name('clave.actualizar');

    // Redirección automática según el rol
    Route::get('/panel', function () {
        $rol = trim(auth()->user()->COD_ROL); 
        
        $rutasPorRol = [
            'ROL001' => 'admin.dashboard',
            'ROL002' => 'recepcion.dashboard',
            'ROL003' => 'medico.dashboard',
            'ROL004' => 'enfermeria.dashboard',
            'ROL005' => 'caja.dashboard',
            'ROL006' => 'paciente.dashboard',
            'ROL007' => 'laboratorio.dashboard',
            'ROL008' => 'farmacia.dashboard',
            'ROL009' => 'auditor.dashboard',
        ];

        if (array_key_exists($rol, $rutasPorRol)) {
            return redirect()->route($rutasPorRol[$rol]);
        }

        abort(403, 'Tu rol (' . $rol . ') no tiene un panel asignado.');
    })->name('panel');

    // 2. Panel de Administrador
    require __DIR__.'/administracion.php';
    require __DIR__.'/medico.php';
    require __DIR__.'/laboratorio.php';
    require __DIR__.'/farmacia.php';
    require __DIR__.'/paciente.php';
    require __DIR__.'/enfermeria.php';
    require __DIR__.'/caja.php';
    require __DIR__.'/recepcion.php';
    require __DIR__.'/auditoria.php';
});
