<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Services\InsumoService;
use DomainException;
use Illuminate\Http\Request;

/** RF031 / RF032: insumos que entregan los familiares (rol Enfermería, ROL004). */
class InsumoController extends Controller
{
    public function __construct(private InsumoService $insumos)
    {
    }

    public function index(Request $request)
    {
        $estado = (string) $request->query('estado', '');
        $buscar = (string) $request->query('buscar', '');

        return view('enfermeria.insumos', [
            'insumos' => $this->insumos->listar($estado, $buscar),
            'estados' => [InsumoService::PENDIENTE, InsumoService::VERIFICADO, InsumoService::RECHAZADO],
            'estado'  => $estado,
            'buscar'  => $buscar,
        ]);
    }

    public function create()
    {
        return view('enfermeria.insumo-crear', ['medicamentos' => $this->insumos->medicamentos()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'ci_pac'   => 'required|string|max:10',
            'ci_ent'   => 'required|string|max:10',
            'nom_ent'  => 'required|string|max:40',
            'ape_ent'  => 'required|string|max:40',
            'cor_ent'  => 'required|email|max:60',
            'tel_ent'  => ['required', 'regex:/^\d{8}$/'],
            'cod_med'  => 'required|string|max:8',
            'dosis'    => 'required|string|max:20',
            'cantidad' => 'required|integer|min:1|max:1000',
            'lote'     => 'nullable|string|max:15',
            'fecven'   => 'required|date_format:Y-m-d',
        ], [
            'tel_ent.regex' => 'El celular del familiar debe tener 8 dígitos.',
        ], [
            'ci_pac'  => 'CI del paciente', 'ci_ent' => 'CI del familiar', 'nom_ent' => 'nombres del familiar',
            'ape_ent' => 'apellidos del familiar', 'cor_ent' => 'correo del familiar', 'cod_med' => 'medicamento',
            'fecven'  => 'fecha de vencimiento',
        ]);

        try {
            $ins = $this->insumos->registrar($datos, $this->codEmp());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['insumo' => $e->getMessage()]);
        }

        if ($ins->EST_INS === InsumoService::RECHAZADO) {
            return redirect()->route('enfermeria.dashboard')
                ->with('aviso', "Insumo {$ins->COD_INS} registrado como Rechazado: " . implode(' y ', $ins->MOTIVOS) . ' (RN36).');
        }

        return redirect()->route('enfermeria.insumos.verificar', $ins->COD_INS)
            ->with('ok', "Insumo {$ins->COD_INS} registrado. Ahora el familiar debe firmar y confirmar el código.");
    }

    public function verificarForm(string $codIns)
    {
        try {
            $ins = $this->insumos->insumo($codIns);
        } catch (DomainException $e) {
            return redirect()->route('enfermeria.dashboard')->withErrors(['insumo' => $e->getMessage()]);
        }

        return view('enfermeria.insumo-verificar', [
            'ins' => $ins,
            'otp' => session('otp_insumo.' . $codIns),
        ]);
    }

    public function enviarOtp(Request $request, string $codIns)
    {
        $datos = $request->validate(['canal' => 'required|in:CORREO,SMS']);

        try {
            $r = $this->insumos->enviarOtp($codIns, $datos['canal'], auth()->user()->COD_USR);
        } catch (DomainException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        session(['otp_insumo.' . $codIns => $r]);
        $via = $r['canal'] === 'SMS' ? 'SMS (simulado: el código queda en el log de Quarkus)' : 'correo';

        return back()->with('ok', "Código enviado por {$via} a {$r['destino']}. Vence en " . (int) round($r['segundos'] / 60) . ' minutos.');
    }

    public function verificar(Request $request, string $codIns)
    {
        $datos = $request->validate([
            'firma'  => 'required|string',
            'codigo' => 'required|string|size:6',
        ], [
            'firma.required' => 'Falta la firma del familiar.',
            'codigo.size'    => 'El código tiene 6 dígitos.',
        ]);

        try {
            $r = $this->insumos->verificar($codIns, $datos['firma'], $datos['codigo'], auth()->user()->COD_USR, $this->codEmp());
        } catch (DomainException $e) {
            return back()->withErrors(['verificar' => $e->getMessage()]);
        }

        session()->forget('otp_insumo.' . $codIns);

        if ($r['NRO_CEN']) {
            return redirect()->route('enfermeria.dashboard')
                ->with('ok', "Insumo {$codIns} verificado. Comprobante de entrega {$r['NRO_CEN']}.");
        }

        return redirect()->route('enfermeria.dashboard')
            ->with('ok', "Insumo {$codIns} verificado.")
            ->with('aviso', 'El comprobante de entrega no se pudo generar. Usa "Reintentar comprobante".');
    }

    public function reintentar(string $codIns)
    {
        try {
            $nro = $this->insumos->emitirComprobante($codIns, $this->codEmp());
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return $nro
            ? back()->with('ok', "Comprobante de entrega {$nro} generado.")
            : back()->with('aviso', 'El servicio de comprobantes sigue sin responder. Intenta en unos minutos.');
    }

    public function pdf(string $nroCen)
    {
        if (!preg_match('/^CEN\d{8}$/', $nroCen)) {
            abort(404);
        }

        try {
            $pdf = $this->insumos->pdfEntrega($nroCen);
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$nroCen}.pdf\"",
        ]);
    }

    public function firma(string $codIns)
    {
        $png = $this->insumos->firma($codIns);
        abort_if(!$png, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=600']);
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
