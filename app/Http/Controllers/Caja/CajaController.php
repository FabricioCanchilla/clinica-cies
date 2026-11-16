<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Services\CajaService;
use App\Services\ComprobanteService;
use DomainException;
use Illuminate\Http\Request;

/** RF039: panel de Caja (ROL005). */
class CajaController extends Controller
{
    public function __construct(private CajaService $caja)
    {
    }

    public function index(Request $request)
    {
        $caja   = $this->caja->cajaAbierta($this->codEmp());
        $buscar = (string) $request->query('buscar', '');

        return view('caja.index', [
            'caja'         => $caja,
            'resumen'      => $caja ? $this->caja->resumen($caja) : null,
            'porCobrar'    => $caja ? $this->caja->citasPorCobrar($buscar) : collect(),
            'pendientes'   => $caja ? $this->caja->cobrosSinComprobante($caja->COD_CAJ) : collect(),
            'comprobantes' => $caja ? $this->caja->comprobantesDeCaja($caja->COD_CAJ) : collect(),
            'buscar'       => $buscar,
        ]);
    }

    public function abrir(Request $request)
    {
        $datos = $request->validate(['monto' => 'required|numeric|min:0|max:100000'], [], ['monto' => 'monto de apertura']);

        try {
            $caja = $this->caja->abrir($this->codEmp(), (float) $datos['monto']);
        } catch (DomainException $e) {
            return back()->withErrors(['caja' => $e->getMessage()]);
        }

        return redirect()->route('caja.dashboard')->with('ok', "Caja {$caja->COD_CAJ} abierta con Bs {$caja->MONAPE_CAJ}.");
    }

    public function formCobro(string $codCit)
    {
        if (!$this->caja->cajaAbierta($this->codEmp())) {
            return redirect()->route('caja.dashboard')->withErrors(['caja' => 'Abre tu caja antes de cobrar (RN26).']);
        }

        try {
            $cita = $this->caja->citaParaCobro($codCit);
        } catch (DomainException $e) {
            return redirect()->route('caja.dashboard')->withErrors(['caja' => $e->getMessage()]);
        }

        return view('caja.cobrar', [
            'cita'    => $cita,
            'metodos' => $this->caja->metodosPago(),
            'limite'  => (int) config('cies.descuento_sin_autorizacion'),
        ]);
    }

    public function cobrar(Request $request, string $codCit)
    {
        $datos = $request->validate([
            'metodo'        => 'required|string|max:7',
            'descuento'     => 'required|integer|min:0|max:99',
            'admin_usuario' => 'nullable|string|max:30',
            'admin_clave'   => 'nullable|string|max:100',
        ], [], [
            'metodo'    => 'método de pago',
            'descuento' => 'descuento',
        ]);

        try {
            $pago = $this->caja->cobrar(
                $this->codEmp(),
                $codCit,
                $datos['metodo'],
                (int) $datos['descuento'],
                ['usuario' => $datos['admin_usuario'] ?? '', 'clave' => $datos['admin_clave'] ?? '']
            );
        } catch (DomainException $e) {
            return back()->withInput($request->except('admin_clave'))->withErrors(['cobro' => $e->getMessage()]);
        }

        $ok = "Cobro {$pago->COD_PAG} de Bs {$pago->monto} registrado (cita {$pago->COD_CIT}).";
        if ($pago->NRO_COM) {
            return redirect()->route('caja.dashboard')->with('ok', "{$ok} Comprobante {$pago->NRO_COM}.");
        }

        return redirect()->route('caja.dashboard')->with('ok', $ok)
            ->with('aviso', 'El cobro quedó registrado pero el comprobante no se pudo generar. Usa "Reintentar comprobante".');
    }

    public function reintentar(string $codPag)
    {
        try {
            $nro = $this->caja->reintentarComprobante($this->codEmp(), $codPag);
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return $nro
            ? back()->with('ok', "Comprobante {$nro} generado para el cobro {$codPag}.")
            : back()->with('aviso', 'El servicio de comprobantes sigue sin responder. Intenta de nuevo en unos minutos.');
    }

    public function anular(Request $request, string $nroCom)
    {
        $datos = $request->validate(['motivo' => 'required|string|min:10|max:200'], [], ['motivo' => 'motivo']);

        try {
            $this->caja->anular($this->codEmp(), $nroCom, $datos['motivo']);
        } catch (DomainException $e) {
            return back()->withErrors(['anulacion' => $e->getMessage()]);
        }

        return back()->with('ok', "Comprobante {$nroCom} anulado. El saldo de la cita vuelve a estar pendiente.");
    }

    public function cerrar(Request $request)
    {
        $datos = $request->validate(['contado' => 'required|numeric|min:0|max:1000000'], [], ['contado' => 'efectivo contado']);

        try {
            $r = $this->caja->cerrar($this->codEmp(), (float) $datos['contado']);
        } catch (DomainException $e) {
            return back()->withErrors(['cierre' => $e->getMessage()]);
        }

        $msg = "Caja {$r['COD_CAJ']} cerrada. Esperado Bs {$r['esperado']}, contado Bs {$r['contado']}.";
        return $r['diferencia'] == 0.0
            ? redirect()->route('caja.dashboard')->with('ok', $msg . ' Sin diferencia.')
            : redirect()->route('caja.dashboard')->with('aviso', $msg . " Diferencia de Bs {$r['diferencia']} (queda auditada).");
    }

    public function pdf(string $nroCom, ComprobanteService $comprobantes)
    {
        if (!preg_match('/^COM\d{8}$/', $nroCom)) {
            abort(404);
        }

        try {
            $pdf = $comprobantes->pdf($nroCom);
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$nroCom}.pdf\"",
        ]);
    }

    private function codEmp(): string
    {
        $codEmp = auth()->user()->COD_EMP;
        if (!$codEmp) {
            abort(403, 'Tu usuario no está vinculado a un empleado.');
        }
        return $codEmp;
    }
}
