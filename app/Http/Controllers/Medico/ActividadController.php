<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\MedicoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ActividadController extends Controller
{
    public function index(string $seccion, MedicoService $medico): View
    {
        abort_unless(in_array($seccion, ['recetas', 'examenes', 'notas'], true), 404);
        $emp = $medico->empleado();
        if ($seccion === 'notas') {
            $filas = DB::table('NOTA_EVOLUCION as n')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'n.COD_HIS')
                ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')->where('n.COD_EMP', $emp)
                ->select('n.FEC_NEV as fecha', 'n.TXT_NEV as texto', 'p.NOM_PAC', 'p.APE_PAC', 'p.COD_PAC')
                ->orderByDesc('n.FEC_NEV')->paginate(15);
        } else {
            $recetas = $seccion === 'recetas';
            $tabla = $recetas ? 'RECETA' : 'ORDEN_EXAMEN';
            $fecha = $recetas ? 'FEC_REC' : 'FEC_ORD';
            $codigo = $recetas ? 'COD_REC' : 'COD_ORD';
            $estado = $recetas ? 'EST_REC' : 'EST_ORD';
            $filas = DB::table($tabla.' as r')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
                ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
                ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')->where('c.COD_EMP', $emp)
                ->select('r.'.$fecha.' as fecha', 'r.'.$codigo.' as codigo', 'r.'.$estado.' as estado', 'c.COD_CNS', 'p.NOM_PAC', 'p.APE_PAC', 'p.COD_PAC')
                ->orderByDesc('r.id')->paginate(15);
        }
        // El listado de actividad también constituye acceso a información clínica.
        Auditoria::registrar('Leer', 'Media', 'DOM03', 'ACTIVIDAD_MEDICA', $emp, 'Listado de '.$seccion);

        return view('medico.actividad', compact('seccion', 'filas'));
    }
}
