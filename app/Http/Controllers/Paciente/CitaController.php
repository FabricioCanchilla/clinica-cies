<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\CitaService;
use App\Services\ComprobanteService;
use App\Services\InsumoService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    public function __construct(private CitaService $citas)
    {
    }

    /** RF007: formulario de agendamiento (catálogos reales desde CitaService). */
    public function create()
    {
        $especialidades = $this->citas->especialidades();

        // Todos los médicos, con su COD_ESP, para filtrar en el navegador
        $medicos = $especialidades
            ->flatMap(fn ($e) => $this->citas->medicos($e->COD_ESP))
            ->values();

        $servicios = $this->citas->serviciosConsulta()->map(function ($s) {
            $s->ANTICIPO = $this->citas->montoAnticipo((float) $s->PRE_SRV);
            return $s;
        });

        return view('paciente.citas.crear', [
            'especialidades' => $especialidades,
            'medicos'        => $medicos,
            'servicios'      => $servicios,
            'minFecha'       => Carbon::today()->toDateString(),
            'maxFecha'       => Carbon::today()->addDays((int) config('cies.dias_max_anticipacion'))->toDateString(),
            'porcentaje'     => (int) config('cies.anticipo_porcentaje'),
            'minutos'        => (int) config('cies.minutos_bloqueo'),
        ]);
    }

    /** Horas libres de un médico en una fecha (lo consume el select del formulario). */
    public function horarios(Request $request)
    {
        $datos = $request->validate([
            'medico' => 'required|string|max:9',
            'fecha'  => 'required|date_format:Y-m-d',
        ]);

        $libres = $this->citas->horariosLibres($datos['medico'], Carbon::createFromFormat('Y-m-d', $datos['fecha']));

        return response()->json(array_column($libres, 'hora'));
    }

    /** RF007: reserva el horario por 15 minutos y manda al pago del anticipo. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'medico'   => 'required|string|max:9',
            'fecha'    => 'required|date_format:Y-m-d',
            'hora'     => 'required|date_format:H:i',
            'servicio' => 'required|string|max:8',
            'motivo'   => 'required|string|max:200',
        ], [], [
            'medico'   => 'médico',
            'servicio' => 'servicio',
            'motivo'   => 'motivo de la consulta',
        ]);

        try {
            $cita = $this->citas->reservar(
                $this->codPac(),
                $datos['medico'],
                $datos['fecha'],
                $datos['hora'],
                $datos['servicio'],
                $datos['motivo']
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['cita' => $e->getMessage()]);
        }

        return redirect()->route('paciente.citas.pagar', $cita->COD_CIT);
    }

    /** RF008: pantalla de pago del anticipo. */
    public function pagar(string $codCit)
    {
        try {
            $cita = $this->citas->citaParaPago($codCit, $this->codPac());
        } catch (DomainException $e) {
            return redirect()->route('paciente.dashboard')->withErrors(['cita' => $e->getMessage()]);
        }

        return view('paciente.citas.pagar', [
            'cita'    => $cita,
            'metodos' => $this->citas->metodosPago(),
            'otp'     => session('otp_cita.' . $codCit),
        ]);
    }

    /** RF008: envía el código al correo del paciente (Quarkus + Mailpit). */
    public function enviarOtp(string $codCit)
    {
        try {
            $r = $this->citas->enviarOtp($codCit, $this->codPac(), auth()->user()->COD_USR);
        } catch (DomainException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        session(['otp_cita.' . $codCit => ['codOtp' => $r['codOtp'], 'correo' => $r['correo']]]);

        return back()->with('ok', "Te enviamos un código de 6 dígitos a {$r['correo']}. Vence en " . (int) round($r['segundos'] / 60) . ' minutos.');
    }

    /** RF008: valida el código y confirma la cita. */
    public function confirmar(Request $request, string $codCit)
    {
        $datos = $request->validate([
            'codigo' => 'required|string|size:6',
            'metodo' => 'required|string|max:7',
            'firma'  => 'required|string',
        ], [
            'codigo.size'    => 'El código tiene 6 dígitos.',
            'firma.required' => 'Falta tu firma (RN16: el pago se confirma con firma y código).',
        ], [
            'metodo' => 'método de pago',
        ]);

        $otp = session('otp_cita.' . $codCit);
        if (!$otp) {
            return back()->withErrors(['otp' => 'Solicita primero el código de verificación.']);
        }

        try {
            $pago = $this->citas->confirmarPago(
                $codCit,
                $this->codPac(),
                auth()->user()->COD_USR,
                $otp['codOtp'],
                $datos['codigo'],
                $datos['metodo'],
                $datos['firma']
            );
        } catch (DomainException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        session()->forget('otp_cita.' . $codCit);

        $ok = "Cita confirmada. Anticipo de Bs {$pago->monto} registrado (pago {$pago->COD_PAG}).";

        if ($pago->NRO_COM) {
            return redirect()->route('paciente.dashboard')
                ->with('ok', "{$ok} Tu comprobante es el {$pago->NRO_COM}.");
        }

        return redirect()->route('paciente.dashboard')
            ->with('ok', $ok)
            ->with('aviso', 'No pudimos generar tu comprobante en este momento. Tu pago y tu cita están confirmados; usa "Reintentar comprobante" en la lista de citas.');
    }

    /** Reintento del comprobante del anticipo cuando Quarkus falló después del pago. */
    public function reintentarComprobante(string $codCit)
    {
        try {
            $nro = $this->citas->reintentarComprobante($codCit, $this->codPac());
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        if (!$nro) {
            return back()->with('aviso', 'El servicio de comprobantes sigue sin responder. Tu pago está registrado; intenta más tarde.');
        }

        return back()->with('ok', "Comprobante {$nro} generado.");
    }

    /** PDF del comprobante del anticipo (lo genera Quarkus; aquí solo se verifica que la cita sea del paciente). */
    public function comprobantePdf(string $codCit, ComprobanteService $comprobantes)
    {
        $nro = $this->citas->comprobanteAnticipo($codCit, $this->codPac());
        if (!$nro) {
            abort(404, 'Esta cita no tiene comprobante emitido.');
        }

        try {
            $pdf = $comprobantes->pdf($nro);
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$nro}.pdf\"",
        ]);
    }

    /** RF016: PDF del comprobante de entrega de un insumo del propio paciente. */
    public function comprobanteInsumoPdf(string $nroCen, InsumoService $insumos)
    {
        if (!$insumos->comprobanteEsDelPaciente($nroCen, $this->codPac())) {
            abort(404);
        }

        try {
            $pdf = $insumos->pdfEntrega($nroCen);
        } catch (DomainException $e) {
            return back()->withErrors(['comprobante' => $e->getMessage()]);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$nroCen}.pdf\"",
        ]);
    }

    // ---------- RF009: cancelar y reprogramar (RN21, RN22) ----------

    public function cancelar(string $codCit)
    {
        try {
            $msg = $this->citas->cancelar($codCit, $this->codPac());
        } catch (DomainException $e) {
            return redirect()->route('paciente.dashboard')->withErrors(['cita' => $e->getMessage()]);
        }

        return redirect()->route('paciente.dashboard')->with('ok', $msg);
    }

    public function reprogramarForm(string $codCit)
    {
        $c = \Illuminate\Support\Facades\DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.COD_CIT', $codCit)->where('c.COD_PAC', $this->codPac())
            ->first(['c.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV']);
        abort_if(!$c, 404);

        $error = null;
        if ($c->EST_CIT !== 'Confirmada') {
            $error = 'Solo se reprograman citas confirmadas.';
        } elseif ($this->citas->horasParaLaCita($c) < 24) {
            $error = 'Solo se puede reprogramar hasta 24 horas antes de la cita.';
        } elseif ($this->citas->reprogramaciones($codCit) >= 2) {
            $error = 'Esta cita ya se reprogramó 2 veces, que es el máximo permitido.';
        }
        if ($error) {
            return redirect()->route('paciente.dashboard')->withErrors(['cita' => $error]);
        }

        return view('paciente.citas.reprogramar', [
            'cita'     => $c,
            'veces'    => $this->citas->reprogramaciones($codCit),
            'minFecha' => Carbon::today()->toDateString(),
            'maxFecha' => Carbon::today()->addDays((int) config('cies.dias_max_anticipacion'))->toDateString(),
        ]);
    }

    public function reprogramar(Request $request, string $codCit)
    {
        $datos = $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'hora'  => 'required|date_format:H:i',
        ]);

        try {
            $c = $this->citas->reprogramar($codCit, $this->codPac(), $datos['fecha'], $datos['hora']);
        } catch (DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()])->withInput();
        }

        return redirect()->route('paciente.dashboard')->with('ok',
            'Cita reprogramada al ' . Carbon::parse($c->FEC_CIT)->format('d/m/Y') . ' a las ' . substr($c->HOR_CIT, 0, 5) . '. El anticipo pagado se trasladó a la nueva fecha.');
    }

    /** El usuario tiene que ser un paciente con ficha. */
    private function codPac(): string
    {
        $codPac = auth()->user()->COD_PAC;

        if (!$codPac) {
            abort(403, 'Tu usuario no está vinculado a una ficha de paciente.');
        }

        return $codPac;
    }
}
