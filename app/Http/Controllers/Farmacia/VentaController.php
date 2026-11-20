<?php

namespace App\Http\Controllers\Farmacia;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\VentaFarmaciaService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** RF041: venta en mostrador y consumos de hospitalizados, con recibo en PDF. */
class VentaController extends Controller
{
    public function __construct(private VentaFarmaciaService $ventas)
    {
    }

    public function index()
    {
        return view('farmacia.ventas', [
            'catalogo'   => $this->ventas->catalogo(),
            'internados' => $this->ventas->internados(),
            'metodos'    => DB::table('METODO_PAGO')->orderBy('COD_MET')->get(),
            'ventas'     => $this->ventas->ventasDelDia(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'tipo'               => 'required|in:Ambulante,Hospitalizado',
            'cliente'            => 'nullable|string|max:100',
            'ci'                 => 'nullable|string|max:10',
            'internacion'        => 'nullable|required_if:tipo,Hospitalizado|string',
            'metodo'             => 'nullable|required_if:tipo,Ambulante|exists:METODO_PAGO,COD_MET',
            'items'              => 'required|array|max:15',
            'items.*.med'        => 'nullable|exists:MEDICAMENTO,COD_MED',
            'items.*.cantidad'   => 'nullable|integer|min:1|max:1000',
        ], ['internacion.required_if' => 'Elige el paciente internado.', 'metodo.required_if' => 'Elige el método de pago.']);
        try {
            $nro = $this->ventas->vender($d['items'], $d, auth()->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['venta' => $e->getMessage()]);
        }
        return redirect()->route('farmacia.ventas')->with('ok', "Venta {$nro} registrada.")->with('recibo', $nro);
    }

    public function anular(Request $request, string $nro)
    {
        $d = $request->validate(['motivo' => 'required|string|min:5|max:200']);
        try {
            $this->ventas->anular($nro, trim($d['motivo']), auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['venta' => $e->getMessage()]);
        }
        return back()->with('ok', "Venta {$nro} anulada; el stock volvió a sus lotes.");
    }

    public function pdf(string $nro)
    {
        $v = $this->ventas->venta($nro);
        abort_if(!$v, 404);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml(view('farmacia.recibo-pdf', ['v' => $v])->render(), 'UTF-8');
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();
        Auditoria::registrar('Recibo farmacia', 'Baja', 'DOM04', 'VENTA', $nro, "Impresión del recibo {$nro}", codPac: $v->COD_PAC);
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$nro}.pdf\"",
        ]);
    }
}
