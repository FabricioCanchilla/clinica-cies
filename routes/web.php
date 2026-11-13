<?php
use App\Http\Controllers\Admin\PacienteController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\CambioClaveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Medico\DashboardController as MedicoDashboardController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmpleadoController;
use App\Http\Controllers\Laboratorio\OrdenController as LabOrdenController;
use App\Http\Controllers\Farmacia\RecetaController as FarmRecetaController;
use App\Http\Controllers\Farmacia\LoteController as FarmLoteController;

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

// 1. Protegemos todo con el middleware 'auth'
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

$secciones = [
    'citas'       => 'Citas',
    'caja'        => 'Caja',
    'internacion' => 'Internación',
];

foreach ($secciones as $slug => $titulo) {
    Route::get("/$slug", fn () => view('admin.pendiente', ['titulo' => $titulo]))
        ->name($slug);
}
}); // Cierre del grupo administrativo

    // 3. Panel de Médico
    Route::middleware('rol:ROL003')->prefix('medico')->name('medico.')->group(function () {
        Route::get('/', [MedicoDashboardController::class, 'index'])->name('dashboard');
    });
    // Panel de Laboratorio (ROL007)
    Route::middleware('rol:ROL007')->prefix('laboratorio')->name('laboratorio.')->group(function () {
        Route::get('/', [LabOrdenController::class, 'index'])->name('dashboard');
        Route::post('/ordenes/{codOrd}/avanzar', [LabOrdenController::class, 'avanzar'])->name('avanzar');
        Route::post('/ordenes/{codOrd}/cancelar', [LabOrdenController::class, 'cancelar'])->name('cancelar');
        Route::get('/ordenes/{codOrd}/resultado', [LabOrdenController::class, 'formResultado'])->name('resultado');
        Route::post('/ordenes/{codOrd}/resultado', [LabOrdenController::class, 'guardarResultado'])->name('resultado.guardar');
        Route::post('/ordenes/{codOrd}/validar', [LabOrdenController::class, 'validar'])->name('validar');
        Route::post('/ordenes/{codOrd}/publicar', [LabOrdenController::class, 'publicar'])->name('publicar');
    });
        // Panel de Farmacia (ROL008)
    Route::middleware('rol:ROL008')->prefix('farmacia')->name('farmacia.')->group(function () {
        Route::get('/', [FarmRecetaController::class, 'index'])->name('dashboard');
        Route::get('/recetas/{codRec}', [FarmRecetaController::class, 'show'])->name('receta');
        Route::post('/recetas/{codRec}/items/{codDre}/despachar', [FarmRecetaController::class, 'despachar'])->name('despachar');

        Route::get('/lotes', [FarmLoteController::class, 'index'])->name('lotes');
        Route::post('/lotes', [FarmLoteController::class, 'entrada'])->name('lotes.entrada');
        Route::get('/lotes/{codLot}/baja', [FarmLoteController::class, 'formBaja'])->name('lotes.baja');
        Route::post('/lotes/{codLot}/baja', [FarmLoteController::class, 'guardarBaja'])->name('lotes.baja.guardar');
    });
    // 4. Panel de los demás roles
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
});