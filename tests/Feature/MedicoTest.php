<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Carbon\Carbon;
use Database\Seeders\DatosClinicosPruebaSeeder;
use Database\Seeders\DatosInicialesSeeder;
use Database\Seeders\MedicoDemoSeeder;
use Database\Seeders\UsuariosPruebaSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MedicoTest extends TestCase
{
    public function test_farmacia_y_auditoria_respetan_roles_y_registran_consultas(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('CEIL', fn ($valor) => $valor === null ? null : ceil($valor), 1);
        $this->get('/farmacia')->assertForbidden();
        $this->get('/admin/auditoria')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'farmacia')->firstOrFail());
        $this->get('/farmacia')->assertOk();
        $this->get('/farmacia/recetas/REC0000001')->assertOk();
        $this->assertDatabaseHas('AUDITORIA', ['ENT_AUD' => 'RECETA', 'REG_AUD' => 'REC0000001', 'COD_PAC' => 'PAC0000001']);
        $this->get('/admin/auditoria')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'auditor')->firstOrFail());
        $this->get('/admin/auditoria?paciente=3000001')->assertOk()->assertSee('REC0000001');
        $this->assertDatabaseHas('AUDITORIA', ['ENT_AUD' => 'AUDITORIA', 'ACC_AUD' => 'Leer auditoría']);
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/despachar', ['cantidad' => 1])->assertForbidden();
    }

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->addAbsoluteCachePathPrefix('C:');
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'America/La_Paz'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        // Se prueban también los CHECK y las FK del esquema real, en una BD efímera.
        $sql = file_get_contents(database_path('sql/01_estructura.sql'));
        $sql = preg_replace('/--[^\r\n]*/', '', $sql);
        $sql = preg_replace('/SET NAMES[^;]*;|CREATE DATABASE[^;]*;|USE clinica_cies;/s', '', $sql);
        $sql = preg_replace('/id\s+BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,/', 'id INTEGER PRIMARY KEY AUTOINCREMENT,', $sql);
        $sql = preg_replace('/PRIMARY KEY \(id\),/', '', $sql);
        $sql = preg_replace('/UNIQUE KEY \w+/', 'UNIQUE', $sql);
        $sql = str_replace('ENGINE=InnoDB', '', $sql);
        DB::unprepared($sql);
        $extra = file_get_contents(database_path('sql/03_modulo_medico.sql'));
        $extra = preg_replace('/--[^\r\n]*/', '', $extra);
        $extra = preg_replace('/GRANT[^;]*;/', '', $extra);
        $extra = preg_replace('/ALTER TABLE[^;]*;/', '', $extra);
        $extra = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB', 'INSERT IGNORE'], ['INTEGER PRIMARY KEY AUTOINCREMENT', '', 'INSERT OR IGNORE'], $extra);
        DB::unprepared($extra);
        $enfermeria = file_get_contents(database_path('sql/04_modulo_enfermeria.sql'));
        $enfermeria = preg_replace('/GRANT[^;]*;/', '', $enfermeria);
        $enfermeria = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $enfermeria);
        DB::unprepared($enfermeria);
        $caja = file_get_contents(database_path('sql/05_modulo_caja.sql'));
        $caja = preg_replace('/GRANT[^;]*;/', '', $caja);
        $caja = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $caja);
        DB::unprepared($caja);
        $laboratorio = file_get_contents(database_path('sql/06_modulo_laboratorio.sql'));
        $laboratorio = preg_replace('/GRANT[^;]*;/', '', $laboratorio);
        $laboratorio = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $laboratorio);
        DB::unprepared($laboratorio);
        $farmacia = file_get_contents(database_path('sql/07_cantidad_farmacia.sql'));
        $farmacia = preg_replace('/GRANT[^;]*;/', '', $farmacia);
        $farmacia = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $farmacia);
        DB::unprepared($farmacia);
        $saldos = file_get_contents(database_path('sql/08_saldo_paciente.sql'));
        $saldos = preg_replace('/GRANT[^;]*;/', '', $saldos);
        $saldos = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'BIGINT UNSIGNED', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER', ''], $saldos);
        DB::unprepared($saldos);
        foreach (['09_cobros_examen.sql', '10_cobros_medicamentos.sql'] as $archivo) {
            $extraCaja = file_get_contents(database_path('sql/'.$archivo));
            $extraCaja = preg_replace('/GRANT[^;]*;/', '', $extraCaja);
            $extraCaja = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $extraCaja);
            DB::unprepared($extraCaja);
        }
        $unidades = file_get_contents(database_path('sql/11_unidades_dosis.sql'));
        $unidades = preg_replace('/GRANT[^;]*;|ALTER TABLE[^;]*;/', '', $unidades);
        $unidades = str_replace(['BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $unidades);
        DB::unprepared($unidades);
        $this->seed([DatosInicialesSeeder::class, UsuariosPruebaSeeder::class, DatosClinicosPruebaSeeder::class]);
        $this->actingAs(Usuario::where('USR_USR', 'medico')->firstOrFail());
        DB::table('CITA')->insert([
            'COD_CIT' => 'CIT0000099', 'FEC_CIT' => today()->toDateString(), 'HOR_CIT' => '09:00:00', 'DUR_CIT' => 30,
            'EST_CIT' => 'Confirmada', 'MOT_CIT' => 'Control', 'COD_PAC' => 'PAC0000001', 'COD_EMP' => 'EMP000003',
            'COD_CON' => 'CON001', 'COD_SRV' => 'SRV00001',
        ]);
    }

    public function test_cobros_de_examen_y_medicamento_se_incluyen_en_arqueo(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'farmacia')->firstOrFail());
        $this->post('/farmacia/precios/MED00001', ['precio' => '2.50'])->assertSessionHasNoErrors();
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/cantidad', ['cantidad' => 6, 'confirmacion' => 1])->assertSessionHasNoErrors();
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->post('/caja/abrir', ['monto' => 100])->assertSessionHasNoErrors();
        $this->get('/caja')->assertOk()->assertSee('Cobro de exámenes')->assertSee('Paracetamol');
        $precioExamen = (float) DB::table('SERVICIO')->where('COD_SRV', 'SRV00002')->value('PRE_SRV');
        $this->post('/caja/examenes/ORD0000001/cobrar', ['metodo' => 'MET0001', 'precio' => $precioExamen])->assertSessionHasNoErrors();
        $this->post('/caja/examenes/ORD0000001/cobrar', ['metodo' => 'MET0001', 'precio' => $precioExamen])->assertSessionHasErrors('cobro');
        $this->post('/caja/medicamentos/cobrar', ['receta' => 'REC0000001', 'detalle' => 'DRE0000001', 'cantidad' => 3, 'metodo' => 'MET0001', 'precio' => 1, 'token' => 'bda03eb1-9469-4cb3-8abd-50d5a39a3e76'])->assertSessionHasErrors('cobro');
        $this->assertDatabaseHas('LOTE_MEDICAMENTO', ['COD_LOT' => 'LOT0000002', 'CANDIS_LOT' => 40]);
        $datosMed = ['receta' => 'REC0000001', 'detalle' => 'DRE0000001', 'cantidad' => 3, 'metodo' => 'MET0001', 'precio' => 2.5, 'token' => 'bda03eb1-9469-4cb3-8abd-50d5a39a3e76'];
        $this->post('/caja/medicamentos/cobrar', $datosMed)->assertSessionHasNoErrors();
        $this->post('/caja/medicamentos/cobrar', $datosMed)->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('CAJA_MEDICAMENTO')->count());
        $this->assertDatabaseHas('CAJA_MEDICAMENTO', ['COD_DRE' => 'DRE0000001', 'CANTIDAD' => 3, 'PRECIO' => 2.5, 'MONTO' => 7.5]);
        $this->assertDatabaseHas('LOTE_MEDICAMENTO', ['COD_LOT' => 'LOT0000002', 'CANDIS_LOT' => 37]);
        $caja = DB::table('CAJA')->where('COD_EMP', 'EMP000005')->where('EST_CAJ', 'Abierta')->first();
        $resumen = app(\App\Services\CajaService::class)->resumen($caja);
        $this->assertEquals(107.5 + $precioExamen, $resumen['esperado']);
        $cobro = DB::table('CAJA_EXAMEN')->where('COD_ORD', 'ORD0000001')->first();
        $medicamento = DB::table('CAJA_MEDICAMENTO')->where('COD_DRE', 'DRE0000001')->first();
        $this->assertDatabaseHas('CAJA_MEDICAMENTO_LOTE', ['COD_CME' => $medicamento->COD_CME, 'COD_LOT' => 'LOT0000002', 'CANTIDAD' => 3]);
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->get('/paciente')->assertOk()->assertSee($medicamento->COD_CME)->assertSee($cobro->COD_CEX);
        $this->get('/paciente/cobros/medicamento/'.$medicamento->COD_CME.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/paciente/cobros/examen/'.$cobro->COD_CEX.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $otro = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first();
        unset($otro['id']); $otro['COD_PAC'] = 'PAC0000097'; $otro['CI_PAC'] = '98989897'; $otro['COR_PAC'] = 'otro97@example.test'; DB::table('PACIENTE')->insert($otro);
        $usuarioOtro = (array) DB::table('USUARIO')->where('USR_USR', 'paciente')->first();
        unset($usuarioOtro['id']); $usuarioOtro['COD_USR'] = 'USR00997'; $usuarioOtro['USR_USR'] = 'otro97'; $usuarioOtro['COD_PAC'] = 'PAC0000097'; DB::table('USUARIO')->insert($usuarioOtro);
        $this->actingAs(Usuario::where('USR_USR', 'otro97')->firstOrFail());
        $this->get('/paciente/cobros/medicamento/'.$medicamento->COD_CME.'/pdf')->assertNotFound();
        $this->get('/paciente/cobros/examen/'.$cobro->COD_CEX.'/pdf')->assertNotFound();
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->post('/caja/examenes/cobros/'.$cobro->COD_CEX.'/anular', ['motivo' => 'Error en el cobro'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CAJA_EXAMEN', ['COD_CEX' => $cobro->COD_CEX, 'ESTADO' => 'Anulado']);
        $this->assertEquals(107.5, app(\App\Services\CajaService::class)->resumen($caja)['esperado']);
        $this->post('/caja/medicamentos/'.$medicamento->COD_CME.'/anular', ['motivo' => 'Error en el cobro'])->assertSessionHasErrors('devuelto');
        $this->post('/caja/medicamentos/'.$medicamento->COD_CME.'/anular', ['motivo' => 'Error en el cobro', 'devuelto' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CAJA_MEDICAMENTO', ['COD_CME' => $medicamento->COD_CME, 'ESTADO' => 'Anulado']);
        $this->assertDatabaseHas('LOTE_MEDICAMENTO', ['COD_LOT' => 'LOT0000002', 'CANDIS_LOT' => 40]);
        $this->assertDatabaseHas('DETALLE_RECETA', ['COD_DRE' => 'DRE0000001', 'CANENT_DRE' => 0]);
        $this->assertEquals(100, app(\App\Services\CajaService::class)->resumen($caja)['esperado']);
    }

    public function test_alertas_auditoria_fuera_de_horario_y_accesos_masivos(): void
    {
        $actor = Usuario::where('USR_USR', 'medico')->firstOrFail();
        $this->travelTo(Carbon::parse('2026-11-16 21:00:00', 'America/La_Paz'));
        for ($i = 1; $i <= 10; $i++) {
            if ($i === 1) { $codigo = 'PAC0000001'; }
            else {
                $p = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first(); unset($p['id']);
                $codigo = sprintf('PAC%07d', $i); $p['COD_PAC'] = $codigo; $p['CI_PAC'] = (string) (9000000 + $i); DB::table('PACIENTE')->insert($p);
            }
            \App\Services\Auditoria::registrar('Leer', 'Media', 'DOM03', 'HISTORIA_CLINICA', 'HIS000001', 'Consulta de expediente', $actor, $codigo);
        }
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Acceso fuera horario', 'REF_NOT' => 'USR00003']);
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Consulta masiva', 'REF_NOT' => 'USR00003']);
        $this->assertSame(2, DB::table('NOTIFICACION')->where('TIP_NOT', 'Consulta masiva')->count());
    }

    public function test_cancelacion_conserva_anticipo_y_aplica_saldo_sin_duplicarlo(): void
    {
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-18', 'HOR_CIT' => '10:00:00']);
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999998', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->post('/paciente/citas/CIT0000099/cancelar')->assertRedirect();
        $this->assertDatabaseHas('SALDO_CREDITO', ['COD_CIT' => 'CIT0000099', 'COD_PAC' => 'PAC0000001', 'MONTO' => 40]);
        $this->assertDatabaseHas('PAGO', ['COD_PAG' => 'PAG9999998', 'EST_PAG' => 'Confirmado']);
        $this->post('/paciente/citas/CIT0000099/cancelar')->assertStatus(422);
        $cita = (array) DB::table('CITA')->where('COD_CIT', 'CIT0000099')->first();
        unset($cita['id']); $cita['COD_CIT'] = 'CIT0000098'; $cita['FEC_CIT'] = '2026-11-19'; $cita['EST_CIT'] = 'Pendiente de pago'; $cita['FECEXP_CIT'] = now()->addMinutes(15);
        DB::table('CITA')->insert($cita);
        $this->get('/paciente/citas/CIT0000098/pagar')->assertOk()->assertSee('Usar saldo para el anticipo');
        $this->post('/paciente/citas/CIT0000098/aplicar-saldo')->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000098', 'EST_CIT' => 'Confirmada']);
        $this->assertDatabaseHas('SALDO_APLICACION', ['COD_CIT' => 'CIT0000098', 'MONTO' => 40]);
        $this->assertSame(0.0, app(\App\Services\SaldoPacienteService::class)->disponible('PAC0000001'));
        $this->assertSame(0, DB::table('PAGO')->where('COD_CIT', 'CIT0000098')->count());
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->post('/caja/abrir', ['monto' => 0])->assertSessionHasNoErrors();
        $this->get('/caja/citas/CIT0000098/cobrar')->assertOk()->assertSee('Bs 40.00');
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->post('/paciente/citas/CIT0000098/cancelar')->assertRedirect();
        $this->assertSame(40.0, app(\App\Services\SaldoPacienteService::class)->disponible('PAC0000001'));
    }

    public function test_saldo_exige_anticipacion_y_cobertura_completa(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-17', 'HOR_CIT' => '09:59:00']);
        $this->post('/paciente/citas/CIT0000099/cancelar')->assertStatus(422);
        $this->assertDatabaseMissing('SALDO_CREDITO', ['COD_CIT' => 'CIT0000099']);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['EST_CIT' => 'Pendiente de pago', 'FECEXP_CIT' => now()->addMinutes(15)]);
        $this->post('/paciente/citas/CIT0000099/aplicar-saldo')->assertSessionHasErrors('saldo');
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'EST_CIT' => 'Pendiente de pago']);
    }

    public function test_farmacia_exige_cantidad_confirmada_y_controla_despacho_parcial(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'farmacia')->firstOrFail());
        $this->get('/farmacia/recetas/REC0000001')->assertOk()->assertSee('Por confirmar');
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/despachar', ['cantidad' => 1])->assertSessionHasErrors('receta');
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/cantidad', ['cantidad' => 6])->assertSessionHasErrors('confirmacion');
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/cantidad', ['cantidad' => 6, 'confirmacion' => 1])->assertSessionHasNoErrors();
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/cantidad', ['cantidad' => 12, 'confirmacion' => 1])->assertSessionHasErrors('cantidad');
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000001/despachar', ['cantidad' => 3])->assertSessionHasErrors('receta');
        $this->assertDatabaseHas('DETALLE_RECETA', ['COD_DRE' => 'DRE0000001', 'CANENT_DRE' => 0]);
        $this->post('/farmacia/recetas/REC0000001/items/DRE0000002/cantidad', ['cantidad' => 1, 'confirmacion' => 1])->assertSessionHasNoErrors();
        $this->post('/farmacia/precios/MED00001', ['precio' => '2.50'])->assertSessionHasNoErrors();
        $this->post('/farmacia/precios/MED00002', ['precio' => '3.00'])->assertSessionHasNoErrors();
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->post('/caja/abrir', ['monto' => 0])->assertSessionHasNoErrors();
        $datos = ['receta' => 'REC0000001', 'detalle' => 'DRE0000001', 'cantidad' => 3, 'metodo' => 'MET0001', 'precio' => '2.50', 'token' => 'a4594f50-cde9-444f-9b99-8d118aa44f01'];
        $this->post('/caja/medicamentos/cobrar', $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('DETALLE_RECETA', ['COD_DRE' => 'DRE0000001', 'CANENT_DRE' => 3]);
        $this->assertDatabaseHas('LOTE_MEDICAMENTO', ['COD_LOT' => 'LOT0000002', 'CANDIS_LOT' => 37]);
        $datos['cantidad'] = 4; $datos['token'] = 'a4594f50-cde9-444f-9b99-8d118aa44f02';
        $this->post('/caja/medicamentos/cobrar', $datos)->assertSessionHasErrors('cobro');
        $datos['cantidad'] = 3; $datos['token'] = 'a4594f50-cde9-444f-9b99-8d118aa44f03';
        $this->post('/caja/medicamentos/cobrar', $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('RECETA', ['COD_REC' => 'REC0000001', 'EST_REC' => 'Activa']);
        $datos = ['receta' => 'REC0000001', 'detalle' => 'DRE0000002', 'cantidad' => 1, 'metodo' => 'MET0001', 'precio' => '3.00', 'token' => 'a4594f50-cde9-444f-9b99-8d118aa44f04'];
        $this->post('/caja/medicamentos/cobrar', $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('RECETA', ['COD_REC' => 'REC0000001', 'EST_REC' => 'Completada']);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Confirmar cantidad', 'COD_PAC' => 'PAC0000001']);
    }

    public function test_laboratorio_publica_resultado_con_adjunto_y_alerta_critica(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->get('/laboratorio')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'laboratorio')->firstOrFail());
        $this->get('/laboratorio')->assertOk()->assertSee('Órdenes de examen');
        $this->post('/laboratorio/ordenes/ORD0000001/avanzar')->assertSessionHasErrors('orden');
        $this->post('/laboratorio/ordenes/ORD0000001/avanzar', ['muestra' => 'Sangre'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('LAB_MUESTRA', ['COD_ORD' => 'ORD0000001', 'TIPO' => 'Sangre', 'COD_EMP' => 'EMP000006']);
        $this->post('/laboratorio/ordenes/ORD0000001/avanzar')->assertSessionHasNoErrors();
        $this->post('/laboratorio/ordenes/ORD0000001/publicar')->assertSessionHasErrors('orden');
        $archivo = \Illuminate\Http\UploadedFile::fake()->createWithContent('resultado.pdf', "%PDF-1.4\nPrueba de resultado\n%%EOF");
        $this->post('/laboratorio/ordenes/ORD0000001/resultado', ['descripcion' => 'Valor de prueba', 'critico' => 1, 'archivo' => $archivo])->assertSessionHasNoErrors();
        $resultado = DB::table('RESULTADO_EXAMEN')->where('COD_ORD', 'ORD0000001')->first();
        $this->assertNotNull($resultado);
        $this->assertSame('Cargado', $resultado->EST_RES);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($resultado->ARC_RES);
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Resultado crítico', 'COD_USR' => 'USR00003', 'EST_NOT' => 'Enviada', 'REF_NOT' => 'ORD0000001']);
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->get('/paciente')->assertOk()->assertDontSee('Valor de prueba');
        $this->get('/resultados/'.$resultado->COD_RES.'/archivo')->assertNotFound();
        $this->actingAs(Usuario::where('USR_USR', 'laboratorio')->firstOrFail());
        $this->post('/laboratorio/ordenes/ORD0000001/validar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('LAB_VALIDACION', ['COD_RES' => $resultado->COD_RES, 'COD_EMP' => 'EMP000006']);
        $this->post('/laboratorio/ordenes/ORD0000001/publicar')->assertSessionHasNoErrors();
        $this->post('/laboratorio/ordenes/ORD0000001/publicar')->assertSessionHasErrors('orden');
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Resultado disponible', 'COD_USR' => 'USR00006', 'REF_NOT' => 'ORD0000001']);
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Resultado disponible', 'COD_USR' => 'USR00003', 'REF_NOT' => 'ORD0000001']);
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->get('/paciente')->assertOk()->assertSee('Valor de prueba')->assertSee('Descargar resultado adjunto');
        $this->get('/resultados/'.$resultado->COD_RES.'/archivo')->assertOk();
        $otro = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first();
        unset($otro['id']); $otro['COD_PAC'] = 'PAC0000098'; $otro['CI_PAC'] = '98989898'; $otro['COR_PAC'] = 'otro@example.test';
        DB::table('PACIENTE')->insert($otro);
        $usuarioOtro = (array) DB::table('USUARIO')->where('USR_USR', 'paciente')->first();
        unset($usuarioOtro['id']); $usuarioOtro['COD_USR'] = 'USR00998'; $usuarioOtro['USR_USR'] = 'otro'; $usuarioOtro['COD_PAC'] = 'PAC0000098';
        DB::table('USUARIO')->insert($usuarioOtro);
        $this->actingAs(Usuario::where('USR_USR', 'otro')->firstOrFail());
        $this->get('/resultados/'.$resultado->COD_RES.'/archivo')->assertNotFound();
        $this->actingAs(Usuario::where('USR_USR', 'medico')->firstOrFail());
        $this->get('/medico/consultas/CNS0000001')->assertOk()->assertSee('Descargar archivo');
        $this->get('/resultados/'.$resultado->COD_RES.'/archivo')->assertOk();
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->get('/resultados/'.$resultado->COD_RES.'/archivo')->assertForbidden();
    }

    public function test_laboratorio_advierte_si_medico_no_puede_recibir_alerta(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'laboratorio')->firstOrFail());
        DB::table('USUARIO')->where('USR_USR', 'medico')->update(['EST_USR' => 'Inactivo']);
        $archivo = \Illuminate\Http\UploadedFile::fake()->createWithContent('invalido.txt', 'contenido no admitido');
        $this->post('/laboratorio/ordenes/ORD0000003/resultado', ['descripcion' => 'Valor de prueba', 'archivo' => $archivo])->assertSessionHasErrors('archivo');
        $this->assertDatabaseMissing('RESULTADO_EXAMEN', ['COD_ORD' => 'ORD0000003']);
        $this->post('/laboratorio/ordenes/ORD0000003/resultado', ['descripcion' => 'Valor de prueba', 'critico' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('aviso');
        $this->assertDatabaseMissing('NOTIFICACION', ['TIP_NOT' => 'Resultado crítico', 'COD_USR' => 'USR00003', 'REF_NOT' => 'ORD0000003']);
    }

    public function test_caja_registra_descuento_y_evita_cobros_duplicados(): void
    {
        $this->get('/caja')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $comprobantes = \Mockery::mock(\App\Services\ComprobanteService::class);
        $comprobantes->shouldReceive('emitirCobro')->once()->andReturn(null);
        $this->app->instance(\App\Services\ComprobanteService::class, $comprobantes);
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999998', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $this->post('/caja/abrir', ['monto' => 100])->assertSessionHasNoErrors();
        $this->get('/caja')->assertOk()->assertSee('Citas con saldo por cobrar');
        $this->get('/caja/citas/CIT0000099/cobrar')->assertOk()->assertSee('Bs 40.00');
        $this->post('/caja/citas/CIT0000099/cobrar', ['metodo' => 'MET0001', 'descuento' => 21])->assertSessionHasErrors('cobro');
        $this->post('/caja/citas/CIT0000099/cobrar', ['metodo' => 'MET0001', 'descuento' => 20])->assertSessionHasNoErrors();
        $pago = DB::table('PAGO')->where('COD_CIT', 'CIT0000099')->where('TIP_PAG', 'Total')->first();
        $this->assertEquals(32, $pago->MON_PAG);
        $this->assertDatabaseHas('CAJA_DETALLE_PAGO', ['COD_PAG' => $pago->COD_PAG, 'BASE' => 40, 'PORCENTAJE' => 20, 'DESCUENTO' => 8]);
        DB::table('SERVICIO')->where('COD_SRV', 'SRV00001')->update(['PRE_SRV' => 100]);
        $cliente = \Mockery::mock(\App\Services\ComprobanteClient::class);
        $cliente->shouldReceive('emitir')->once()->with('CIT0000099', [['codSrv' => 'SRV00001', 'cantidad' => 1, 'precio' => 40.0, 'descuento' => 20]], 'EMP000005', $pago->COD_CAJ)
            ->andReturn(['nroCom' => 'COM00000099', 'monto' => 32]);
        $this->assertSame('COM00000099', (new \App\Services\ComprobanteService($cliente))->emitirCobro($pago->COD_PAG));
        DB::table('SERVICIO')->where('COD_SRV', 'SRV00001')->update(['PRE_SRV' => 80]);
        $this->get('/caja')->assertOk()->assertDontSee('caja/citas/CIT0000099/cobrar', false)->assertSee('Cobros sin comprobante');
        $this->post('/caja/citas/CIT0000099/cobrar', ['metodo' => 'MET0001', 'descuento' => 0])->assertSessionHasErrors('cobro');
        $this->post('/caja/cerrar', ['contado' => 132])->assertSessionHasErrors('cierre');
        $caja = DB::table('CAJA')->where('COD_EMP', 'EMP000005')->where('EST_CAJ', 'Abierta')->first();
        $this->assertNotNull($caja);
        DB::table('COMPROBANTE')->insert(['NRO_COM' => 'COM00000099', 'FEC_COM' => now(), 'MONTO_COM' => 32, 'EST_COM' => 'Emitido', 'COD_PAC' => 'PAC0000001', 'COD_CIT' => 'CIT0000099', 'COD_EMP' => 'EMP000005', 'COD_CAJ' => $caja->COD_CAJ]);
        $this->post('/caja/comprobantes/COM00000099/anular', ['motivo' => 'Error en el registro'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('PAGO', ['COD_PAG' => $pago->COD_PAG, 'EST_PAG' => 'Anulado']);
        $this->assertDatabaseHas('PAGO', ['COD_PAG' => 'PAG9999998', 'EST_PAG' => 'Confirmado']);
        $this->get('/caja')->assertOk()->assertSee('caja/citas/CIT0000099/cobrar', false);
        $this->post('/caja/cerrar', ['contado' => 100])->assertSessionHasNoErrors();
        $this->get('/caja')->assertOk()->assertSee('Historial de cierres')->assertSee($caja->COD_CAJ);
        $this->post('/caja/abrir', ['monto' => 0])->assertSessionHasNoErrors();
        $this->travel(1)->day();
        $this->get('/caja')->assertOk()->assertSee('caja corresponde a un día anterior');
        $this->post('/caja/citas/CIT0000099/cobrar', ['metodo' => 'MET0001', 'descuento' => 0])->assertSessionHasErrors('cobro');
    }

    public function test_enfermeria_registra_cuidados_y_protege_el_acceso(): void
    {
        DB::table('TIPO_SALA')->insert(['COD_TIS' => 'TIS999', 'DES_TIS' => 'Prueba']);
        DB::table('SALA')->insert(['COD_SAL' => 'SAL999', 'NOM_SAL' => 'Prueba', 'UBI_SAL' => 'Piso 1', 'COD_TIS' => 'TIS999']);
        DB::table('CAMA')->insert(['COD_CAM' => 'CAM0999', 'NRO_CAM' => 1, 'EST_CAM' => 'Ocupada', 'COD_SAL' => 'SAL999']);
        DB::table('INTERNACION')->insert(['COD_INT' => 'INT0000099', 'FECING_INT' => now(), 'MOT_INT' => 'Prueba', 'EST_INT' => 'Internado', 'COD_PAC' => 'PAC0000001', 'COD_CAM' => 'CAM0999', 'COD_EMP' => 'EMP000003']);
        $this->get('/enfermeria/pacientes')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'enfermeria')->firstOrFail());
        $this->get('/enfermeria/pacientes')->assertOk()->assertSee('Pacientes internados');
        $this->get('/enfermeria/pacientes/INT0000099')->assertOk()->assertSee('Paracetamol');
        $this->post('/enfermeria/pacientes/INT0000099/signos', ['sistolica' => 120, 'diastolica' => 80, 'pulso' => 75, 'temperatura' => 36.5])->assertSessionHasNoErrors();
        $this->post('/enfermeria/pacientes/INT0000099/signos', ['sistolica' => 70, 'diastolica' => 80, 'pulso' => 75, 'temperatura' => 36.5])->assertSessionHasErrors('sistolica');
        $this->post('/enfermeria/pacientes/INT0000099/nota', ['texto' => 'Paciente en observación'])->assertSessionHasNoErrors();
        $this->post('/enfermeria/pacientes/INT0000099/balance', ['ingreso' => 500, 'egreso' => 300])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('SIGNO_VITAL', ['COD_PAC' => 'PAC0000001', 'SIS_SIV' => 120]);
        $this->assertDatabaseHas('ENFERMERIA_NOTA', ['COD_INT' => 'INT0000099', 'TEXTO' => 'Paciente en observación']);
        $this->assertDatabaseHas('ENFERMERIA_BALANCE', ['COD_INT' => 'INT0000099', 'INGRESO' => 500, 'EGRESO' => 300]);
        $this->post('/enfermeria/pacientes/INT0000099/dosis', ['receta' => 'DRE0000001', 'insumo' => 'INS9999999', 'fecha' => '2026-11-17 12:00', 'unidades' => 1])->assertSessionHasErrors('insumo');
        DB::table('ENTREGANTE')->insert(['COD_ENT' => 'ENT00999', 'CI_ENT' => '9876543', 'NOM_ENT' => 'Familiar', 'APE_ENT' => 'Prueba', 'COR_ENT' => 'familiar@example.test', 'TEL_ENT' => '71234567']);
        DB::table('OTP')->insert(['COD_OTP' => 'OTP0000099', 'VAL_OTP' => 'hash', 'PRO_OTP' => 'ENTREGA_INSUMOS', 'CAN_OTP' => 'SMS', 'DST_OTP' => '71234567', 'FECEMI_OTP' => now(), 'FECEXP_OTP' => now()->addMinutes(10), 'EST_OTP' => 'Verificado', 'COD_USR' => 'USR00004']);
        DB::table('INSUMO_TERCERO')->insert(['COD_INS' => 'INS0000099', 'FEC_INS' => now(), 'DOS_INS' => '500 mg', 'CAN_INS' => 3, 'LOT_INS' => 'LOTE', 'FECVEN_INS' => '2027-01-01', 'EST_INS' => 'Verificado', 'FIR_INS' => 'firma.png', 'COD_PAC' => 'PAC0000001', 'COD_ENT' => 'ENT00999', 'COD_MED' => 'MED00001', 'COD_EMP' => 'EMP000004', 'COD_OTP' => 'OTP0000099']);
        $datos = ['receta' => 'DRE0000001', 'insumo' => 'INS0000099', 'fecha' => '2026-11-17 12:00', 'unidades' => 0.5];
        $invalido = $datos; $invalido['unidades'] = 0;
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $invalido)->assertSessionHasErrors('unidades');
        $invalido['unidades'] = 0.0001;
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $invalido)->assertSessionHasErrors('unidades');
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $datos)->assertSessionHasErrors('insumo');
        DB::table('COMPROBANTE_ENTREGA')->insert(['NRO_CEN' => 'CEN00000099', 'FEC_CEN' => now(), 'EST_CEN' => 'Emitido', 'COD_INS' => 'INS0000099', 'COD_EMP' => 'EMP000004']);
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $datos)->assertSessionHasNoErrors();
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $datos)->assertSessionHasErrors('fecha');
        $dos = DB::table('DOSIS')->where('COD_INT', 'INT0000099')->first();
        $trozo = fn (string $tipo, string $contenido): string => pack('N', strlen($contenido)).$tipo.$contenido.pack('H*', hash('crc32b', $tipo.$contenido));
        $pixeles = '';
        for ($y = 0; $y < 20; $y++) {
            $pixeles .= "\0";
            for ($x = 0; $x < 40; $x++) { $pixeles .= pack('CCCC', ($x * 13) % 256, ($y * 11) % 256, ($x * $y) % 256, 255); }
        }
        $png = "\x89PNG\r\n\x1a\n".$trozo('IHDR', pack('NNCCCCC', 40, 20, 8, 6, 0, 0, 0)).$trozo('IDAT', gzcompress($pixeles)).$trozo('IEND', '');
        $firma = 'data:image/png;base64,'.base64_encode($png);
        $derechos = ['firma' => $firma, 'paciente' => 1, 'medicamento' => 1, 'dosis' => 1, 'via' => 1, 'hora' => 1, 'unidades' => 0.75];
        $this->post('/enfermeria/dosis/'.$dos->COD_DOS.'/administrar', array_diff_key($derechos, ['hora' => 1]))->assertSessionHasErrors('hora');
        $invalida = $derechos; $invalida['firma'] = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".str_repeat('x', 220));
        $this->post('/enfermeria/dosis/'.$dos->COD_DOS.'/administrar', $invalida)->assertSessionHasErrors('firma');
        $this->post('/enfermeria/dosis/'.$dos->COD_DOS.'/administrar', $derechos)->assertSessionHasNoErrors();
        $this->post('/enfermeria/dosis/'.$dos->COD_DOS.'/administrar', $derechos)->assertStatus(422);
        $this->assertDatabaseHas('DOSIS', ['COD_DOS' => $dos->COD_DOS, 'EST_DOS' => 'Administrada']);
        $this->assertDatabaseHas('ENFERMERIA_FIRMA_DOSIS', ['COD_DOS' => $dos->COD_DOS]);
        $this->assertDatabaseHas('ENFERMERIA_UNIDADES_DOSIS', ['COD_DOS' => $dos->COD_DOS, 'PROGRAMADAS' => 0.5, 'USADAS' => 0.75]);
        $datos['fecha'] = '2026-11-17 15:00';
        $datos['unidades'] = 2.25;
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $datos)->assertSessionHasNoErrors();
        $exceso = $datos; $exceso['fecha'] = '2026-11-17 16:00'; $exceso['unidades'] = 0.001;
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $exceso)->assertSessionHasErrors('insumo');
        $pendiente = DB::table('DOSIS')->where('EST_DOS', 'Programada')->first();
        $this->post('/enfermeria/insumos/INS0000099/cierre', ['tipo' => 'Devolución', 'detalle' => 'Al familiar'])->assertSessionHasErrors('insumo');
        $dosisExcesiva = $derechos; $dosisExcesiva['unidades'] = 2.251;
        $this->post('/enfermeria/dosis/'.$pendiente->COD_DOS.'/administrar', $dosisExcesiva)->assertSessionHasErrors('unidades');
        $dosUnidades = $derechos; $dosUnidades['unidades'] = 2;
        $this->post('/enfermeria/dosis/'.$pendiente->COD_DOS.'/administrar', $dosUnidades)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ENFERMERIA_UNIDADES_DOSIS', ['COD_DOS' => $pendiente->COD_DOS, 'PROGRAMADAS' => 2.25, 'USADAS' => 2]);
        $datos['fecha'] = '2026-11-17 17:00'; $datos['unidades'] = 0.25;
        $this->post('/enfermeria/pacientes/INT0000099/dosis', $datos)->assertSessionHasNoErrors();
        $pendiente = DB::table('DOSIS')->where('EST_DOS', 'Programada')->first();
        $this->post('/enfermeria/dosis/'.$pendiente->COD_DOS.'/omitir', ['motivo' => 'Paciente rechazó'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('NOTIFICACION', ['TIP_NOT' => 'Incidencia de dosis', 'COD_USR' => 'USR00003']);
        DB::table('DOSIS')->insert(['COD_DOS' => 'DOS9999999', 'FECPRG_DOS' => now(), 'FECADM_DOS' => now(), 'DOS_DOS' => 'Indicación anterior', 'EST_DOS' => 'Administrada', 'COD_INT' => 'INT0000099', 'COD_DRE' => 'DRE0000001', 'COD_INS' => 'INS0000099', 'COD_EMP' => 'EMP000004']);
        $this->post('/enfermeria/insumos/INS0000099/cierre', ['tipo' => 'Devolución', 'detalle' => 'Al familiar'])->assertSessionHasErrors('insumo');
        $this->post('/enfermeria/dosis/DOS9999999/unidades-anteriores', ['unidades' => 0.125, 'confirmacion' => 1])->assertSessionHasNoErrors();
        $this->post('/enfermeria/insumos/INS0000099/cierre', ['tipo' => 'Devolución', 'detalle' => 'Al familiar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ENFERMERIA_CIERRE_INSUMO', ['COD_INS' => 'INS0000099', 'CANTIDAD' => 0.125]);
    }

    public function test_agenda_and_all_clinical_screens_render(): void
    {
        foreach (['/medico', '/medico?fecha=2026-11-16&vista=semana', '/medico?fecha=2026-11-16&vista=mes',
            '/medico/historias', '/medico/historias/PAC0000001', '/medico/citas/CIT0000099',
            '/medico/consultas/CNS0000001', '/medico/actividad/recetas', '/medico/actividad/examenes', '/medico/actividad/notas'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_other_roles_cannot_enter_or_write_in_the_medical_module(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'caja')->firstOrFail());
        $this->get('/medico')->assertForbidden();
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En consulta'])->assertForbidden();
    }

    public function test_reception_dashboard_and_patient_registration_prevent_duplicate_ci(): void
    {
        $this->get('/recepcion')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->get('/recepcion?fecha=2026-11-16')->assertOk()->assertSee('Recepción')->assertSee('lista de espera', false);
        $datos = ['ci' => '98765432', 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'sexo' => 'F', 'nacimiento' => '1990-01-01', 'estado_civil' => 'Soltero', 'telefono' => '71234567', 'correo' => 'ana@example.invalid', 'direccion' => 'Dirección de prueba'];
        $this->post('/recepcion/pacientes', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('PACIENTE', ['CI_PAC' => '98765432']);
        $this->post('/recepcion/pacientes', $datos)->assertSessionHasErrors('ci');
        $this->assertSame(1, DB::table('PACIENTE')->where('CI_PAC', '98765432')->count());
        $codigo = DB::table('PACIENTE')->where('CI_PAC', '98765432')->value('COD_PAC');
        $this->post('/recepcion/citas', ['paciente' => $codigo, 'especialidad' => 'ESP0001', 'fecha' => '2026-11-17', 'hora' => '08:00', 'motivo' => 'Consulta de control'])->assertSessionHasErrors('paciente');
        $this->get('/admin/usuarios')->assertForbidden();
        $this->get('/caja')->assertForbidden();
    }

    public function test_reception_sends_activation_and_patient_can_activate_existing_record_once(): void
    {
        config(['mail.default' => 'array']);
        $p = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first();
        unset($p['id']); $p['COD_PAC'] = 'PAC0000099'; $p['CI_PAC'] = '98765432'; $p['COR_PAC'] = 'nueva@example.invalid';
        DB::table('PACIENTE')->insert($p);
        $this->post('/recepcion/pacientes/PAC0000099/activacion')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/pacientes/PAC0000099/activacion')->assertRedirect()->assertSessionHasNoErrors();
        $mensajes = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $mensajes);
        $email = $mensajes->first()->getOriginalMessage();
        $this->assertSame('nueva@example.invalid', $email->getTo()[0]->getAddress());
        preg_match('/href="([^"]+)"/', $email->getHtmlBody(), $m);
        $enlace = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        auth()->logout();
        $this->get($enlace)->assertOk()->assertSee('Activa tu cuenta');
        $datos = ['clave' => 'claveSegura123', 'clave_confirmation' => 'claveSegura123', 'privacidad' => 1, 'terminos' => 1];
        $this->post($enlace, $datos)->assertRedirect(route('login'));
        $u = DB::table('USUARIO')->where('COD_PAC', 'PAC0000099')->first();
        $this->assertNotNull($u);
        $this->assertSame('98765432', $u->USR_USR);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('claveSegura123', $u->CON_USR));
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Activar cuenta', 'COD_PAC' => 'PAC0000099']);
        $this->post($enlace, $datos)->assertRedirect(route('login'));
        $this->assertSame(1, DB::table('USUARIO')->where('COD_PAC', 'PAC0000099')->count());
        $this->post('/login', ['usuario' => '98765432', 'contrasena' => 'claveSegura123'])->assertRedirect(route('panel'));
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/citas', ['paciente' => 'PAC0000099', 'especialidad' => 'ESP0001', 'fecha' => '2026-11-17', 'hora' => '08:00', 'motivo' => 'Consulta de prueba'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CITA', ['COD_PAC' => 'PAC0000099', 'EST_CIT' => 'Pendiente de pago']);
    }

    public function test_activation_rejects_expired_tampered_and_changed_email_links(): void
    {
        config(['mail.default' => 'array']);
        $p = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first();
        unset($p['id']); $p['COD_PAC'] = 'PAC0000099'; $p['CI_PAC'] = '98765432'; $p['COR_PAC'] = 'nueva@example.invalid';
        DB::table('PACIENTE')->insert($p);
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/pacientes/PAC0000099/activacion')->assertRedirect()->assertSessionHasNoErrors();
        $email = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        preg_match('/href="([^"]+)"/', $email->getHtmlBody(), $m);
        $enlace = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        auth()->logout();
        $this->get($enlace.'&alterado=1')->assertForbidden();
        DB::table('PACIENTE')->where('COD_PAC', 'PAC0000099')->update(['COR_PAC' => 'modificado@example.invalid']);
        $this->get($enlace)->assertRedirect(route('login'));
        DB::table('PACIENTE')->where('COD_PAC', 'PAC0000099')->update(['COR_PAC' => 'nueva@example.invalid']);
        $this->travel(31)->minutes();
        $this->get($enlace)->assertForbidden();
        $this->assertDatabaseMissing('USUARIO', ['COD_PAC' => 'PAC0000099']);
    }

    public function test_activation_refuses_existing_patient_accounts(): void
    {
        config(['mail.default' => 'array']);
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/pacientes/PAC0000001/activacion')->assertRedirect()->assertSessionHasErrors('activacion');
        $this->assertCount(0, \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_reception_checkin_and_paid_cancellation_protection(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/citas/CIT0000099/llegada')->assertRedirect();
        $this->assertDatabaseHas('MEDICO_ATENCION', ['COD_CIT' => 'CIT0000099', 'ESTADO' => 'En espera']);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-18']);
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999998', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $this->post('/recepcion/citas/CIT0000099/cancelar', ['motivo' => 'Solicitud del paciente'])->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'EST_CIT' => 'Cancelada']);
        $this->assertDatabaseHas('SALDO_CREDITO', ['COD_CIT' => 'CIT0000099', 'MONTO' => 40]);
    }

    public function test_reception_sends_system_reminders_only_for_future_confirmed_appointments(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/citas/CIT0000099/recordatorio')->assertStatus(422);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-17']);
        $this->post('/recepcion/citas/CIT0000099/recordatorio')->assertRedirect();
        $this->assertDatabaseHas('NOTIFICACION', ['REF_NOT' => 'CIT0000099', 'TIP_NOT' => 'Recordatorio de cita', 'CAN_NOT' => 'Sistema']);
        $this->assertDatabaseHas('AUDITORIA', ['REG_AUD' => 'CIT0000099', 'ACC_AUD' => 'Recordatorio cita']);
    }

    public function test_reception_reservation_and_insurance_validation(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->post('/recepcion/citas', ['paciente' => 'PAC0000001', 'especialidad' => 'ESP0001', 'fecha' => '2026-11-17', 'hora' => '08:00', 'motivo' => 'Consulta de control'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CITA', ['COD_PAC' => 'PAC0000001', 'FEC_CIT' => '2026-11-17', 'HOR_CIT' => '08:00:00', 'EST_CIT' => 'Pendiente de pago']);
        $this->post('/recepcion/citas', ['paciente' => 'PAC0000001', 'especialidad' => 'ESP0001', 'fecha' => '2026-11-17', 'hora' => '08:00', 'motivo' => 'Consulta duplicada'])->assertSessionHasErrors('cita');
        DB::table('ASEGURADORA')->insert(['COD_ASE' => 'ASE9999', 'DES_ASE' => 'Seguro de prueba']);
        $seguro = ['paciente' => 'PAC0000001', 'aseguradora' => 'ASE9999', 'afiliacion' => '123456', 'desde' => '2026-01-01', 'hasta' => '2026-12-31', 'estado' => 'Vigente'];
        $this->post('/recepcion/seguros', $seguro)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('SEGURO_PACIENTE', ['COD_PAC' => 'PAC0000001', 'EST_SEG' => 'Vigente']);
        $seguro['hasta'] = '2026-02-01';
        $this->post('/recepcion/seguros', $seguro)->assertStatus(422);
    }

    public function test_reception_patient_search_and_specialties_enforce_referrals(): void
    {
        $this->seed(\Database\Seeders\TarifasEspecialidadPruebaSeeder::class);
        $this->get('/recepcion/pacientes/buscar?q=Maria')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $this->get('/recepcion/pacientes/buscar?q=Ma')->assertOk()->assertJsonFragment(['COD_PAC' => 'PAC0000001']);
        $this->get('/recepcion/pacientes/buscar?q=Maria%20Prueba')->assertOk()->assertJsonFragment(['COD_PAC' => 'PAC0000001']);
        $this->get('/recepcion/pacientes/buscar?q=M')->assertExactJson([]);
        $nuevo = (array) DB::table('PACIENTE')->where('COD_PAC', 'PAC0000001')->first(); unset($nuevo['id']);
        $nuevo['COD_PAC'] = 'PAC0000099'; $nuevo['CI_PAC'] = '99999999'; DB::table('PACIENTE')->insert($nuevo);
        $this->get('/recepcion/pacientes/PAC0000099/especialidades')->assertOk()->assertJsonFragment(['codigo' => 'ESP0001'])->assertJsonMissing(['codigo' => 'ESP0003']);
        $this->get('/recepcion/pacientes/PAC0000001/especialidades')->assertOk()->assertJsonFragment(['codigo' => 'ESP0001'])->assertJsonMissing(['codigo' => 'ESP0003']);
        $this->post('/recepcion/citas', ['paciente' => 'PAC0000001', 'especialidad' => 'ESP0003', 'fecha' => '2026-11-17', 'hora' => '08:00', 'motivo' => 'Revisión'])->assertSessionHasErrors('cita');
        $emp = (array) DB::table('EMPLEADO')->where('COD_EMP', 'EMP000003')->first(); unset($emp['id']);
        $emp['COD_EMP'] = 'EMP000099'; $emp['CI_EMP'] = '99999999'; $emp['COD_ESP'] = 'ESP0003'; DB::table('EMPLEADO')->insert($emp);
        DB::table('INTERCONSULTA')->insert(['COD_CNS' => 'CNS0000001', 'COD_EMP' => 'EMP000099', 'MOTIVO' => 'Derivación de prueba', 'PRIORIDAD' => 'Normal', 'FECHA' => now(), 'ESTADO' => 'Pendiente']);
        $this->get('/recepcion/pacientes/PAC0000001/especialidades')->assertOk()->assertJsonFragment(['codigo' => 'ESP0003'])->assertJsonMissing(['codigo' => 'ESP0002'])->assertJsonMissing(['codigo' => 'ESP0001']);
    }

    public function test_login_offers_accessible_password_toggle(): void
    {
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('id="contrasena-login"', false)
            ->assertSee('id="alternar-contrasena"', false)->assertSee('aria-pressed="false"', false);
    }

    public function test_reception_assigns_doctors_in_rotation(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'recepcion')->firstOrFail());
        $emp = (array) DB::table('EMPLEADO')->where('COD_EMP', 'EMP000003')->first(); unset($emp['id']);
        $emp['COD_EMP'] = 'EMP000099'; $emp['CI_EMP'] = '99999999'; DB::table('EMPLEADO')->insert($emp);
        $usuario = (array) DB::table('USUARIO')->where('USR_USR', 'medico')->first(); unset($usuario['id']);
        $usuario['COD_USR'] = 'USR00099'; $usuario['USR_USR'] = 'medico2'; $usuario['COD_EMP'] = 'EMP000099'; DB::table('USUARIO')->insert($usuario);
        DB::table('HORARIO_MEDICO')->insert(['COD_HOR' => 'HOR99999', 'DIA_HOR' => 'Martes', 'HORINI_HOR' => '08:00:00', 'HORFIN_HOR' => '12:00:00', 'COD_EMP' => 'EMP000099', 'COD_CON' => 'CON001']);
        foreach (['08:00', '08:30', '09:00'] as $hora) {
            $this->post('/recepcion/citas', ['paciente' => 'PAC0000001', 'especialidad' => 'ESP0001', 'fecha' => '2026-11-17', 'hora' => $hora, 'motivo' => 'Consulta'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $asignados = DB::table('CITA')->where('FEC_CIT', '2026-11-17')->orderBy('HOR_CIT')->pluck('COD_EMP')->all();
        $this->assertSame(['EMP000099', 'EMP000003', 'EMP000099'], $asignados);
    }

    public function test_admin_sections_and_admission_bed_lifecycle(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'admin')->firstOrFail());
        foreach (['/admin/citas', '/admin/caja', '/admin/internacion'] as $ruta) {
            $this->get($ruta)->assertOk()->assertDontSee('En construcción');
        }
        DB::table('TIPO_SALA')->insert(['COD_TIS' => 'TIS999', 'DES_TIS' => 'Prueba']);
        DB::table('SALA')->insert(['COD_SAL' => 'SAL999', 'NOM_SAL' => 'Sala prueba', 'UBI_SAL' => 'Piso 1', 'COD_TIS' => 'TIS999']);
        DB::table('CAMA')->insert(['COD_CAM' => 'CAM9999', 'NRO_CAM' => 1, 'EST_CAM' => 'Disponible', 'COD_SAL' => 'SAL999']);
        $datos = ['paciente' => 'PAC0000001', 'cama' => 'CAM9999', 'medico' => 'EMP000003', 'motivo' => 'Ingreso de prueba'];
        $this->post('/admin/internacion', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CAMA', ['COD_CAM' => 'CAM9999', 'EST_CAM' => 'Ocupada']);
        $codigo = DB::table('INTERNACION')->where('COD_CAM', 'CAM9999')->value('COD_INT');
        $this->post('/admin/internacion', $datos)->assertStatus(422);
        $this->post('/admin/internacion/'.$codigo.'/alta')->assertRedirect();
        $this->assertDatabaseHas('CAMA', ['COD_CAM' => 'CAM9999', 'EST_CAM' => 'Limpieza']);
        $this->post('/admin/camas/CAM9999/disponible', ['estado' => 'Disponible'])->assertRedirect();
        $this->assertDatabaseHas('CAMA', ['COD_CAM' => 'CAM9999', 'EST_CAM' => 'Disponible']);
        $this->assertDatabaseHas('AUDITORIA', ['ENT_AUD' => 'INTERNACION', 'REG_AUD' => $codigo]);
    }

    public function test_admin_checkin_and_cancellation_are_audited_and_role_protected(): void
    {
        $this->post('/admin/citas/CIT0000099/llegada')->assertForbidden();
        $this->actingAs(Usuario::where('USR_USR', 'admin')->firstOrFail());
        $this->post('/admin/citas/CIT0000099/llegada')->assertRedirect();
        $this->assertDatabaseHas('MEDICO_ATENCION', ['COD_CIT' => 'CIT0000099', 'ESTADO' => 'En espera']);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-18']);
        $this->post('/admin/citas/CIT0000099/cancelar', ['motivo' => 'Solicitud del paciente'])->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'EST_CIT' => 'Cancelada']);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Cancelar cita', 'REG_AUD' => 'CIT0000099']);
    }

    public function test_admin_can_reschedule_only_into_available_doctor_hours(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'admin')->firstOrFail());
        $datos = ['fecha' => '2026-11-17', 'hora' => '03:00', 'motivo' => 'Solicitud del paciente'];
        $this->post('/admin/citas/CIT0000099/reprogramar', $datos)->assertStatus(422);
        $datos['hora'] = '08:00';
        $this->post('/admin/citas/CIT0000099/reprogramar', $datos)->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'FEC_CIT' => '2026-11-17', 'HOR_CIT' => '08:00:00', 'EST_CIT' => 'Confirmada']);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Reprogramar cita', 'REG_AUD' => 'CIT0000099']);
    }

    public function test_patient_portal_only_shows_published_results_and_own_records(): void
    {
        $this->seed(\Database\Seeders\MedicoDemoSeeder::class);
        DB::table('ORDEN_EXAMEN')->insert(['COD_ORD' => 'ORD9999999', 'FEC_ORD' => today()->toDateString(), 'IND_ORD' => 'Prueba', 'EST_ORD' => 'Listo', 'PRI_ORD' => 'Normal', 'COD_CNS' => 'CNSDM00001', 'COD_SRV' => 'SRV00002']);
        DB::table('RESULTADO_EXAMEN')->insert(['COD_RES' => 'RES9999999', 'FEC_RES' => now(), 'DES_RES' => 'Resultado de otro paciente', 'COD_ORD' => 'ORD9999999', 'EST_RES' => 'Publicado', 'CRI_RES' => 0, 'COD_EMP' => 'EMP000003']);
        DB::table('RECETA')->insert(['COD_REC' => 'REC9999999', 'FEC_REC' => today()->toDateString(), 'EST_REC' => 'Activa', 'COD_CNS' => 'CNSDM00001']);
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        DB::table('RESULTADO_EXAMEN')->insert([
            'COD_RES' => 'RES0000098', 'FEC_RES' => now(), 'DES_RES' => 'Resultado aún no publicado',
            'COD_ORD' => 'ORD0000001', 'EST_RES' => 'Cargado', 'CRI_RES' => 0, 'COD_EMP' => 'EMP000003',
        ]);
        $this->get('/paciente')->assertOk()->assertSee('Resultados de exámenes')->assertDontSee('Resultado aún no publicado')->assertDontSee('Resultado de otro paciente')
            ->assertSee('Mis recetas')->assertSee('Pagos y comprobantes')->assertSee('Navegación del paciente');
        DB::table('RESULTADO_EXAMEN')->where('COD_RES', 'RES0000098')->update(['EST_RES' => 'Publicado']);
        $this->get('/paciente')->assertOk()->assertSee('Resultado aún no publicado');
        $this->get('/paciente/recetas/REC0000001/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/paciente/recetas/REC9999999/pdf')->assertNotFound();
        $this->get('/paciente/comprobantes/COM99999999/pdf')->assertNotFound();
        $this->actingAs(Usuario::where('USR_USR', 'medico')->firstOrFail());
        $this->get('/paciente/recetas/REC0000001/pdf')->assertForbidden();
    }

    public function test_patient_cancels_only_own_unpaid_reservation(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['EST_CIT' => 'Pendiente de pago', 'FECEXP_CIT' => now()->addMinutes(15)]);
        $this->post('/paciente/citas/CIT0000099/cancelar-reserva')->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'EST_CIT' => 'Cancelada']);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Cancelar reserva', 'REG_AUD' => 'CIT0000099']);
        $this->post('/paciente/citas/CIT0000001/cancelar-reserva')->assertStatus(422);
        $this->post('/paciente/citas/NOEXISTE/cancelar-reserva')->assertNotFound();
    }

    public function test_medical_navigation_is_available_in_notifications_and_other_screens(): void
    {
        foreach (['/notificaciones', '/medico/historias', '/medico/actividad/recetas', '/medico/actividad/examenes', '/medico/actividad/notas'] as $ruta) {
            $this->get($ruta)->assertOk()->assertSee('agenda-page')->assertSee('Navegación médica')
                ->assertSee('Historias clínicas')->assertSee('Notificaciones')->assertSee('medico-agenda.css');
        }
        $this->get('/notificaciones')->assertSee('class="activo"', false)->assertSee('No tienes notificaciones.');
        $this->actingAs(Usuario::where('USR_USR', 'admin')->firstOrFail());
        $this->get('/notificaciones')->assertOk()->assertDontSee('Navegación médica');
    }

    public function test_month_calendar_opens_the_selected_days_patients_and_their_information(): void
    {
        $this->seed(MedicoDemoSeeder::class);
        $respuesta = $this->get('/medico?fecha=2026-11-16&dia=2026-11-17')->assertOk()
            ->assertViewHas('diaSeleccionado', '2026-11-17')
            ->assertSee('calendar-grid')->assertSee('data-day="2026-11-17"', false)
            ->assertSee('images/cies-logo.png');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent());
        $panel = $dom->getElementById('citas-del-dia');
        $this->assertNotNull($panel);
        $contenido = $dom->saveHTML($panel);
        $this->assertStringContainsString('Luciana (Demo)', $contenido);
        $this->assertStringContainsString('Gabriel (Demo)', $contenido);
        $this->assertStringNotContainsString('Valeria (Demo)', $contenido);
        $this->assertStringContainsString('Ver información del paciente', $contenido);
        $this->get('/medico/historias/PACDEMO005')->assertOk()->assertSee('Datos del paciente')
            ->assertSee('paciente5@demo.invalid')->assertSee('Dirección ficticia');
    }

    public function test_demo_patients_can_be_seeded_twice_without_duplicates(): void
    {
        $this->seed(MedicoDemoSeeder::class);
        $this->seed(MedicoDemoSeeder::class);
        $this->assertSame(8, DB::table('PACIENTE')->where('COD_PAC', 'like', 'PACDEMO%')->count());
        $this->assertSame(12, DB::table('CITA')->where('COD_CIT', 'like', 'CD%')->count());
        $this->assertSame(8, DB::table('CONSULTA')->where('COD_CNS', 'like', 'CNSDM%')->count());
    }

    public function test_logo_is_shared_by_public_admin_and_patient_screens(): void
    {
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('images/cies-logo.png');
        $this->actingAs(Usuario::where('USR_USR', 'admin')->firstOrFail());
        $this->get('/admin/usuarios')->assertOk()->assertSee('images/cies-logo.png');
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        $this->get('/paciente')->assertOk()->assertSee('images/cies-logo.png');
        $this->get('/paciente/citas/crear')->assertOk()->assertSee('images/cies-logo.png');
    }

    public function test_consultation_requires_start_and_is_not_duplicated(): void
    {
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999999', 'FEC_PAG' => now(), 'MON_PAG' => 80, 'TIP_PAG' => 'Total', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $datos = ['diagnostico' => 'Control clínico', 'plan' => 'Seguimiento', 'cie10' => 'I10'];
        $this->post('/medico/citas/CIT0000099/consulta', $datos)->assertStatus(422);
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En espera'])->assertRedirect();
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En consulta'])->assertRedirect();
        $this->post('/medico/citas/CIT0000099/consulta', $datos)->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'EST_CIT' => 'Atendida']);
        $this->assertDatabaseHas('DIAGNOSTICO_CONSULTA', ['CODIGO' => 'I10']);
        $this->post('/medico/citas/CIT0000099/consulta', $datos)->assertStatus(422);
        $this->assertSame(1, DB::table('CONSULTA')->where('COD_CIT', 'CIT0000099')->count());
    }

    public function test_patient_can_reschedule_24_hours_ahead_and_preserves_payment(): void
    {
        $this->actingAs(Usuario::where('USR_USR', 'paciente')->firstOrFail());
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-17', 'HOR_CIT' => '10:00:00']);
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999998', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $this->assertSame(50, config('cies.anticipo_porcentaje'));
        $this->post('/paciente/citas/CIT0000099/reprogramar', ['fecha' => '2026-11-18', 'hora' => '08:00'])->assertRedirect();
        $this->assertDatabaseHas('CITA', ['COD_CIT' => 'CIT0000099', 'FEC_CIT' => '2026-11-18']);
        $this->assertDatabaseHas('PAGO', ['COD_PAG' => 'PAG9999998', 'COD_CIT' => 'CIT0000099', 'EST_PAG' => 'Confirmado']);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['FEC_CIT' => '2026-11-17', 'HOR_CIT' => '09:59:00']);
        $this->post('/paciente/citas/CIT0000099/reprogramar', ['fecha' => '2026-11-18', 'hora' => '09:00'])->assertStatus(422);
    }

    public function test_medical_consultation_requires_remaining_payment_and_appointment_time(): void
    {
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999998', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Adelanto', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En consulta'])->assertStatus(422);
        DB::table('PAGO')->insert(['COD_PAG' => 'PAG9999999', 'FEC_PAG' => now(), 'MON_PAG' => 40, 'TIP_PAG' => 'Total', 'EST_PAG' => 'Confirmado', 'COD_CIT' => 'CIT0000099', 'COD_MET' => 'MET0001']);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['HOR_CIT' => '11:00:00']);
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En consulta'])->assertStatus(422);
        DB::table('CITA')->where('COD_CIT', 'CIT0000099')->update(['HOR_CIT' => '09:00:00']);
        $this->post('/medico/citas/CIT0000099/estado', ['estado' => 'En consulta'])->assertRedirect();
    }

    public function test_unknown_cie10_code_is_rejected(): void
    {
        $this->post('/medico/citas/CIT0000099/consulta', ['diagnostico' => 'Control', 'plan' => 'Plan', 'cie10' => 'XYZ'])
            ->assertSessionHasErrors('cie10');
    }

    public function test_prescription_is_available_in_pharmacy_and_requires_allergy_review(): void
    {
        $datos = ['items' => [['medicamento' => 'MED00001', 'dosis' => '1 tableta', 'via' => 'VIA001', 'frecuencia' => 8, 'duracion' => 3]]];
        $this->post('/medico/consultas/CNS0000001/recetas', $datos)->assertSessionHasErrors('alergias_revisadas');
        $datos['alergias_revisadas'] = 1;
        $this->post('/medico/consultas/CNS0000001/recetas', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('DETALLE_RECETA', ['FRE_DRE' => 8, 'DUR_DRE' => 3, 'CANENT_DRE' => 0]);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Emitir receta', 'COD_PAC' => 'PAC0000001']);
    }

    public function test_multiple_prescription_items_are_saved_atomically(): void
    {
        $datos = ['alergias_revisadas' => 1, 'items' => [
            ['medicamento' => 'MED00001', 'dosis' => '1 tableta', 'via' => 'VIA001', 'frecuencia' => 8, 'duracion' => 3],
            ['medicamento' => 'NOEXISTE', 'dosis' => '1 tableta', 'via' => 'VIA001', 'frecuencia' => 8, 'duracion' => 3],
        ]];
        $antes = DB::table('RECETA')->count();
        $this->post('/medico/consultas/CNS0000001/recetas', $datos)->assertSessionHasErrors('items.1.medicamento');
        $this->assertSame($antes, DB::table('RECETA')->count());
    }

    public function test_diagnostic_order_is_routed_to_laboratory_and_consultation_service_is_rejected(): void
    {
        $datos = ['servicio' => 'SRV00001', 'indicaciones' => 'Examen de control', 'prioridad' => 'Urgente'];
        $this->post('/medico/consultas/CNS0000001/examenes', $datos)->assertSessionHasErrors('servicio');
        $datos['servicio'] = 'SRV00002';
        $this->post('/medico/consultas/CNS0000001/examenes', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ORDEN_EXAMEN', ['COD_CNS' => 'CNS0000001', 'PRI_ORD' => 'Urgente', 'EST_ORD' => 'Pendiente']);
    }

    public function test_evolution_is_corrected_with_an_addendum_without_overwriting_the_note(): void
    {
        $this->post('/medico/historias/PAC0000001', ['tipo' => 'nota', 'texto' => 'Evolución original'])->assertRedirect();
        $nota = DB::table('NOTA_EVOLUCION')->first();
        $this->post('/medico/historias/PAC0000001/adendas', ['tipo' => 'nota', 'codigo' => $nota->COD_NEV, 'texto' => 'Aclaración posterior'])->assertRedirect();
        $this->assertDatabaseHas('NOTA_EVOLUCION', ['TXT_NEV' => 'Evolución original']);
        $this->assertDatabaseHas('ADENDA_EVOLUCION', ['TEXTO' => 'Aclaración posterior']);
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Adenda']);
    }

    public function test_consultation_addendum_preserves_original_diagnosis(): void
    {
        $this->post('/medico/historias/PAC0000001/adendas', ['tipo' => 'consulta', 'codigo' => 'CNS0000001', 'texto' => 'Aclaración'])->assertRedirect();
        $this->assertDatabaseHas('CONSULTA', ['COD_CNS' => 'CNS0000001', 'DIA_CNS' => 'Control general']);
        $this->assertDatabaseHas('ADENDA', ['COD_CNS' => 'CNS0000001', 'TXT_ADE' => 'Aclaración']);
    }

    public function test_allergy_and_antecedent_are_added_and_length_is_validated(): void
    {
        $this->post('/medico/historias/PAC0000001', ['tipo' => 'alergia', 'texto' => 'Penicilina', 'severidad' => 'Grave'])->assertRedirect();
        $this->post('/medico/historias/PAC0000001', ['tipo' => 'antecedente', 'texto' => 'Antecedente familiar', 'clase' => 'Familiar'])->assertRedirect();
        $this->assertDatabaseHas('ALERGIA', ['DES_ALE' => 'Penicilina', 'SEV_ALE' => 'Grave']);
        $this->post('/medico/historias/PAC0000001', ['tipo' => 'alergia', 'texto' => str_repeat('a', 61), 'severidad' => 'Leve'])->assertSessionHasErrors('texto');
        $this->get('/medico')->assertOk()->assertSee('Penicilina');
    }

    public function test_unassigned_history_requires_a_justified_and_expiring_emergency_access(): void
    {
        $p = (array) DB::table('PACIENTE')->first();
        unset($p['id']);
        $p['COD_PAC'] = 'PAC0000099';
        $p['CI_PAC'] = '99999999';
        DB::table('PACIENTE')->insert($p);
        $this->get('/medico/historias/PAC0000099')->assertForbidden();
        $this->post('/medico/emergencia', ['paciente' => 'PAC0000099', 'motivo' => 'Urgencia clínica justificada de prueba'])->assertRedirect();
        $this->get('/medico/historias/PAC0000099')->assertOk();
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Acceso emergencia', 'COD_PAC' => 'PAC0000099']);
        $this->travel(31)->minutes();
        $this->get('/medico/historias/PAC0000099')->assertForbidden();
    }

    public function test_unpublished_results_are_hidden(): void
    {
        DB::table('RESULTADO_EXAMEN')->insert([
            'COD_RES' => 'RES0000099', 'FEC_RES' => now(), 'DES_RES' => 'Resultado confidencial no publicado',
            'COD_ORD' => 'ORD0000001', 'EST_RES' => 'Cargado', 'CRI_RES' => 0, 'COD_EMP' => 'EMP000003',
        ]);
        $this->get('/medico/consultas/CNS0000001')->assertOk()->assertDontSee('Resultado confidencial no publicado');
        DB::table('RESULTADO_EXAMEN')->where('COD_RES', 'RES0000099')->update(['EST_RES' => 'Publicado']);
        $this->get('/medico/consultas/CNS0000001')->assertOk()->assertSee('Resultado confidencial no publicado');
    }

    public function test_pdf_download_returns_pdf_and_is_audited(): void
    {
        $r = $this->get('/medico/recetas/REC0000001/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertDatabaseHas('AUDITORIA', ['ACC_AUD' => 'Descargar PDF', 'REG_AUD' => 'REC0000001']);
    }

    public function test_consent_requires_a_real_png_and_acceptance(): void
    {
        $datos = ['procedimiento' => 'Procedimiento de prueba', 'informacion' => 'Información sobre riesgos beneficios y alternativas explicada.',
            'firmante' => 'Paciente de prueba', 'ci' => '12345678', 'acepta' => 1, 'firma' => 'data:image/png;base64,'.base64_encode('texto')];
        $this->post('/medico/consultas/CNS0000001/consentimientos', $datos)->assertStatus(422);
        $datos['firma'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB1kAAAAASUVORK5CYII=';
        $this->post('/medico/consultas/CNS0000001/consentimientos', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('CONSENTIMIENTO_MEDICO', ['PROCEDIMIENTO' => 'Procedimiento de prueba']);
    }

    public function test_interconsultation_grants_recipient_access_and_only_recipient_can_answer(): void
    {
        $emp = (array) DB::table('EMPLEADO')->where('COD_EMP', 'EMP000003')->first();
        unset($emp['id']);
        $emp['COD_EMP'] = 'EMP000099';
        $emp['CI_EMP'] = '99999999';
        DB::table('EMPLEADO')->insert($emp);
        $u = (array) DB::table('USUARIO')->where('USR_USR', 'medico')->first();
        unset($u['id']);
        $u['COD_USR'] = 'USR00099';
        $u['USR_USR'] = 'especialista';
        $u['COD_EMP'] = 'EMP000099';
        DB::table('USUARIO')->insert($u);
        $this->post('/medico/consultas/CNS0000001/interconsultas', ['especialista' => 'EMP000099', 'motivo' => 'Valoración especializada', 'prioridad' => 'Normal'])->assertRedirect()->assertSessionHasNoErrors();
        $i = DB::table('INTERCONSULTA')->first();
        $this->post('/medico/interconsultas/'.$i->id.'/respuesta', ['respuesta' => 'Respuesta'])->assertForbidden();
        $this->actingAs(Usuario::where('COD_USR', 'USR00099')->firstOrFail());
        $this->get('/medico/consultas/CNS0000001')->assertOk();
        $this->post('/medico/consultas/CNS0000001/recetas', [])->assertForbidden();
        $this->post('/medico/interconsultas/'.$i->id.'/respuesta', ['respuesta' => 'Respuesta del especialista'])->assertRedirect();
        $this->assertDatabaseHas('INTERCONSULTA', ['ESTADO' => 'Respondida', 'RESPUESTA' => 'Respuesta del especialista']);
    }
}
