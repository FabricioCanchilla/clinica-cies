<?php

namespace App\Http\Controllers\Comun;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\HistoriaClinicaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RF027 / RF010: informe adjunto (PDF o imagen) de un resultado de examen.
 * - Laboratorio: siempre (es quien lo carga y valida).
 * - Médico: solo publicado y si es tratante o abrió un acceso de emergencia.
 * - Paciente: solo publicado y si el resultado es suyo.
 * Cada descarga queda en auditoría (RF047, dominio clínico).
 */
class ResultadoArchivoController extends Controller
{
    public function __construct(private HistoriaClinicaService $historias) {}

    public function show(string $codRes)
    {
        $r = DB::table('RESULTADO_EXAMEN as r')
            ->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'r.COD_ORD')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('r.COD_RES', $codRes)
            ->first(['r.COD_RES', 'r.ARC_RES', 'r.EST_RES', 'r.COD_ORD', 'h.COD_PAC']);

        abort_if(!$r || !$r->ARC_RES || !Storage::disk('local')->exists($r->ARC_RES), 404, 'El resultado no tiene archivo adjunto.');

        $u = auth()->user();
        $permitido = match ($u->COD_ROL) {
            'ROL007' => true,
            'ROL006' => $r->EST_RES === 'Publicado' && $u->COD_PAC === $r->COD_PAC,
            'ROL003' => $r->EST_RES === 'Publicado' && $u->COD_EMP
                        && ($this->historias->medicoTratante($u->COD_EMP, $r->COD_PAC)
                            || in_array($r->COD_PAC, session('acceso_emergencia', []), true)),
            default  => false,
        };
        abort_unless($permitido, 403, 'No tienes acceso a este resultado.');

        Auditoria::registrar('Descargar resultado', 'Baja', 'DOM03', 'RESULTADO_EXAMEN', $r->COD_RES,
            "Descarga del informe adjunto de la orden {$r->COD_ORD}", codPac: $r->COD_PAC);

        $ext = pathinfo($r->ARC_RES, PATHINFO_EXTENSION) ?: 'pdf';

        return response()->file(Storage::disk('local')->path($r->ARC_RES), [
            'Content-Disposition' => 'inline; filename="resultado_' . $r->COD_RES . '.' . $ext . '"',
        ]);
    }
}
