<?php

namespace App\Http\Controllers\Farmacia;

use App\Http\Controllers\Controller;
use App\Services\FarmaciaService;
use App\Services\Auditoria;
use Illuminate\Support\Facades\DB;
use DomainException;
use Illuminate\Http\Request;

class RecetaController extends Controller
{
    public function __construct(private FarmaciaService $farmacia)
    {
    }

    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');

        if (!in_array($estado, FarmaciaService::ESTADOS_RECETA, true)) {
            $estado = '';
        }

        return view('farmacia.recetas', [
            'contadores' => $this->farmacia->contadoresRecetas(),
            'recetas'    => $this->farmacia->recetas($buscar, $estado),
            'estados'    => FarmaciaService::ESTADOS_RECETA,
            'alertas'    => $this->farmacia->contadoresLotes(),
            'buscar'     => $buscar,
            'estado'     => $estado,
        ]);
    }

    public function show(string $codRec)
    {
        $receta = $this->farmacia->receta($codRec);

        if (!$receta) {
            return redirect()->route('farmacia.dashboard')
                ->withErrors(['receta' => "La receta {$codRec} no existe."]);
        }
        $paciente = DB::table('RECETA as r')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('r.COD_REC', $codRec)->value('h.COD_PAC');
        Auditoria::registrar('Leer', 'Media', 'DOM03', 'RECETA', $codRec, 'Consulta de receta en farmacia', codPac: $paciente);

        return view('farmacia.despacho', [
            'receta' => $receta,
            'items'  => $this->farmacia->detalles($codRec),
        ]);
    }

    public function cantidad(Request $request, string $codRec, string $codDre)
    {
        $d = $request->validate(['cantidad' => 'required|integer|min:1|max:32000', 'confirmacion' => 'accepted']);
        try {
            $this->farmacia->confirmarCantidad($codRec, $codDre, (int) $d['cantidad'], auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['cantidad' => $e->getMessage()]);
        }
        return back()->with('ok', 'Cantidad total confirmada para el despacho.');
    }

    public function despachar(Request $request, string $codRec, string $codDre)
    {
        return back()->withErrors(['receta' => 'El cobro y la entrega deben registrarse juntos en caja.']);
    }
}
