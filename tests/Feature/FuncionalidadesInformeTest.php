<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarPermiso;
use App\Models\Usuario;
use App\Services\EnfermeriaService;
use App\Support\Parametros;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de integración sobre la base MySQL de demostración (docker compose). Cada prueba corre en una
 * transacción que se revierte. Si MySQL no está disponible, se omiten.
 *
 *   docker exec clinica_cies_app php artisan test --filter=FuncionalidadesInformeTest
 */
class FuncionalidadesInformeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'clinica_cies',
            'database.connections.mysql.host' => env('DB_HOST_PRUEBAS', getenv('DB_HOST') ?: '127.0.0.1'),
        ]);
        DB::purge('mysql');
        try {
            DB::connection('mysql')->getPdo();
            if (!DB::getSchemaBuilder()->hasTable('PARAMETRO')) {
                $this->markTestSkipped('Falta el esquema v6 (05_ajustes_v6.sql).');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL de demostración no disponible: ' . $e->getMessage());
        }
        DB::connection('mysql')->beginTransaction();
        VerificarPermiso::limpiarCache();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('mysql')->rollBack();
        } catch (\Throwable) {
        }
        VerificarPermiso::limpiarCache();
        parent::tearDown();
    }

    private function como(string $usuario): Usuario
    {
        $u = Usuario::where('USR_USR', $usuario)->firstOrFail();
        $this->actingAs($u);
        return $u;
    }

    public function test_pantallas_nuevas_responden_por_rol(): void
    {
        $this->como('admin');
        foreach (['parametros', 'especialidades', 'tarifas', 'permisos', 'respaldos'] as $s) {
            $this->get(route('admin.configuracion', ['seccion' => $s]))->assertOk();
        }

        $this->como('medico');
        $codPac = DB::table('CITA')->where('COD_EMP', 'EMP000003')->value('COD_PAC');
        $this->get(route('medico.historia.buscar', ['q' => 'a']))->assertOk();
        $this->get(route('medico.historia', $codPac))->assertOk()->assertSee('Consultas y adendas');
        $this->get(route('medico.historia.consentimiento', $codPac))->assertOk();
        $this->get(route('medico.interconsultas'))->assertOk();
        $this->getJson(route('medico.cie10', ['q' => 'J06']))->assertOk()->assertJsonFragment(['COD_CIE' => 'J06.9']);

        $this->como('enfermeria');
        $this->get(route('enfermeria.internados'))->assertOk();
        $codInt = DB::table('INTERNACION')->where('EST_INT', 'Internado')->value('COD_INT');
        if ($codInt) {
            $this->get(route('enfermeria.internaciones.show', $codInt))->assertOk()->assertSee('5 correctos');
        }

        $this->como('farmacia');
        $this->get(route('farmacia.ventas'))->assertOk();

        // RN09: un rol no entra al panel de otro
        $this->get(route('admin.configuracion'))->assertForbidden();
    }

    public function test_pantalla_principal_de_cada_rol_trae_graficos(): void
    {
        $rutas = ['admin' => 'admin.dashboard', 'recepcion' => 'recepcion.dashboard', 'medico' => 'medico.dashboard',
                  'enfermeria' => 'enfermeria.internados', 'caja' => 'caja.dashboard', 'paciente' => 'paciente.dashboard',
                  'laboratorio' => 'laboratorio.dashboard', 'farmacia' => 'farmacia.dashboard', 'auditor' => 'admin.auditoria'];
        foreach ($rutas as $usuario => $ruta) {
            $this->como($usuario);
            $this->get(route($ruta))->assertOk()->assertSee('chart.umd.min.js', false);
        }
        // Enfermería entra directo a pacientes internados
        $this->como('enfermeria');
        $this->get(route('panel'))->assertRedirect(route('enfermeria.internados'));
    }

    public function test_portal_publico_y_agendar_cita(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Tejada Sorzano')->assertSee(route('agendar'), false);
        foreach (['nosotros' => 'Misión', 'servicios' => 'Laboratorio e imagenología', 'investigacion' => 'Proyectos vigentes',
                  'ubicacion' => 'Red CIES en La Paz', 'contacto' => '800 11 2437'] as $pag => $texto) {
            $this->get(route('publico.' . $pag))->assertOk()->assertSee($texto);
        }
        // Sin sesión: lleva al acceso y, tras iniciar sesión el paciente, al formulario de la cita
        $this->get(route('agendar'))->assertRedirect(route('login') . '?acceso=agendar');
        $this->assertSame(route('paciente.citas.crear'), session('url.intended'));
        $this->get(route('login') . '?acceso=agendar')->assertOk()->assertSee('Para agendar tu cita');

        $this->como('paciente');
        $this->get(route('agendar'))->assertRedirect(route('paciente.citas.crear'));
        $this->como('medico');
        $this->get(route('agendar'))->assertRedirect(route('panel'));
    }

    public function test_historia_sin_relacion_pide_acceso_de_emergencia_auditado(): void
    {
        $this->como('medico');
        // Paciente nuevo sin citas, internaciones ni derivaciones con este médico
        $ajeno = 'PACTEST01';
        DB::table('PACIENTE')->insert(['COD_PAC' => $ajeno, 'CI_PAC' => '99999901', 'NOM_PAC' => 'Prueba', 'APE_PAC' => 'Emergencia',
            'SEX_PAC' => 'F', 'FECNAC_PAC' => '1990-01-01', 'ESTCIV_PAC' => 'Soltera', 'TEL_PAC' => '70000000',
            'COR_PAC' => 'prueba@example.com', 'DIR_PAC' => 'La Paz']);
        $this->get(route('medico.historia', $ajeno))->assertOk()->assertSee('Acceso de emergencia');
        $this->post(route('medico.historia.emergencia', $ajeno), ['motivo' => 'Paciente inconsciente en emergencias'])
            ->assertRedirect(route('medico.historia', $ajeno));
        $this->assertTrue(DB::table('AUDITORIA')->where('ACC_AUD', 'Acceso emergencia')->where('REG_AUD', $ajeno)->where('CRI_AUD', 'Alta')->exists());
        $this->get(route('medico.historia', $ajeno))->assertOk()->assertSee('acceso de emergencia');
    }

    public function test_adenda_no_modifica_la_consulta(): void
    {
        $this->como('medico');
        $cns = DB::table('CONSULTA')->where('COD_EMP', 'EMP000003')->first();
        $this->post(route('medico.historia.adenda', $cns->COD_CNS), ['texto' => 'Se corrige: la evolución fue favorable.'])->assertSessionHas('ok');
        $this->assertSame($cns->DIA_CNS, DB::table('CONSULTA')->where('COD_CNS', $cns->COD_CNS)->value('DIA_CNS'));
        $this->assertTrue(DB::table('ADENDA')->where('COD_CNS', $cns->COD_CNS)->where('COD_EMP', 'EMP000003')->exists());
    }

    public function test_atencion_exige_cie10_y_registra_derivacion(): void
    {
        $this->como('medico');
        $codCit = DB::table('CITA')->where('COD_EMP', 'EMP000003')->where('EST_CIT', 'Confirmada')->value('COD_CIT');
        if (!$codCit) {
            $this->markTestSkipped('No hay citas confirmadas del médico de prueba.');
        }
        $base = ['diagnostico' => 'Faringitis', 'plan' => 'Reposo e hidratación', 'evolucion' => 'Paciente estable'];
        $this->post(route('medico.atender.guardar', $codCit), $base)->assertSessionHasErrors('cie');
        $this->assertNotSame('Atendida', DB::table('CITA')->where('COD_CIT', $codCit)->value('EST_CIT'));

        $this->post(route('medico.atender.guardar', $codCit), $base + [
            'cie' => 'J02.9', 'deriv_esp' => 'ESP0011', 'deriv_tipo' => 'Interconsulta',
            'deriv_prioridad' => 'Urgente', 'deriv_motivo' => 'Amigdalitis recurrente, valorar amigdalectomía',
        ])->assertSessionHas('ok');
        $this->assertSame('Atendida', DB::table('CITA')->where('COD_CIT', $codCit)->value('EST_CIT'));
        $cns = DB::table('CONSULTA')->where('COD_CIT', $codCit)->first();
        $this->assertSame('J02.9', $cns->COD_CIE);
        $this->assertTrue(DB::table('DERIVACION')->where('COD_CNS', $cns->COD_CNS)->where('COD_ESP', 'ESP0011')->where('EST_DER', 'Pendiente')->exists());
    }

    public function test_consentimiento_requiere_firma(): void
    {
        Storage::fake('local');
        $this->como('medico');
        $codPac = DB::table('CITA')->where('COD_EMP', 'EMP000003')->value('COD_PAC');
        $datos = ['procedimiento' => 'Colocación de DIU', 'riesgos' => 'Dolor, sangrado, expulsión; alternativas: implante o píldora.',
                  'firmante' => 'Paciente de prueba', 'acepta' => '1'];
        $this->post(route('medico.historia.consentimiento.guardar', $codPac), $datos + ['firma' => 'x'])->assertSessionHasErrors('firma');

        $png = self::pngDePrueba();
        $this->post(route('medico.historia.consentimiento.guardar', $codPac), $datos + ['firma' => 'data:image/png;base64,' . base64_encode($png)])
            ->assertRedirect(route('medico.historia', $codPac));
        $c = DB::table('CONSENTIMIENTO')->where('COD_PAC', $codPac)->orderByDesc('id')->first();
        Storage::disk('local')->assertExists($c->FIR_CNT);
    }

    /** PNG válido sin depender de la extensión GD (trazo aleatorio en escala de grises). */
    private static function pngDePrueba(int $w = 120, int $h = 40): string
    {
        $raw = '';
        for ($y = 0; $y < $h; $y++) {
            $raw .= chr(0);
            for ($x = 0; $x < $w; $x++) {
                $raw .= chr(abs($y - (int) ($h / 2 + 10 * sin($x / 9))) < 2 ? 0 : mt_rand(230, 255));
            }
        }
        $chunk = fn ($t, $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
        return chr(0x89) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10) . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 0, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
    }

    public function test_signos_vitales_validan_rn40(): void
    {
        $this->como('enfermeria');
        $codInt = DB::table('INTERNACION')->where('EST_INT', 'Internado')->value('COD_INT');
        if (!$codInt) {
            $this->markTestSkipped('No hay internados.');
        }
        $url = route('enfermeria.internaciones.signos', $codInt);
        $this->post($url, ['sistolica' => 80, 'diastolica' => 90, 'pulso' => 70, 'temperatura' => 36.5])->assertSessionHasErrors('signos');
        $this->post($url, ['sistolica' => 120, 'diastolica' => 80, 'pulso' => 70, 'temperatura' => 46])->assertSessionHasErrors('signos');
        $this->post($url, ['sistolica' => 120, 'diastolica' => 80, 'pulso' => 72, 'temperatura' => 36.6])->assertSessionHas('ok');
    }

    public function test_dosis_cinco_correctos_y_omision_con_incidencia(): void
    {
        $actor = $this->como('enfermeria');
        $int = DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->where('i.EST_INT', 'Internado')->first(['i.*', 'p.CI_PAC']);
        if (!$int) {
            $this->markTestSkipped('No hay internados.');
        }
        // Indicación activa para el internado
        $cit = DB::table('CITA')->where('COD_PAC', $int->COD_PAC)->value('COD_CIT')
            ?? DB::table('CITA')->value('COD_CIT');
        $his = app(\App\Services\HistoriaClinicaService::class)->historia($int->COD_PAC, true);
        DB::table('CONSULTA')->insert(['COD_CNS' => 'CNSTEST01', 'FEC_CNS' => now(), 'DIA_CNS' => 'Neumonía', 'COD_CIE' => 'J18.9',
            'PLA_CNS' => 'Antibiótico IV', 'COD_CIT' => $cit, 'COD_HIS' => $his->COD_HIS, 'COD_EMP' => 'EMP000003']);
        DB::table('RECETA')->insert(['COD_REC' => 'RECTEST01', 'FEC_REC' => now()->toDateString(), 'EST_REC' => 'Activa', 'COD_CNS' => 'CNSTEST01']);
        DB::table('DETALLE_RECETA')->insert(['COD_DRE' => 'DRETEST01', 'DOS_DRE' => '1 g', 'FRE_DRE' => 12, 'DUR_DRE' => 3,
            'CANENT_DRE' => 0, 'COD_REC' => 'RECTEST01', 'COD_MED' => 'MED00009', 'COD_VIA' => 'VIA002']);

        $n = app(EnfermeriaService::class)->programarDosis($int->COD_INT, 'DRETEST01', Carbon::now(), $actor);
        $this->assertSame(6, $n);
        $dosis = DB::table('DOSIS')->where('COD_DRE', 'DRETEST01')->orderBy('FECPRG_DOS')->get();

        // Vía equivocada → no se administra y queda auditado con criticidad Alta
        $this->post(route('enfermeria.dosis.administrar', $dosis[0]->COD_DOS), [
            'paciente' => $int->COD_PAC, 'medicamento' => 'MED00009', 'dosis' => '1 g', 'via' => 'VIA001',
        ])->assertSessionHasErrors('dosis');
        $this->assertSame('Programada', DB::table('DOSIS')->where('COD_DOS', $dosis[0]->COD_DOS)->value('EST_DOS'));

        // Los 5 correctos (pulsera por CI) → Administrada
        $this->post(route('enfermeria.dosis.administrar', $dosis[0]->COD_DOS), [
            'paciente' => $int->CI_PAC, 'medicamento' => 'med00009', 'dosis' => '1 G', 'via' => 'VIA002',
        ])->assertSessionHas('ok');
        $this->assertSame('Administrada', DB::table('DOSIS')->where('COD_DOS', $dosis[0]->COD_DOS)->value('EST_DOS'));

        // Hora fuera de la ventana de ±30 min → bloqueada
        $this->post(route('enfermeria.dosis.administrar', $dosis[1]->COD_DOS), [
            'paciente' => $int->COD_PAC, 'medicamento' => 'MED00009', 'dosis' => '1 g', 'via' => 'VIA002',
        ])->assertSessionHasErrors('dosis');

        // Omisión → incidencia + notificación al médico tratante
        $this->post(route('enfermeria.dosis.omitir', $dosis[1]->COD_DOS), ['motivo' => 'Paciente en ayunas para cirugía'])->assertSessionHas('ok');
        $this->assertSame('Omitida', DB::table('DOSIS')->where('COD_DOS', $dosis[1]->COD_DOS)->value('EST_DOS'));
        $this->assertTrue(DB::table('INCIDENCIA_DOSIS')->where('COD_DOS', $dosis[1]->COD_DOS)->exists());
        $medUsr = DB::table('USUARIO')->where('COD_EMP', $int->COD_EMP)->value('COD_USR');
        $this->assertTrue(DB::table('NOTIFICACION')->where('COD_USR', $medUsr)->where('TIP_NOT', 'Dosis omitida')->exists());
    }

    public function test_notas_balance_y_cambio_de_turno(): void
    {
        $this->como('enfermeria');
        $codInt = DB::table('INTERNACION')->where('EST_INT', 'Internado')->value('COD_INT');
        if (!$codInt) {
            $this->markTestSkipped('No hay internados.');
        }
        $antes = app(EnfermeriaService::class)->balance($codInt)['saldo'];
        $this->post(route('enfermeria.internaciones.balance', $codInt), ['tipo' => 'Ingreso', 'concepto' => 'Intravenoso', 'volumen' => 1000])->assertSessionHas('ok');
        $this->post(route('enfermeria.internaciones.balance', $codInt), ['tipo' => 'Egreso', 'concepto' => 'Orina', 'volumen' => 600])->assertSessionHas('ok');
        $this->post(route('enfermeria.internaciones.balance', $codInt), ['tipo' => 'Egreso', 'concepto' => 'Oral', 'volumen' => 100])->assertSessionHasErrors('balance');
        $b = app(EnfermeriaService::class)->balance($codInt);
        $this->assertSame($antes + 400, $b['saldo']);
        $this->post(route('enfermeria.internaciones.nota', $codInt), ['tipo' => 'Cambio de turno', 'texto' => app(EnfermeriaService::class)->borradorTurno($codInt)])
            ->assertSessionHas('ok');
        $this->assertTrue(DB::table('NOTA_ENFERMERIA')->where('COD_INT', $codInt)->where('TIP_NEN', 'Cambio de turno')->exists());
    }

    public function test_venta_en_mostrador_peps_y_anulacion_devuelve_stock(): void
    {
        $this->como('farmacia');
        $stock = fn () => (int) DB::table('LOTE_MEDICAMENTO')->where('COD_MED', 'MED00001')->sum('CANDIS_LOT');
        $antes = $stock();
        $loteQueVenceAntes = DB::table('LOTE_MEDICAMENTO')->where('COD_MED', 'MED00001')->where('CANDIS_LOT', '>', 0)
            ->where('FECVEN_LOT', '>=', now()->toDateString())->orderBy('FECVEN_LOT')->value('COD_LOT');

        $this->post(route('farmacia.ventas.guardar'), [
            'tipo' => 'Ambulante', 'metodo' => 'MET0001', 'cliente' => 'Cliente prueba',
            'items' => [['med' => 'MED00001', 'cantidad' => 3]],
        ])->assertSessionHas('ok');
        $v = DB::table('VENTA')->orderByDesc('id')->first();
        $this->assertSame($antes - 3, $stock());
        $this->assertEquals(1.50, (float) $v->TOT_VEN);
        $this->assertSame($loteQueVenceAntes, DB::table('DETALLE_VENTA')->where('NRO_VEN', $v->NRO_VEN)->value('COD_LOT'));
        $this->get(route('farmacia.ventas.pdf', $v->NRO_VEN))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('farmacia.ventas.anular', $v->NRO_VEN), ['motivo' => 'Error de cobro'])->assertSessionHas('ok');
        $this->assertSame($antes, $stock());
        $this->assertSame('Anulada', DB::table('VENTA')->where('NRO_VEN', $v->NRO_VEN)->value('EST_VEN'));

        // Consumo cargado a la cuenta de un hospitalizado
        $codInt = DB::table('INTERNACION')->where('EST_INT', 'Internado')->value('COD_INT');
        if ($codInt) {
            $this->post(route('farmacia.ventas.guardar'), [
                'tipo' => 'Hospitalizado', 'internacion' => $codInt, 'items' => [['med' => 'MED00004', 'cantidad' => 2]],
            ])->assertSessionHas('ok');
            $this->assertTrue(DB::table('VENTA')->where('COD_INT', $codInt)->where('TIP_VEN', 'Hospitalizado')->exists());
        }

        // Stock insuficiente: no se vende
        $this->post(route('farmacia.ventas.guardar'), [
            'tipo' => 'Ambulante', 'metodo' => 'MET0001', 'items' => [['med' => 'MED00001', 'cantidad' => 999]],
        ])->assertSessionHasErrors('venta');
    }

    public function test_permisos_por_rol_se_aplican(): void
    {
        $this->como('admin');
        $this->post(route('admin.configuracion.permisos'), ['m' => [
            'ROL001' => ['PER0001', 'PER0005'], 'ROL008' => ['PER0010'], // farmacia sin venta en mostrador
        ]])->assertSessionHas('ok');
        $this->assertFalse(DB::table('ROL_PERMISO')->where('COD_ROL', 'ROL008')->where('COD_PER', 'PER0016')->exists());

        VerificarPermiso::limpiarCache();
        $this->como('farmacia');
        $this->get(route('farmacia.ventas'))->assertForbidden();
        $this->get(route('farmacia.lotes'))->assertOk();
    }

    public function test_parametros_cambian_la_configuracion(): void
    {
        $this->como('admin');
        $p = DB::table('PARAMETRO')->pluck('VAL_PAR', 'COD_PAR')->all();
        $p['anticipo_porcentaje'] = 40;
        $this->post(route('admin.configuracion.parametros'), ['p' => $p])->assertSessionHas('ok');
        Parametros::olvidar();
        Parametros::aplicar();
        $this->assertSame(40, config('cies.anticipo_porcentaje'));
        $p['minutos_inactividad'] = 90; // fuera de 5–60 (RN06)
        $this->post(route('admin.configuracion.parametros'), ['p' => $p])->assertSessionHasErrors();
        Parametros::olvidar();
    }
    public function test_resultado_con_adjunto_y_acceso_por_rol(): void
    {
        Storage::fake('local');
        $codOrd = DB::table('ORDEN_EXAMEN as o')->leftJoin('RESULTADO_EXAMEN as r', 'r.COD_ORD', '=', 'o.COD_ORD')
            ->whereNull('r.COD_RES')->whereIn('o.EST_ORD', ['Pendiente', 'Muestra tomada', 'En proceso'])->value('o.COD_ORD');
        if (!$codOrd) {
            $this->markTestSkipped('No hay órdenes sin resultado en la demo.');
        }
        DB::table('ORDEN_EXAMEN')->where('COD_ORD', $codOrd)->update(['EST_ORD' => 'En proceso']);

        $this->como('laboratorio');
        // RF027: solo PDF o imagen
        $this->post(route('laboratorio.resultado.guardar', $codOrd), [
            'descripcion' => 'Hemograma normal', 'archivo' => \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10),
        ])->assertSessionHasErrors('archivo');

        $this->post(route('laboratorio.resultado.guardar', $codOrd), [
            'descripcion' => 'Hemograma normal', 'archivo' => \Illuminate\Http\UploadedFile::fake()->create('informe.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('ok');
        $res = DB::table('RESULTADO_EXAMEN')->where('COD_ORD', $codOrd)->first();
        $this->assertNotNull($res->ARC_RES);
        Storage::disk('local')->assertExists($res->ARC_RES);
        $this->get(route('resultados.archivo', $res->COD_RES))->assertOk();

        // El paciente dueño no lo ve hasta que se publique; otro paciente nunca
        $codPac = DB::table('ORDEN_EXAMEN as o')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('o.COD_ORD', $codOrd)->value('h.COD_PAC');
        $dueno = Usuario::where('COD_PAC', $codPac)->first();
        $otro  = Usuario::whereNotNull('COD_PAC')->where('COD_PAC', '!=', $codPac)->first();
        if ($dueno) {
            $this->actingAs($dueno)->get(route('resultados.archivo', $res->COD_RES))->assertForbidden();
            DB::table('RESULTADO_EXAMEN')->where('COD_RES', $res->COD_RES)->update(['EST_RES' => 'Publicado']);
            $this->actingAs($dueno)->get(route('resultados.archivo', $res->COD_RES))->assertOk();
        }
        if ($otro) {
            $this->actingAs($otro)->get(route('resultados.archivo', $res->COD_RES))->assertForbidden();
        }
    }
}
