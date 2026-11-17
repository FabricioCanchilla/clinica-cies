<?php

use App\Http\Controllers\Admin\PacienteController;
use App\Http\Controllers\Admin\UsuarioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmpleadoController;
    Route::middleware('rol:ROL001')->prefix('admin')->name('admin.')->group(function () {

        Route::get('/', function () {
            $totales = [
                'Usuarios'  => DB::table('usuario')->count(),
                'Empleados' => DB::table('empleado')->count(),
                'Pacientes' => DB::table('paciente')->count(),
                'Citas'     => DB::table('cita')->count(),
            ];

            $ultimos = DB::table('usuario')
                ->join('rol', 'usuario.COD_ROL', '=', 'rol.COD_ROL')
                ->select('usuario.USR_USR', 'usuario.EST_USR', 'usuario.FECULT_USR', 'rol.DES_ROL')
                ->orderByDesc('usuario.id')
                ->limit(5)
                ->get();

            return view('admin.dashboard', compact('totales', 'ultimos'));
        })->name('dashboard');

        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.crear');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.guardar');
        Route::get('/usuarios/{codUsr}/editar', [UsuarioController::class, 'edit'])->name('usuarios.editar');
        Route::put('/usuarios/{codUsr}', [UsuarioController::class, 'update'])->name('usuarios.actualizar');
        Route::post('/usuarios/{codUsr}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
        Route::post('/usuarios/{codUsr}/reset-clave', [UsuarioController::class, 'resetClave'])->name('usuarios.reset');
        Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados');
        Route::get('/empleados/crear', [EmpleadoController::class, 'create'])->name('empleados.crear');
        Route::post('/empleados', [EmpleadoController::class, 'store'])->name('empleados.guardar');
        Route::get('/empleados/{codEmp}/editar', [EmpleadoController::class, 'edit'])->name('empleados.editar');
        Route::put('/empleados/{codEmp}', [EmpleadoController::class, 'update'])->name('empleados.actualizar');
        Route::get('/pacientes', [PacienteController::class, 'index'])
    ->name('pacientes');

Route::get('/citas', [\App\Http\Controllers\Admin\GestionController::class, 'citas'])->name('citas');
Route::post('/citas/{codigo}/llegada', [\App\Http\Controllers\Admin\GestionController::class, 'llegada'])->name('citas.llegada');
Route::post('/citas/{codigo}/cancelar', [\App\Http\Controllers\Admin\GestionController::class, 'cancelar'])->name('citas.cancelar');
Route::post('/citas/{codigo}/reprogramar', [\App\Http\Controllers\Admin\GestionController::class, 'reprogramar'])->name('citas.reprogramar');
Route::get('/caja', [\App\Http\Controllers\Admin\GestionController::class, 'caja'])->name('caja');
Route::get('/internacion', [\App\Http\Controllers\Admin\GestionController::class, 'internacion'])->name('internacion');
Route::post('/internacion', [\App\Http\Controllers\Admin\GestionController::class, 'ingresar'])->name('internacion.ingresar');
Route::post('/internacion/{codigo}/alta', [\App\Http\Controllers\Admin\GestionController::class, 'alta'])->name('internacion.alta');
Route::post('/camas/{codigo}/disponible', [\App\Http\Controllers\Admin\GestionController::class, 'cama'])->name('internacion.cama');
}); // Cierre del grupo administrativo

    // 3. Panel de Médico
