<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use App\Services\RecepcionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Panel de Recepción (ROL002): RF036, RF037 y RF038. */
class RecepcionController extends Controller
{
    public function __construct(private RecepcionService $recepcion)
    {
    }

    /** RF037: citas de hoy con check-in y disponibilidad en tiempo real. */
    public function index()
    {
        $hoy = Carbon::today();

        return view('recepcion.index', [
            'citas'        => $this->recepcion->citasDelDia($hoy),
            'medicos'      => $this->recepcion->disponibilidadMedicos($hoy),
            'consultorios' => $this->recepcion->disponibilidadConsultorios(),
            'camas'        => $this->recepcion->resumenCamas(),
        ]);
    }

    /** RF036: búsqueda por CI, código o nombre antes de crear la ficha. */
    public function pacientes(Request $request)
    {
        $q = (string) $request->query('q', '');

        return view('recepcion.pacientes', [
            'q'         => $q,
            'pacientes' => $this->recepcion->buscarPacientes($q),
            'buscado'   => trim($q) !== '',
        ]);
    }

    public function crearPaciente(Request $request)
    {
        return view('recepcion.paciente-crear', ['ci' => $request->query('ci')]);
    }

    public function guardarPaciente(Request $request)
    {
        $d = $request->validate([
            'ci'         => ['required', 'regex:/^[0-9]{5,10}$/'],
            'nombres'    => ['required', 'string', 'max:40'],
            'apellidos'  => ['required', 'string', 'max:40'],
            'sexo'       => ['required', 'in:M,F'],
            'fecnac'     => ['required', 'date', 'before:today'],
            'estciv'     => ['required', 'in:Soltero,Casado,Divorciado,Viudo,Concubino'],
            'telefono'   => ['required', 'regex:/^[0-9]{7,8}$/'],
            'correo'     => ['required', 'email', 'max:60'],
            'direccion'  => ['required', 'string', 'max:100'],
            'privacidad' => ['accepted'],
        ], [
            'ci.regex'            => 'El CI debe tener entre 5 y 10 dígitos.',
            'telefono.regex'      => 'El teléfono debe tener 7 u 8 dígitos.',
            'privacidad.accepted' => 'El paciente debe aceptar el aviso de privacidad y los términos de uso (RN07).',
        ]);

        try {
            $r = $this->recepcion->registrarPaciente($d);
        } catch (DomainException $e) {
            return back()->withErrors(['ci' => $e->getMessage()])->withInput();
        }

        return redirect()->route('recepcion.paciente', $r['codPac'])
            ->with('ok', "Ficha {$r['codPac']} creada. Usuario: {$r['usuario']} · clave temporal: {$r['clave']} (la cambia en su primer ingreso).");
    }

    /** Ficha del paciente: datos, seguros (RF038) y citas. */
    public function paciente(string $codPac)
    {
        $p = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();
        abort_if(!$p, 404);

        $citas = DB::table('CITA as c')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.COD_PAC', $codPac)
            ->orderByDesc('c.FEC_CIT')->orderByDesc('c.HOR_CIT')
            ->limit(20)
            ->get(['c.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV']);

        return view('recepcion.paciente', [
            'p'             => $p,
            'seguros'       => $this->recepcion->segurosDelPaciente($codPac),
            'aseguradoras'  => DB::table('ASEGURADORA')->orderBy('DES_ASE')->get(),
            'citas'         => $citas,
        ]);
    }

    // ---------- RF037 ----------

    public function checkin(string $codCit)
    {
        try {
            $this->recepcion->checkin($codCit);
        } catch (DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()]);
        }
        return back()->with('ok', "Llegada registrada para la cita {$codCit}.");
    }

    public function inasistencia(string $codCit)
    {
        try {
            $this->recepcion->marcarInasistencia($codCit);
        } catch (DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()]);
        }
        return back()->with('ok', "La cita {$codCit} quedó como «No asistio» (RN23).");
    }

    // ---------- RF038 ----------

    public function guardarSeguro(Request $request, string $codPac)
    {
        $d = $request->validate([
            'aseguradora' => 'required|exists:ASEGURADORA,COD_ASE',
            'afiliacion'  => 'required|string|max:15',
            'vencimiento' => 'required|date',
        ], [], ['afiliacion' => 'número de afiliación', 'vencimiento' => 'fecha de vencimiento']);

        abort_if(!DB::table('PACIENTE')->where('COD_PAC', $codPac)->exists(), 404);

        try {
            $this->recepcion->registrarSeguro($codPac, $d['aseguradora'], trim($d['afiliacion']), $d['vencimiento']);
        } catch (DomainException $e) {
            return back()->withErrors(['seguro' => $e->getMessage()])->withInput();
        }
        return back()->with('ok', 'Póliza validada como Vigente.');
    }

    public function revalidarSeguro(string $codSeg)
    {
        try {
            $est = $this->recepcion->revalidarSeguro($codSeg);
        } catch (DomainException $e) {
            return back()->withErrors(['seguro' => $e->getMessage()]);
        }
        return back()->with('ok', "Póliza revalidada: {$est}.");
    }

    public function cancelarCita(Request $request, string $codCit)
    {
        $d = $request->validate(['motivo' => 'required|string|min:10|max:150'],
            ['motivo.min' => 'Escribe un motivo de al menos 10 caracteres.']);

        try {
            $msg = $this->recepcion->cancelarPorClinica($codCit, trim($d['motivo']));
        } catch (DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()]);
        }
        return back()->with('ok', $msg);
    }

    public function reprogramarForm(string $codCit)
    {
        $c = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->where('c.COD_CIT', $codCit)
            ->first(['c.*', 'p.NOM_PAC', 'p.APE_PAC', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']);
        abort_if(!$c, 404);

        return view('recepcion.reprogramar', [
            'cita'     => $c,
            'minFecha' => Carbon::today()->toDateString(),
            'maxFecha' => Carbon::today()->addDays((int) config('cies.dias_max_anticipacion'))->toDateString(),
        ]);
    }

    public function reprogramar(Request $request, string $codCit)
    {
        $d = $request->validate([
            'fecha'  => 'required|date_format:Y-m-d',
            'hora'   => 'required|date_format:H:i',
            'motivo' => 'required|string|min:10|max:150',
        ], ['motivo.min' => 'Escribe un motivo de al menos 10 caracteres.']);

        try {
            $c = $this->recepcion->reprogramarPorClinica($codCit, $d['fecha'], $d['hora'], trim($d['motivo']));
        } catch (DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()])->withInput();
        }

        return redirect()->route('recepcion.paciente', $c->COD_PAC)
            ->with('ok', 'Cita reprogramada sin costo al ' . Carbon::parse($c->FEC_CIT)->format('d/m/Y') . ' ' . substr($c->HOR_CIT, 0, 5) . '.');
    }

    /** Horas libres de un médico (para el formulario de reprogramación). */
    public function horarios(Request $request, \App\Services\CitaService $citas)
    {
        $d = $request->validate(['medico' => 'required|string|max:9', 'fecha' => 'required|date_format:Y-m-d']);
        return response()->json(array_column($citas->horariosLibres($d['medico'], Carbon::createFromFormat('Y-m-d', $d['fecha'])), 'hora'));
    }
}
