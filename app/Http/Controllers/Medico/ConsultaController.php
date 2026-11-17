<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\MedicoService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConsultaController extends Controller
{
    public function __construct(private MedicoService $medico) {}

    public function cita(string $cita): View|RedirectResponse
    {
        $c = DB::table('CITA')->where('COD_CIT', $cita)->first();
        abort_unless($c, 404);
        abort_unless($c->COD_EMP === $this->medico->empleado(), 403);
        $p = $this->medico->paciente($c->COD_PAC);
        $h = $this->medico->historia($c->COD_PAC);
        $consulta = DB::table('CONSULTA')->where('COD_CIT', $cita)->first();
        if ($consulta) {
            return redirect()->route('medico.consultas.ver', $consulta->COD_CNS);
        }
        $this->medico->auditar('Leer', 'HISTORIA_CLINICA', $h->COD_HIS, $p->COD_PAC, 'Expediente abierto desde cita');

        return view('medico.consulta-crear', [
            'c' => $c, 'p' => $p,
            'alergias' => DB::table('ALERGIA')->where('COD_HIS', $h->COD_HIS)->get(),
            'diagnosticos' => DB::table('CATALOGO_CIE10')->orderBy('CODIGO')->limit(20)->get(),
            'totalDiagnosticos' => DB::table('CATALOGO_CIE10')->count(),
        ]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $d = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $buscar = trim($d['buscar'] ?? '');

        return response()->json(DB::table('CATALOGO_CIE10')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('CODIGO', 'like', strtoupper($buscar).'%')
                ->orWhere('DESCRIPCION', 'like', '%'.$buscar.'%')))
            ->orderBy('CODIGO')->limit(30)->get());
    }

    public function estado(Request $request, string $cita): RedirectResponse
    {
        $d = $request->validate(['estado' => ['required', Rule::in(['En espera', 'En consulta', 'No asistio'])]]);
        DB::transaction(function () use ($cita, $d) {
            $c = DB::table('CITA')->where('COD_CIT', $cita)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->COD_EMP === $this->medico->empleado(), 403);
            abort_unless($c->FEC_CIT === today()->toDateString(), 422, 'Solo se cambia la atención de las citas de hoy.');
            abort_unless($c->EST_CIT === 'Confirmada', 422, 'La cita debe estar confirmada.');
            if ($d['estado'] === 'En consulta') {
                $this->verificarPagoYHora($c);
            }
            $a = DB::table('MEDICO_ATENCION')->where('COD_CIT', $cita)->first();
            if ($d['estado'] === 'No asistio') {
                abort_if($a && $a->INICIO, 422, 'Una consulta iniciada no se marca como inasistencia.');
                abort_if(now()->lt(Carbon::parse($c->FEC_CIT.' '.$c->HOR_CIT)), 422, 'Aún no llegó la hora de la cita.');
                DB::table('CITA')->where('COD_CIT', $cita)->update(['EST_CIT' => 'No asistio']);
            } else {
                abort_if($a && $a->ESTADO === 'En consulta' && $d['estado'] === 'En espera', 422, 'La consulta ya comenzó.');
                DB::table('MEDICO_ATENCION')->updateOrInsert(['COD_CIT' => $cita], [
                    'ESTADO' => $d['estado'], 'LLEGADA' => $a?->LLEGADA ?? now(),
                    'INICIO' => $a?->INICIO ?? ($d['estado'] === 'En consulta' ? now() : null),
                ]);
            }
            $this->medico->auditar('Atencion', 'CITA', $cita, $c->COD_PAC, 'Estado: '.$d['estado']);
        });

        return back()->with('ok', 'Estado de atención actualizado.');
    }

    public function store(Request $request, string $cita): RedirectResponse
    {
        $d = $request->validate([
            'diagnostico' => ['required', 'string', 'max:200'],
            'plan' => ['required', 'string', 'max:255'],
            'cie10' => ['required', 'string', 'exists:CATALOGO_CIE10,CODIGO'],
        ]);
        $codigo = DB::transaction(function () use ($cita, $d) {
            $c = DB::table('CITA')->where('COD_CIT', $cita)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->COD_EMP === $this->medico->empleado(), 403);
            abort_unless($c->EST_CIT === 'Confirmada', 422, 'La cita debe estar confirmada y sin atender.');
            abort_unless($c->FEC_CIT === today()->toDateString(), 422, 'La consulta se registra en la fecha de la cita.');
            $this->verificarPagoYHora($c);
            abort_if(DB::table('CONSULTA')->where('COD_CIT', $cita)->exists(), 422, 'Esta consulta ya se registró.');
            $a = DB::table('MEDICO_ATENCION')->where('COD_CIT', $cita)->first();
            abort_unless($a && $a->ESTADO === 'En consulta', 422, 'Inicia la consulta desde la agenda antes de finalizarla.');
            $h = $this->medico->historia($c->COD_PAC);
            abort_unless($h->EST_HIS === 'Activa', 422, 'El expediente está inactivo.');
            $codigo = $this->medico->codigo('CONSULTA', 'COD_CNS', 'CNS');
            DB::table('CONSULTA')->insert([
                'COD_CNS' => $codigo, 'FEC_CNS' => now(), 'DIA_CNS' => $d['diagnostico'], 'PLA_CNS' => $d['plan'],
                'COD_CIT' => $cita, 'COD_HIS' => $h->COD_HIS, 'COD_EMP' => $c->COD_EMP,
            ]);
            DB::table('DIAGNOSTICO_CONSULTA')->insert([
                'COD_CNS' => $codigo, 'CODIGO' => $d['cie10'],
                'DESCRIPCION' => DB::table('CATALOGO_CIE10')->where('CODIGO', $d['cie10'])->value('DESCRIPCION'),
            ]);
            DB::table('CITA')->where('COD_CIT', $cita)->update(['EST_CIT' => 'Atendida']);
            $this->medico->auditar('Crear', 'CONSULTA', $codigo, $c->COD_PAC, 'Consulta finalizada con diagnóstico codificado');

            return $codigo;
        });

        return redirect()->route('medico.consultas.ver', $codigo)->with('ok', 'Consulta guardada. Puedes emitir receta, exámenes e interconsulta.');
    }

    private function verificarPagoYHora(object $cita): void
    {
        abort_if(now()->lt(Carbon::parse($cita->FEC_CIT.' '.$cita->HOR_CIT)), 422, 'La consulta puede iniciar a la hora programada.');
        $precio = (float) DB::table('SERVICIO')->where('COD_SRV', $cita->COD_SRV)->value('PRE_SRV');
        $pagos = DB::table('PAGO')->where('COD_CIT', $cita->COD_CIT)->where('EST_PAG', 'Confirmado');
        $pagado = (float) (clone $pagos)->sum('MON_PAG');
        $pagado += (float) DB::table('SALDO_APLICACION')->where('COD_CIT', $cita->COD_CIT)->sum('MONTO');
        $liquidado = (clone $pagos)->where('TIP_PAG', 'Total')->exists();
        abort_unless($pagado >= $precio || $liquidado, 422, 'El saldo debe pagarse en caja antes de iniciar la consulta.');
    }

    public function show(string $consulta): View
    {
        $c = $this->medico->consulta($consulta);
        $p = $this->medico->paciente($c->COD_PAC);
        $this->medico->auditar('Leer', 'CONSULTA', $consulta, $c->COD_PAC, 'Consulta de atención médica');
        $recetas = DB::table('RECETA')->where('COD_CNS', $consulta)->orderByDesc('id')->get();

        return view('medico.consulta', [
            'c' => $c, 'p' => $p, 'propia' => $c->COD_EMP === $this->medico->empleado(),
            'diagnosticos' => DB::table('DIAGNOSTICO_CONSULTA')->where('COD_CNS', $consulta)->get(),
            'alergias' => DB::table('ALERGIA')->where('COD_HIS', $c->COD_HIS)->get(),
            'recetas' => $recetas,
            'detalles' => DB::table('DETALLE_RECETA as d')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
                ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')->whereIn('d.COD_REC', $recetas->pluck('COD_REC'))
                ->select('d.*', 'm.DES_MED', 'v.DES_VIA')->get()->groupBy('COD_REC'),
            'medicamentos' => DB::table('MEDICAMENTO')->orderBy('DES_MED')->get(),
            'vias' => DB::table('VIA_ADMIN')->orderBy('DES_VIA')->get(),
            'servicios' => $this->medico->examenes()->get(),
            'ordenes' => DB::table('ORDEN_EXAMEN as o')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
                ->where('o.COD_CNS', $consulta)->select('o.*', 's.NOM_SRV')->get(),
            'resultados' => DB::table('RESULTADO_EXAMEN as r')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'r.COD_ORD')
                ->where('o.COD_CNS', $consulta)->where('r.EST_RES', 'Publicado')->select('r.*')->get()->groupBy('COD_ORD'),
            'especialistas' => DB::table('EMPLEADO as e')->join('USUARIO as u', 'u.COD_EMP', '=', 'e.COD_EMP')
                ->leftJoin('ESPECIALIDAD as s', 's.COD_ESP', '=', 'e.COD_ESP')
                ->where('u.COD_ROL', 'ROL003')->where('u.EST_USR', 'Activo')->where('e.COD_EMP', '!=', $this->medico->empleado())
                ->select('e.COD_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.DES_ESP')->distinct()->get(),
            'interconsultas' => DB::table('INTERCONSULTA as i')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
                ->where('i.COD_CNS', $consulta)->select('i.*', 'e.NOM_EMP', 'e.APE_EMP')->get(),
            'consentimientos' => DB::table('CONSENTIMIENTO_MEDICO')->where('COD_CNS', $consulta)->get(),
        ]);
    }

    public function receta(Request $request, string $consulta): RedirectResponse
    {
        $c = $this->medico->consulta($consulta, true);
        $d = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:200'],
            'alergias_revisadas' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.medicamento' => ['required', 'exists:MEDICAMENTO,COD_MED'],
            'items.*.dosis' => ['required', 'string', 'max:20'],
            'items.*.via' => ['required', 'exists:VIA_ADMIN,COD_VIA'],
            'items.*.frecuencia' => ['required', 'integer', Rule::in([1, 2, 3, 4, 6, 8, 12, 24])],
            'items.*.duracion' => ['required', 'integer', 'min:1', 'max:365'],
        ]);
        DB::transaction(function () use ($c, $d) {
            DB::table('CONSULTA')->where('COD_CNS', $c->COD_CNS)->lockForUpdate()->first();
            $codigo = $this->medico->codigo('RECETA', 'COD_REC', 'REC');
            DB::table('RECETA')->insert([
                'COD_REC' => $codigo, 'FEC_REC' => today(), 'EST_REC' => 'Activa',
                'OBS_REC' => $d['observaciones'] ?? null, 'COD_CNS' => $c->COD_CNS,
            ]);
            foreach ($d['items'] as $item) {
                DB::table('DETALLE_RECETA')->insert([
                    'COD_DRE' => $this->medico->codigo('DETALLE_RECETA', 'COD_DRE', 'DRE'),
                    'COD_REC' => $codigo, 'COD_MED' => $item['medicamento'], 'DOS_DRE' => $item['dosis'],
                    'COD_VIA' => $item['via'], 'FRE_DRE' => $item['frecuencia'], 'DUR_DRE' => $item['duracion'], 'CANENT_DRE' => 0,
                ]);
            }
            $this->medico->auditar('Emitir receta', 'RECETA', $codigo, $c->COD_PAC, 'Receta emitida tras revisión de alergias');
        });

        return back()->with('ok', 'Receta emitida y disponible en Farmacia.');
    }

    public function examen(Request $request, string $consulta): RedirectResponse
    {
        $c = $this->medico->consulta($consulta, true);
        $d = $request->validate([
            'servicio' => ['required', 'string', Rule::in($this->medico->examenes()->pluck('s.COD_SRV')->all())],
            'indicaciones' => ['required', 'string', 'max:200'],
            'prioridad' => ['required', Rule::in(['Normal', 'Urgente'])],
        ]);
        DB::transaction(function () use ($c, $d) {
            $codigo = $this->medico->codigo('ORDEN_EXAMEN', 'COD_ORD', 'ORD');
            DB::table('ORDEN_EXAMEN')->insert([
                'COD_ORD' => $codigo, 'FEC_ORD' => today(), 'IND_ORD' => $d['indicaciones'],
                'EST_ORD' => 'Pendiente', 'PRI_ORD' => $d['prioridad'], 'COD_CNS' => $c->COD_CNS, 'COD_SRV' => $d['servicio'],
            ]);
            $this->medico->auditar('Solicitar examen', 'ORDEN_EXAMEN', $codigo, $c->COD_PAC, 'Solicitud de examen diagnóstico');
        });

        return back()->with('ok', 'Orden enviada al módulo de Laboratorio e imagenología.');
    }

    public function interconsulta(Request $request, string $consulta): RedirectResponse
    {
        $c = $this->medico->consulta($consulta, true);
        $d = $request->validate([
            'especialista' => ['required', 'string', 'exists:EMPLEADO,COD_EMP'],
            'motivo' => ['required', 'string', 'max:500'], 'prioridad' => ['required', Rule::in(['Normal', 'Urgente'])],
        ]);
        abort_if($d['especialista'] === $this->medico->empleado(), 422, 'Selecciona otro especialista.');
        $usuarios = DB::table('USUARIO')->where('COD_EMP', $d['especialista'])->where('COD_ROL', 'ROL003')->where('EST_USR', 'Activo')->get();
        abort_if($usuarios->isEmpty(), 422, 'El especialista no tiene una cuenta médica activa.');
        DB::transaction(function () use ($c, $d, $usuarios) {
            $id = DB::table('INTERCONSULTA')->insertGetId([
                'COD_CNS' => $c->COD_CNS, 'COD_EMP' => $d['especialista'], 'MOTIVO' => $d['motivo'],
                'PRIORIDAD' => $d['prioridad'], 'FECHA' => now(), 'ESTADO' => 'Pendiente',
            ]);
            foreach ($usuarios as $u) {
                DB::table('NOTIFICACION')->insert([
                    'COD_NOT' => $this->medico->codigo('NOTIFICACION', 'COD_NOT', 'NOT'), 'FEC_NOT' => now(),
                    'TIP_NOT' => 'Interconsulta', 'MSG_NOT' => 'Nueva interconsulta '.$c->COD_CNS.' ('.$d['prioridad'].'). Revise su portal médico.',
                    'CAN_NOT' => 'Sistema', 'EST_NOT' => 'Enviada', 'REF_NOT' => $c->COD_CNS, 'COD_USR' => $u->COD_USR,
                ]);
            }
            $this->medico->auditar('Derivar', 'INTERCONSULTA', (string) $id, $c->COD_PAC, 'Derivación interna a especialista');
        });

        return back()->with('ok', 'Interconsulta enviada y especialista notificado.');
    }

    public function responder(Request $request, string $interconsulta): RedirectResponse
    {
        $d = $request->validate(['respuesta' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($interconsulta, $d) {
            $i = DB::table('INTERCONSULTA')->where('id', $interconsulta)->lockForUpdate()->first();
            abort_unless($i, 404);
            abort_unless($i->COD_EMP === $this->medico->empleado(), 403);
            abort_unless($i->ESTADO === 'Pendiente', 422, 'La interconsulta ya fue respondida.');
            $c = $this->medico->consulta($i->COD_CNS);
            DB::table('INTERCONSULTA')->where('id', $interconsulta)->update([
                'RESPUESTA' => $d['respuesta'], 'RESPONDIDA' => now(), 'ESTADO' => 'Respondida',
            ]);
            $this->medico->auditar('Responder', 'INTERCONSULTA', $interconsulta, $c->COD_PAC, 'Respuesta del especialista');
        });

        return back()->with('ok', 'Respuesta registrada.');
    }

    public function consentimiento(Request $request, string $consulta): RedirectResponse
    {
        $c = $this->medico->consulta($consulta, true);
        $d = $request->validate([
            'procedimiento' => ['required', 'string', 'max:200'], 'informacion' => ['required', 'string', 'min:20', 'max:10000'],
            'firmante' => ['required', 'string', 'max:100'], 'ci' => ['required', 'string', 'max:20'],
            'acepta' => ['accepted'], 'firma' => ['required', 'string', 'max:200000'],
        ]);
        abort_unless(preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/D', $d['firma'], $m), 422, 'Firma inválida.');
        $bin = base64_decode($m[1], true);
        $info = $bin === false ? false : @getimagesizefromstring($bin);
        abort_unless($info && $info[2] === IMAGETYPE_PNG && $info[0] <= 2000 && $info[1] <= 1000, 422, 'La firma debe ser una imagen PNG válida.');
        DB::transaction(function () use ($c, $d) {
            $id = DB::table('CONSENTIMIENTO_MEDICO')->insertGetId([
                'COD_CNS' => $c->COD_CNS, 'COD_EMP' => $this->medico->empleado(), 'FECHA' => now(),
                'PROCEDIMIENTO' => $d['procedimiento'], 'INFORMACION' => $d['informacion'],
                'FIRMANTE' => $d['firmante'], 'CI_FIRMANTE' => $d['ci'], 'FIRMA' => $d['firma'],
            ]);
            $this->medico->auditar('Consentimiento', 'CONSENTIMIENTO_MEDICO', (string) $id, $c->COD_PAC, 'Consentimiento con firma manuscrita digital registrada');
        });

        return back()->with('ok', 'Consentimiento informado registrado con firma.');
    }

    public function pdf(string $receta): Response
    {
        $r = DB::table('RECETA')->where('COD_REC', $receta)->first();
        abort_unless($r, 404);
        $c = $this->medico->consulta($r->COD_CNS);
        $p = $this->medico->paciente($c->COD_PAC);
        $autor = DB::table('EMPLEADO')->where('COD_EMP', $c->COD_EMP)->first();
        $items = DB::table('DETALLE_RECETA as d')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')->where('d.COD_REC', $receta)
            ->select('d.*', 'm.DES_MED', 'v.DES_VIA')->get();
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml(view('medico.receta-pdf', compact('r', 'c', 'p', 'autor', 'items'))->render());
        $pdf->setPaper('A4');
        $pdf->render();
        $this->medico->auditar('Descargar PDF', 'RECETA', $receta, $c->COD_PAC, 'Descarga de receta médica');

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="receta-'.$receta.'.pdf"']);
    }
}
