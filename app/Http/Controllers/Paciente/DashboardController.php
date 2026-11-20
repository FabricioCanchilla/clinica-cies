<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\CitaService;
use App\Services\InsumoService;
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
                ->where('h.COD_PAC', $paciente->COD_PAC)
                ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
                ->leftJoin('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
                ->select(
                    'co.FEC_CNS',
                    'co.DIA_CNS',
                    'co.PLA_CNS',
                    'e.TIT_EMP',
                    'e.NOM_EMP',
                    'e.APE_EMP',
                    'es.DES_ESP'
                )
                ->orderBy('co.FEC_CNS', 'desc')
                ->get();
        }

        // RF016: comprobantes de insumos entregados por familiares
        $insumos = $paciente ? $this->insumos->comprobantesDelPaciente($paciente->COD_PAC) : collect();

        // RF010 / RN31: el paciente ve solo los resultados publicados
        $resultados = collect();
        // RF013: historial de pagos con sus comprobantes
        $pagos = collect();

        if ($paciente) {
            $resultados = DB::table('RESULTADO_EXAMEN as r')
                ->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'r.COD_ORD')
                ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
                ->join('CONSULTA as co', 'co.COD_CNS', '=', 'o.COD_CNS')
                ->join('CITA as ci', 'ci.COD_CIT', '=', 'co.COD_CIT')
                ->where('ci.COD_PAC', $paciente->COD_PAC)
                ->where('r.EST_RES', 'Publicado')
                ->orderByDesc('r.FEC_RES')
                ->get(['r.COD_RES', 'r.FEC_RES', 'r.DES_RES', 'r.CRI_RES', 'r.ARC_RES', 'o.COD_ORD', 's.NOM_SRV']);

            $pagos = DB::table('PAGO as p')
                ->join('CITA as ci', 'ci.COD_CIT', '=', 'p.COD_CIT')
                ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'p.COD_MET')
                ->join('SERVICIO as s', 's.COD_SRV', '=', 'ci.COD_SRV')
                ->where('ci.COD_PAC', $paciente->COD_PAC)
                ->orderByDesc('p.FEC_PAG')
                ->get(['p.COD_PAG', 'p.FEC_PAG', 'p.MON_PAG', 'p.TIP_PAG', 'p.EST_PAG', 'p.COD_CIT', 'p.COD_CAJ',
                       'm.DES_MET', 's.NOM_SRV', 's.PRE_SRV']);
        }

        return view('paciente.dashboard', [
            'resultados' => $resultados,
            'pagos'      => $pagos,
            'insumos'   => $insumos,
            'paciente'  => $paciente,
            'citas'     => $citas,
            'recetas'   => $recetas,
            'historial' => $historial,
        ]);
    }

    /** RF014: el paciente actualiza sus datos de contacto. Nombre, CI y fecha de nacimiento los corrige recepción. */
    public function actualizarPerfil(\Illuminate\Http\Request $request)
    {
        $codPac = auth()->user()->COD_PAC;
        abort_if(!$codPac, 403);

        $datos = $request->validate([
            'telefono'  => ['required', 'regex:/^[0-9]{7,8}$/'],
            'correo'    => ['required', 'email', 'max:60'],
            'direccion' => ['required', 'string', 'max:100'],
            'estciv'    => ['required', 'in:Soltero,Casado,Divorciado,Viudo,Concubino'],
        ], [
            'telefono.regex' => 'El teléfono debe tener 7 u 8 dígitos.',
            'correo.email'   => 'El correo no es válido.',
        ]);

        $antes = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();

        DB::table('PACIENTE')->where('COD_PAC', $codPac)->update([
            'TEL_PAC'    => $datos['telefono'],
            'COR_PAC'    => mb_strtolower(trim($datos['correo'])),
            'DIR_PAC'    => trim($datos['direccion']),
            'ESTCIV_PAC' => $datos['estciv'],
        ]);

        $cambios = collect([
            'teléfono'     => [$antes->TEL_PAC, $datos['telefono']],
            'correo'       => [$antes->COR_PAC, mb_strtolower(trim($datos['correo']))],
            'dirección'    => [$antes->DIR_PAC, trim($datos['direccion'])],
            'estado civil' => [$antes->ESTCIV_PAC, $datos['estciv']],
        ])->filter(fn ($v) => $v[0] !== $v[1])->keys()->implode(', ');

        \App\Services\Auditoria::registrar('Actualizar perfil', 'Baja', 'DOM01', 'PACIENTE', $codPac,
            'El paciente actualizó: ' . ($cambios ?: 'sin cambios'), codPac: $codPac);

        return redirect()->route('paciente.dashboard')->with('ok', 'Tus datos se actualizaron.');
    }

    /** RF011: descarga de la receta en PDF (dompdf), solo las del propio paciente. */
    public function recetaPdf(string $codRec)
    {
        $codPac = auth()->user()->COD_PAC;
        abort_if(!$codPac, 403);

        $r = DB::table('RECETA as r')
            ->join('CONSULTA as co', 'co.COD_CNS', '=', 'r.COD_CNS')
            ->join('CITA as ci', 'ci.COD_CIT', '=', 'co.COD_CIT')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'ci.COD_PAC')
            ->where('r.COD_REC', $codRec)->where('ci.COD_PAC', $codPac)
            ->first(['r.*', 'co.DIA_CNS', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.COD_PAC']);
        abort_if(!$r, 404);

        $items = DB::table('DETALLE_RECETA as d')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')
            ->where('d.COD_REC', $codRec)
            ->get(['m.DES_MED', 'd.DOS_DRE', 'd.FRE_DRE', 'd.DUR_DRE', 'v.DES_VIA']);

        $html = view('paciente.receta-pdf', ['r' => $r, 'items' => $items])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        \App\Services\Auditoria::registrar('Descargar receta', 'Baja', 'DOM03', 'RECETA', $codRec,
            "El paciente descargó la receta {$codRec} en PDF", codPac: $codPac);

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$codRec}.pdf\"",
        ]);
    }
}
