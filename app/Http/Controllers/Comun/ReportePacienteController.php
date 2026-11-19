<?php

namespace App\Http\Controllers\Comun;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\ReportePacienteService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporte del paciente: qué hizo en la clínica y cuándo.
 * Lo ven el administrador y recepción (cualquier paciente), el médico (solo sus pacientes)
 * y el propio paciente (solo el suyo, desde su panel).
 */
class ReportePacienteController extends Controller
{
    public function __construct(private ReportePacienteService $servicio)
    {
    }

    /** Buscador de pacientes para abrir su reporte. */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $usuario = auth()->user();

        $consulta = DB::table('PACIENTE as p')
            ->leftJoin('CITA as c', function ($j) {
                $j->on('c.COD_PAC', '=', 'p.COD_PAC')->where('c.EST_CIT', 'Atendida');
            })
            ->groupBy('p.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.FECNAC_PAC', 'p.SEX_PAC')
            ->selectRaw('p.COD_PAC, p.NOM_PAC, p.APE_PAC, p.CI_PAC, p.FECNAC_PAC, p.SEX_PAC, COUNT(c.id) AS VISITAS, MAX(c.FEC_CIT) AS ULTIMA');

        if ($usuario->COD_ROL === 'ROL003') {
            $consulta->whereExists(function ($s) use ($usuario) {
                $s->select(DB::raw(1))->from('CITA as c3')->whereColumn('c3.COD_PAC', 'p.COD_PAC')->where('c3.COD_EMP', $usuario->COD_EMP);
            });
        }
        if ($q !== '') {
            $consulta->where(function ($w) use ($q) {
                $w->where('p.CI_PAC', 'like', "{$q}%")
                  ->orWhere('p.COD_PAC', $q)
                  ->orWhere(DB::raw("CONCAT(p.NOM_PAC, ' ', p.APE_PAC)"), 'like', "%{$q}%");
            });
        }

        $pacientes = $consulta->orderByDesc('VISITAS')->orderBy('p.APE_PAC')->limit(40)->get();

        return view('reportes.buscar', compact('pacientes', 'q'));
    }

    public function show(string $codPac)
    {
        $this->autorizar($codPac);
        $datos = $this->servicio->datos($codPac);
        abort_unless($datos, 404, 'Paciente no encontrado.');

        Auditoria::registrar('Ver reporte pac.', 'Baja', 'DOM03', 'PACIENTE', $codPac,
            'Consulta del reporte integral del paciente', codPac: $codPac);

        return view('reportes.paciente', $datos + ['pdfUrl' => route('reportes.paciente.pdf', $codPac), 'propio' => false]);
    }

    public function pdf(string $codPac)
    {
        $this->autorizar($codPac);
        return $this->generarPdf($codPac);
    }

    /** El paciente ve y descarga su propio reporte. */
    public function mio()
    {
        $codPac = auth()->user()->COD_PAC;
        abort_unless($codPac, 403);
        $datos = $this->servicio->datos($codPac);
        abort_unless($datos, 404);

        return view('reportes.paciente', $datos + ['pdfUrl' => route('paciente.reporte.pdf'), 'propio' => true]);
    }

    public function mioPdf()
    {
        $codPac = auth()->user()->COD_PAC;
        abort_unless($codPac, 403);
        return $this->generarPdf($codPac);
    }

    private function generarPdf(string $codPac)
    {
        $datos = $this->servicio->datos($codPac);
        abort_unless($datos, 404);

        Auditoria::registrar('PDF reporte pac.', 'Baja', 'DOM03', 'PACIENTE', $codPac,
            'Descarga en PDF del reporte integral del paciente', codPac: $codPac);

        $html = view('reportes.paciente-pdf', $datos + [
            'generadoPor' => auth()->user()->USR_USR ?? '—',
            'generadoEn'  => now()->format('d/m/Y H:i'),
        ])->render();

        $op = new Options();
        $op->set('isRemoteEnabled', false);
        $op->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($op);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->getCanvas()->page_text(500, 815, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'), 7, [0.4, 0.4, 0.4]);

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_' . $codPac . '.pdf"',
        ]);
    }

    private function autorizar(string $codPac): void
    {
        $u = auth()->user();
        if (in_array($u->COD_ROL, ['ROL001', 'ROL002'], true)) {
            return;
        }
        if ($u->COD_ROL === 'ROL003') {
            $atendio = DB::table('CITA')->where('COD_PAC', $codPac)->where('COD_EMP', $u->COD_EMP)->exists();
            abort_unless($atendio, 403, 'Solo puede ver el reporte de pacientes que tienen citas con usted.');
            return;
        }
        if ($u->COD_ROL === 'ROL006' && $u->COD_PAC === $codPac) {
            return;
        }
        abort(403);
    }
}
