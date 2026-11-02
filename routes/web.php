<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\CambioClaveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Medico\DashboardController as MedicoDashboardController; // <-- Importamos el nuevo controlador
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('panel'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    
    Route::get('/recuperar-contrasena', function () {
        return view('auth.forgot-password'); 
    })->name('password.request');
    
    Route::post('/register', function () {
        return back()->with('success', 'Registro de paciente procesado.');
    })->name('register');
});

// 1. PRIMERO protegemos todo con el middleware 'auth' (requiere estar logueado)
Route::middleware('auth')->group(function () {
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

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
        ];

        if (array_key_exists($rol, $rutasPorRol)) {
            return redirect()->route($rutasPorRol[$rol]);
        }

        abort(403, 'Tu rol (' . $rol . ') no tiene un panel asignado.');
    })->name('panel');

    // 2. Panel de Administrador (Protegido por el Middleware de Rol)
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

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria');

        $secciones = [
            'pacientes'   => 'Pacientes',
            'citas'       => 'Citas',
            'caja'        => 'Caja',
            'internacion' => 'Internación',
        ];

        foreach ($secciones as $slug => $titulo) {
            Route::get("/$slug", fn () => view('admin.pendiente', ['titulo' => $titulo]))
                ->name($slug);
        }
    });

    // 3. Panel de Médico (Ruta personalizada)
    Route::middleware('rol:ROL003')->prefix('medico')->name('medico.')->group(function () {
        Route::get('/', [MedicoDashboardController::class, 'index'])->name('dashboard');
    });

    // 4. Panel de los demás roles (Protegidos cada uno por su Middleware)
    $otros = [
        'ROL002' => 'recepcion',
        'ROL004' => 'enfermeria',
        'ROL005' => 'caja',
        'ROL006' => 'paciente',
    ];

    foreach ($otros as $codigo => $slug) {
        Route::middleware("rol:$codigo")->group(function () use ($slug) {
            Route::get("/$slug", fn () => view('pendiente'))->name("$slug.dashboard");
        });
    }
});