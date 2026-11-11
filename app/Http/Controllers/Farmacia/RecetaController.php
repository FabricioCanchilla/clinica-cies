<?php

namespace App\Http\Controllers\Farmacia;

use App\Http\Controllers\Controller;
use App\Services\FarmaciaService;
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

        return view('farmacia.despacho', [
            'receta' => $receta,
            'items'  => $this->farmacia->detalles($codRec),
        ]);
    }

    public function despachar(Request $request, string $codRec, string $codDre)
    {
        $d = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:32000'],
        ], [
            'cantidad.required' => 'Indica la cantidad a despachar.',
            'cantidad.integer'  => 'La cantidad debe ser un número entero.',
            'cantidad.min'      => 'La cantidad debe ser al menos 1.',
        ]);

        try {
            $r = $this->farmacia->despacharItem($codRec, $codDre, (int) $d['cantidad'], auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['receta' => $e->getMessage()]);
        }

        $mensaje = "Receta {$codRec}: se entregaron {$r['entregado']} de {$r['medicamento']} (" . implode(', ', $r['lotes']) . ').';

        if ($r['entregado'] < $r['solicitado']) {
            $mensaje .= " Solo había {$r['entregado']} unidades vigentes; el ítem queda con {$r['pendiente']} pendientes.";
        }
        if ($r['completa']) {
            $mensaje .= ' La receta quedó «Completada».';
        }

        return back()->with('ok', $mensaje);
    }
}