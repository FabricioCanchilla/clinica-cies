<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AuditoriaController extends Controller
{
    private const CRITICIDADES = ['Baja', 'Media', 'Alta', 'Crítica'];

    // Tope de filas para el PDF (dompdf consume mucha memoria). El Excel no tiene tope.
    private const LIMITE_PDF = 2000;

    public function index(Request $request)
    {
        [$query, $f, $dominios, $roles, $acciones] = $this->consulta($request);

        $registros = $query->simplePaginate(20)->withQueryString();

        return view('admin.auditoria.index', [
            'registros' => $registros,
            'dominios'  => $dominios,
            'roles'     => $roles,
            'acciones'  => $acciones,
            'f'         => $f,
        ]);
    }

    public function excel(Request $request)
    {
        [$query, $f, $dominios, $roles] = $this->consulta($request);

        $this->auditarExportacion('Exportar Excel', $this->resumenFiltros($f, $dominios, $roles));

        $nombre = 'auditoria_cies_' . now()->format('Ymd_His') . '.xlsx';
        $ruta   = tempnam(sys_get_temp_dir(), 'aud_');

        $writer = new Writer();
        $writer->openToFile($ruta);

        $writer->addRow(Row::fromValues([
            'Código', 'Fecha', 'Usuario', 'Rol', 'Acción', 'Criticidad', 'Dominio',
            'Entidad', 'Registro', 'Paciente', 'CI paciente', 'Detalle', 'IP', 'Dispositivo',
        ]));

        foreach ($query->cursor() as $r) {
            $writer->addRow(Row::fromValues([
                $r->COD_AUD,
                \Carbon\Carbon::parse($r->FEC_AUD)->format('d/m/Y H:i:s'),
                $r->USR_USR,
                $r->DES_ROL,
                $r->ACC_AUD,
                $r->CRI_AUD,
                $r->DES_DOM,
                $r->ENT_AUD,
                $r->REG_AUD,
                $r->COD_PAC ? trim($r->NOM_PAC . ' ' . $r->APE_PAC) : '',
                $r->CI_PAC ?? '',
                $r->DET_AUD,
                $r->IP_AUD,
                $r->DIS_AUD,
            ]));
        }

        $writer->close();

        return response()->download($ruta, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request)
    {
        [$query, $f, $dominios, $roles] = $this->consulta($request);

        $resumen = $this->resumenFiltros($f, $dominios, $roles);
        $this->auditarExportacion('Exportar PDF', $resumen);

        $registros = $query->limit(self::LIMITE_PDF + 1)->get();
        $truncado  = $registros->count() > self::LIMITE_PDF;
        $registros = $registros->take(self::LIMITE_PDF);

        $html = view('admin.auditoria.pdf', [
            'registros'   => $registros,
            'filtros'     => $resumen,
            'truncado'    => $truncado,
            'limite'      => self::LIMITE_PDF,
            'generadoPor' => Auth::user()->USR_USR ?? '—',
            'generadoEn'  => now()->format('d/m/Y H:i:s'),
        ])->render();

        $opciones = new Options();
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Pie de página: "Página X de Y"
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(720, 570, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.4, 0.4, 0.4]);

        $nombre = 'auditoria_cies_' . now()->format('Ymd_His') . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    /**
     * Consulta compartida por la pantalla, el Excel y el PDF.
     * Devuelve: [query, filtros saneados, dominios, roles, acciones].
     */
    private function consulta(Request $request): array
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
        ]);

        $dominios = DB::table('DOMINIO_AUD')->orderBy('COD_DOM')->get(['COD_DOM', 'DES_DOM']);
        $roles    = DB::table('ROL')->orderBy('DES_ROL')->get(['COD_ROL', 'DES_ROL']);
        $acciones = DB::table('AUDITORIA')->distinct()->orderBy('ACC_AUD')->pluck('ACC_AUD');

        $criticidad = (string) $request->query('criticidad', '');
        $dominio    = (string) $request->query('dominio', '');
        $rol        = (string) $request->query('rol', '');
        $accion     = (string) $request->query('accion', '');

        $f = [
            'buscar'     => trim((string) $request->query('buscar', '')),
            'usuario'    => trim((string) $request->query('usuario', '')),
            'paciente'   => trim((string) $request->query('paciente', '')),
            'criticidad' => in_array($criticidad, self::CRITICIDADES, true) ? $criticidad : '',
            'dominio'    => $dominios->contains('COD_DOM', $dominio) ? $dominio : '',
            'rol'        => $roles->contains('COD_ROL', $rol) ? $rol : '',
            'accion'     => $acciones->contains($accion) ? $accion : '',
            'desde'      => (string) $request->query('desde', ''),
            'hasta'      => (string) $request->query('hasta', ''),
        ];

        $query = DB::table('AUDITORIA')
            ->join('USUARIO', 'AUDITORIA.COD_USR', '=', 'USUARIO.COD_USR')
            ->join('ROL', 'AUDITORIA.COD_ROL', '=', 'ROL.COD_ROL')
            ->join('DOMINIO_AUD', 'AUDITORIA.COD_DOM', '=', 'DOMINIO_AUD.COD_DOM')
            ->leftJoin('PACIENTE', 'AUDITORIA.COD_PAC', '=', 'PACIENTE.COD_PAC')
            ->select(
                'AUDITORIA.*',
                'USUARIO.USR_USR',
                'ROL.DES_ROL',
                'DOMINIO_AUD.DES_DOM',
                'PACIENTE.NOM_PAC',
                'PACIENTE.APE_PAC',
                'PACIENTE.CI_PAC'
            )
            ->when($f['buscar'] !== '', function ($q) use ($f) {
                $q->where(function ($q) use ($f) {
                    $q->where('AUDITORIA.DET_AUD', 'like', "%{$f['buscar']}%")
                      ->orWhere('AUDITORIA.REG_AUD', 'like', "%{$f['buscar']}%")
                      ->orWhere('AUDITORIA.COD_AUD', 'like', "%{$f['buscar']}%");
                });
            })
            ->when($f['usuario'] !== '', fn ($q) => $q->where('USUARIO.USR_USR', 'like', "%{$f['usuario']}%"))
            ->when($f['paciente'] !== '', function ($q) use ($f) {
                $q->where(function ($q) use ($f) {
                    $q->where('PACIENTE.CI_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('PACIENTE.NOM_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('PACIENTE.APE_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('AUDITORIA.COD_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhereExists(function ($sub) use ($f) {
                          $sub->select(DB::raw(1))
                              ->from('HISTORIA_CLINICA')
                              ->whereColumn('HISTORIA_CLINICA.COD_PAC', 'AUDITORIA.COD_PAC')
                              ->where('HISTORIA_CLINICA.NRO_HIS', 'like', "%{$f['paciente']}%");
                      });
                });
            })
            ->when($f['criticidad'] !== '', fn ($q) => $q->where('AUDITORIA.CRI_AUD', $f['criticidad']))
            ->when($f['dominio'] !== '', fn ($q) => $q->where('AUDITORIA.COD_DOM', $f['dominio']))
            ->when($f['rol'] !== '', fn ($q) => $q->where('AUDITORIA.COD_ROL', $f['rol']))
            ->when($f['accion'] !== '', fn ($q) => $q->where('AUDITORIA.ACC_AUD', $f['accion']))
            ->when($f['desde'] !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '>=', $f['desde'] . ' 00:00:00'))
            ->when($f['hasta'] !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '<=', $f['hasta'] . ' 23:59:59'))
            ->orderByDesc('AUDITORIA.id');

        return [$query, $f, $dominios, $roles, $acciones];
    }

    /** Lista legible de los filtros activos (para el PDF y para el detalle de auditoría). */
    private function resumenFiltros(array $f, $dominios, $roles): array
    {
        $t = [];

        if ($f['buscar'] !== '')     $t[] = 'Texto: ' . $f['buscar'];
        if ($f['usuario'] !== '')    $t[] = 'Usuario: ' . $f['usuario'];
        if ($f['paciente'] !== '')   $t[] = 'Paciente/expediente: ' . $f['paciente'];
        if ($f['rol'] !== '')        $t[] = 'Rol: ' . $roles->firstWhere('COD_ROL', $f['rol'])->DES_ROL;
        if ($f['accion'] !== '')     $t[] = 'Acción: ' . $f['accion'];
        if ($f['criticidad'] !== '') $t[] = 'Criticidad: ' . $f['criticidad'];
        if ($f['dominio'] !== '')    $t[] = 'Dominio: ' . $dominios->firstWhere('COD_DOM', $f['dominio'])->DES_DOM;
        if ($f['desde'] !== '')      $t[] = 'Desde: ' . $f['desde'];
        if ($f['hasta'] !== '')      $t[] = 'Hasta: ' . $f['hasta'];

        return $t;
    }

    /** Exportar el log de auditoría también deja rastro en la auditoría. */
    private function auditarExportacion(string $accion, array $resumen): void
    {
        Auditoria::registrar(
            $accion,
            'Media',
            'DOM02',
            'AUDITORIA',
            'EXPORTACION',
            'Filtros: ' . ($resumen ? implode(' | ', $resumen) : 'ninguno')
        );
    }
}