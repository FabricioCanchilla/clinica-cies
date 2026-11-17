<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\CitaService;
use App\Services\InsumoService;
use App\Services\Auditoria;
use App\Services\ComprobanteService;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private CitaService $citas, private InsumoService $insumos)
    {
    }

    public function index(): View
    {
        $usuario = auth()->user();

        $paciente = null;

        if ($usuario->COD_PAC) {
            $paciente = DB::table('PACIENTE')
                ->where('COD_PAC', $usuario->COD_PAC)
                ->first();
        }

        // Las citas salen de CitaService: traen médico, servicio y el vencimiento del bloqueo
        $citas = collect();

        if ($paciente) {
            $citas = $this->citas->citasDelPaciente($paciente->COD_PAC);
        }

        $recetas = collect();

        if ($paciente) {
            $recetas = DB::table('RECETA as r')
                ->join('CONSULTA as co', 'r.COD_CNS', '=', 'co.COD_CNS')
                ->join('CITA as ci', 'co.COD_CIT', '=', 'ci.COD_CIT')
                ->where('ci.COD_PAC', $paciente->COD_PAC)
                ->select(
                    'r.COD_REC',
                    'r.FEC_REC',
                    'r.EST_REC',
                    'r.OBS_REC'
                )
                ->orderBy('r.FEC_REC', 'desc')
                ->get();
        }

        $historial = collect();

        if ($paciente) {
            $historial = DB::table('CONSULTA as co')
                ->join('HISTORIA_CLINICA as h', 'co.COD_HIS', '=', 'h.COD_HIS')
                ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
                ->where('h.COD_PAC', $paciente->COD_PAC)
                ->select(
                    'co.FEC_CNS',
                    'co.DIA_CNS',
                    'co.PLA_CNS',
                    'e.NOM_EMP',
                    'e.APE_EMP'
                )
                ->orderBy('co.FEC_CNS', 'desc')
                ->get();
        }

        // RF016: comprobantes de insumos entregados por familiares
        $insumos = $paciente ? $this->insumos->comprobantesDelPaciente($paciente->COD_PAC) : collect();

        $codigo = $paciente?->COD_PAC;
        $resultados = DB::table('RESULTADO_EXAMEN as r')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'r.COD_ORD')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')->where('h.COD_PAC', $codigo)->where('r.EST_RES', 'Publicado')
            ->select('r.COD_RES', 'r.FEC_RES', 'r.DES_RES', 'r.CRI_RES', 'r.ARC_RES', 's.NOM_SRV')->orderByDesc('r.FEC_RES')->get();
        $pagos = DB::table('PAGO as p')->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')
            ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'p.COD_MET')->where('c.COD_PAC', $codigo)
            ->select('p.*', 'm.DES_MET')->orderByDesc('p.FEC_PAG')->get();
        $comprobantes = DB::table('COMPROBANTE')->where('COD_PAC', $codigo)->orderByDesc('FEC_COM')->get();
        $seguros = DB::table('SEGURO_PACIENTE as s')->join('ASEGURADORA as a', 'a.COD_ASE', '=', 's.COD_ASE')
            ->where('s.COD_PAC', $codigo)->select('s.*', 'a.DES_ASE')->get();
        $avisos = DB::table('NOTIFICACION')->where('COD_USR', $usuario->COD_USR)->orderByDesc('FEC_NOT')->limit(10)->get();

        return view('paciente.dashboard', [
            'insumos'   => $insumos,
            'paciente'  => $paciente,
            'citas'     => $citas,
            'recetas'   => $recetas,
            'historial' => $historial,
            'resultados' => $resultados,
            'pagos' => $pagos,
            'comprobantes' => $comprobantes,
            'seguros' => $seguros,
            'avisos' => $avisos,
            'saldoDisponible' => $codigo ? app(\App\Services\SaldoPacienteService::class)->disponible($codigo) : 0,
            'cobrosExamenPaciente' => $codigo ? DB::table('CAJA_EXAMEN as x')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'x.COD_ORD')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')->where('h.COD_PAC', $codigo)->select('x.*', 's.NOM_SRV')->orderByDesc('x.id')->get() : collect(),
            'cobrosMedicamentoPaciente' => $codigo ? DB::table('CAJA_MEDICAMENTO as x')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'x.COD_MED')->where('x.COD_PAC', $codigo)->select('x.*', 'm.DES_MED')->orderByDesc('x.id')->get() : collect(),
        ]);
    }

    public function comprobanteCobro(string $tipo, string $codigo): Response
    {
        $paciente = auth()->user()->COD_PAC;
        abort_unless($paciente && in_array($tipo, ['examen', 'medicamento'], true), 404);
        if ($tipo === 'examen') {
            $caja = DB::table('CAJA_EXAMEN as x')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'x.COD_ORD')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('h.COD_PAC', $paciente)->where('x.COD_CEX', $codigo)->value('x.COD_CAJ');
            $cobro = $caja ? app(\App\Services\CobroExamenService::class)->cobros($caja)->firstWhere('COD_CEX', $codigo) : null;
        } else {
            $caja = DB::table('CAJA_MEDICAMENTO')->where('COD_PAC', $paciente)->where('COD_CME', $codigo)->value('COD_CAJ');
            $cobro = $caja ? app(\App\Services\CobroMedicamentoService::class)->cobros($caja)->firstWhere('COD_CME', $codigo) : null;
        }
        abort_unless($cobro, 404);
        Auditoria::registrar('Descargar', 'Media', 'DOM04', 'COMPROBANTE', $codigo, 'Descarga de comprobante por paciente', codPac: $paciente);
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml(view('caja.comprobante-'.$tipo, compact('cobro'))->render(), 'UTF-8');
        $pdf->render();
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$codigo.'.pdf"']);
    }

    public function recetaPdf(string $receta): Response
    {
        $codigo = auth()->user()->COD_PAC;
        abort_unless($codigo, 403);
        $r = DB::table('RECETA as r')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('r.COD_REC', $receta)->where('h.COD_PAC', $codigo)->select('r.*')->first();
        abort_unless($r, 404);
        $c = DB::table('CONSULTA')->where('COD_CNS', $r->COD_CNS)->first();
        $p = DB::table('PACIENTE')->where('COD_PAC', $codigo)->first();
        $autor = DB::table('EMPLEADO')->where('COD_EMP', $c->COD_EMP)->first();
        $items = DB::table('DETALLE_RECETA as d')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')->where('d.COD_REC', $receta)
            ->select('d.*', 'm.DES_MED', 'v.DES_VIA')->get();
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml(view('medico.receta-pdf', compact('r', 'c', 'p', 'autor', 'items'))->render());
        $pdf->setPaper('A4');
        $pdf->render();
        Auditoria::registrar('Descargar PDF', 'Media', 'DOM03', 'RECETA', $receta, 'Paciente descargó su receta', codPac: $codigo);
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="receta-'.$receta.'.pdf"']);
    }

    public function comprobantePdf(string $numero, ComprobanteService $servicio): Response
    {
        abort_unless(DB::table('COMPROBANTE')->where('COD_PAC', auth()->user()->COD_PAC)->where('NRO_COM', $numero)->exists(), 404);
        try {
            $pdf = $servicio->pdf($numero);
        } catch (\DomainException $e) {
            abort(503, 'El servicio de comprobantes no está disponible. Intenta más tarde.');
        }
        Auditoria::registrar('Descargar PDF', 'Media', 'DOM04', 'COMPROBANTE', $numero, 'Paciente descargó su comprobante', codPac: auth()->user()->COD_PAC);
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="comprobante.pdf"']);
    }
}
