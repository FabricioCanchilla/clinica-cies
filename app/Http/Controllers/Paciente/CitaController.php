<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\CitaService;
use App\Services\ComprobanteService;
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
        ], [
            'codigo.size' => 'El código tiene 6 dígitos.',
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
                $datos['metodo']
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
