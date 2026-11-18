<?php
use App\Http\Controllers\Admin\PacienteController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\CambioClaveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\Medico\DashboardController as MedicoDashboardController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmpleadoController;
use App\Http\Controllers\Laboratorio\OrdenController as LabOrdenController;
use App\Http\Controllers\Farmacia\RecetaController as FarmRecetaController;
use App\Http\Controllers\Farmacia\LoteController as FarmLoteController;
use App\Http\Controllers\Paciente\DashboardController as PacienteDashboardController;
use App\Http\Controllers\Paciente\CitaController as PacienteCitaController;
use App\Http\Controllers\Caja\CajaController;
use App\Http\Controllers\Enfermeria\InsumoController;

Route::get('/', fn () => redirect()->route('panel'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');

    // RN05: segundo factor OTP para Administrador, Caja y Auditor
    Route::get('/login/verificar', [LoginController::class, 'form2fa'])->name('login.2fa');
    Route::post('/login/verificar', [LoginController::class, 'verificar2fa'])
        ->middleware('throttle:10,1')->name('login.2fa.verificar');
    Route::post('/login/reenviar', [LoginController::class, 'reenviar2fa'])
        ->middleware('throttle:3,1')->name('login.2fa.reenviar');
    
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
});

// 1. Protegemos todo con el middleware 'auth'
Route::middleware('auth')->group(function () {
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Bandeja de notificaciones del usuario (RN46 alertas críticas)
    Route::get('/notificaciones', [\App\Http\Controllers\Comun\NotificacionController::class, 'index'])->name('notificaciones');

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

        // Supervisión (solo lectura): citas, caja e internación
        Route::get('/citas', [\App\Http\Controllers\Admin\SupervisionController::class, 'citas'])->name('citas');
        Route::get('/caja', [\App\Http\Controllers\Admin\SupervisionController::class, 'caja'])->name('caja');
        Route::get('/internacion', [\App\Http\Controllers\Admin\SupervisionController::class, 'internacion'])->name('internacion');
}); // Cierre del grupo administrativo

    // 3. Panel de Médico
    Route::middleware('rol:ROL003')->prefix('medico')->name('medico.')->group(function () {
        Route::get('/', [MedicoDashboardController::class, 'index'])->name('dashboard');
        // RF020: atender la cita (consulta + nota de evolución) y pasarla a Atendida
        Route::get('/citas/{codCit}/atender', [\App\Http\Controllers\Medico\AtencionController::class, 'form'])->name('atender');
        Route::post('/citas/{codCit}/atender', [\App\Http\Controllers\Medico\AtencionController::class, 'guardar'])->name('atender.guardar');
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

    // Panel del paciente (ROL006): sus datos, sus citas, agendamiento y pago con OTP
    Route::middleware('rol:ROL006')->prefix('paciente')->name('paciente.')->group(function () {
        Route::get('/', [PacienteDashboardController::class, 'index'])->name('dashboard');

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

        // RF009: cancelación y reprogramación (RN21, RN22)
        Route::post('/citas/{codCit}/cancelar', [PacienteCitaController::class, 'cancelar'])->name('citas.cancelar');
        Route::get('/citas/{codCit}/reprogramar', [PacienteCitaController::class, 'reprogramarForm'])->name('citas.reprogramar');
        Route::post('/citas/{codCit}/reprogramar', [PacienteCitaController::class, 'reprogramar'])->name('citas.reprogramar.guardar');

        // RF011: receta en PDF
        Route::get('/recetas/{codRec}/pdf', [PacienteDashboardController::class, 'recetaPdf'])->name('recetas.pdf');

        // RF014: mi perfil
        Route::post('/perfil', [PacienteDashboardController::class, 'actualizarPerfil'])->name('perfil.actualizar');
    });

    // Panel de Enfermería (ROL004): RF031 recepción de insumos y RF032 firma + OTP + comprobante (RN36)
    Route::middleware('rol:ROL004')->prefix('enfermeria')->name('enfermeria.')->group(function () {
        Route::get('/', [InsumoController::class, 'index'])->name('dashboard');
        Route::get('/insumos/crear', [InsumoController::class, 'create'])->name('insumos.crear');
        Route::post('/insumos', [InsumoController::class, 'store'])->name('insumos.guardar');
        Route::get('/insumos/{codIns}/verificar', [InsumoController::class, 'verificarForm'])->name('insumos.verificar');
        Route::post('/insumos/{codIns}/otp', [InsumoController::class, 'enviarOtp'])->name('insumos.otp');
        Route::post('/insumos/{codIns}/verificar', [InsumoController::class, 'verificar'])->name('insumos.verificar.guardar');
        Route::post('/insumos/{codIns}/comprobante', [InsumoController::class, 'reintentar'])->name('insumos.comprobante');
        Route::get('/insumos/{codIns}/firma', [InsumoController::class, 'firma'])->name('insumos.firma');
        Route::get('/comprobantes/{nroCen}/pdf', [InsumoController::class, 'pdf'])->name('insumos.pdf');
    });

    // Panel de Caja (ROL005), RF039: apertura, cobros, anulación y cierre
    Route::middleware('rol:ROL005')->prefix('caja')->name('caja.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('dashboard');
        Route::post('/abrir', [CajaController::class, 'abrir'])->name('abrir');
        Route::post('/cerrar', [CajaController::class, 'cerrar'])->name('cerrar');
        Route::get('/citas/{codCit}/cobrar', [CajaController::class, 'formCobro'])->name('cobrar');
        Route::post('/citas/{codCit}/cobrar', [CajaController::class, 'cobrar'])->name('cobrar.guardar');
        Route::post('/pagos/{codPag}/comprobante', [CajaController::class, 'reintentar'])->name('comprobante.reintentar');
        Route::post('/comprobantes/{nroCom}/anular', [CajaController::class, 'anular'])->name('anular');
        Route::get('/comprobantes/{nroCom}/pdf', [CajaController::class, 'pdf'])->name('pdf');
    });

    // Panel de Recepción (ROL002): RF036 pacientes, RF037 check-in y disponibilidad, RF038 seguros y agendas
    Route::middleware('rol:ROL002')->prefix('recepcion')->name('recepcion.')->group(function () {
        $rc = \App\Http\Controllers\Recepcion\RecepcionController::class;
        Route::get('/', [$rc, 'index'])->name('dashboard');
        Route::get('/pacientes', [$rc, 'pacientes'])->name('pacientes');
        Route::get('/pacientes/crear', [$rc, 'crearPaciente'])->name('pacientes.crear');
        Route::post('/pacientes', [$rc, 'guardarPaciente'])->name('pacientes.guardar');
        Route::get('/pacientes/{codPac}', [$rc, 'paciente'])->name('paciente');
        Route::post('/pacientes/{codPac}/seguros', [$rc, 'guardarSeguro'])->name('seguros.guardar');
        Route::post('/seguros/{codSeg}/revalidar', [$rc, 'revalidarSeguro'])->name('seguros.revalidar');
        Route::post('/citas/{codCit}/checkin', [$rc, 'checkin'])->name('checkin');
        Route::post('/citas/{codCit}/inasistencia', [$rc, 'inasistencia'])->name('inasistencia');
        Route::post('/citas/{codCit}/cancelar', [$rc, 'cancelarCita'])->name('cancelar');
        Route::get('/citas/{codCit}/reprogramar', [$rc, 'reprogramarForm'])->name('reprogramar');
        Route::post('/citas/{codCit}/reprogramar', [$rc, 'reprogramar'])->name('reprogramar.guardar');
        Route::get('/horarios', [$rc, 'horarios'])->name('horarios');
    });
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