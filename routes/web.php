<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('panel'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Redirige a cada usuario al panel de su rol
    Route::get('/panel', function () {
        $ruta = config('roles.' . auth()->user()->COD_ROL . '.ruta');
        abort_if(!$ruta, 403, 'Rol sin panel asignado.');
        return redirect()->route($ruta);
    })->name('panel');

    // Administrador
    Route::middleware('rol:ROL001')->prefix('admin')->name('admin.')->group(function () {

        Route::get('/', function () {
            $totales = [
                'Usuarios'  => DB::table('USUARIO')->count(),
                'Empleados' => DB::table('EMPLEADO')->count(),
                'Pacientes' => DB::table('PACIENTE')->count(),
                'Citas'     => DB::table('CITA')->count(),
            ];

            $ultimos = DB::table('USUARIO')
                ->join('ROL', 'USUARIO.COD_ROL', '=', 'ROL.COD_ROL')
                ->select('USUARIO.USR_USR', 'USUARIO.EST_USR', 'USUARIO.FECULT_USR', 'ROL.DES_ROL')
                ->orderByDesc('USUARIO.id')
                ->limit(5)
                ->get();

            return view('admin.dashboard', compact('totales', 'ultimos'));
        })->name('dashboard');

        $secciones = [
            'usuarios'    => 'Usuarios',
            'pacientes'   => 'Pacientes',
            'citas'       => 'Citas',
            'caja'        => 'Caja',
            'internacion' => 'Internación',
            'auditoria'   => 'Auditoría',
        ];

        foreach ($secciones as $slug => $titulo) {
            Route::get("/$slug", fn () => view('admin.pendiente', ['titulo' => $titulo]))
                ->name($slug);
        }
    });

    // Los demás roles: panel provisional "en construcción"
    $otros = [
        'ROL002' => 'recepcion',
        'ROL003' => 'medico',
        'ROL004' => 'enfermeria',
        'ROL005' => 'caja',
        'ROL006' => 'paciente',
    ];

    foreach ($otros as $codigo => $slug) {
        Route::get("/$slug", fn () => view('pendiente'))
            ->middleware("rol:$codigo")
            ->name("$slug.dashboard");
    }
});