<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\AgendaRecepcionService;
use App\Services\CitaService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecepcionController extends Controller
{
    public function __construct(private CitaService $servicio) {}

    public function buscarPacientes(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $texto = trim($d['q'] ?? '');
        if (mb_strlen($texto) < 2) {
            return response()->json([]);
        }
        $consulta = DB::table('PACIENTE');
        foreach (preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $consulta->where(function ($q) use ($palabra) {
                $q->where('CI_PAC', 'like', '%'.$palabra.'%')->orWhere('COD_PAC', 'like', '%'.$palabra.'%')
                    ->orWhere('NOM_PAC', 'like', '%'.$palabra.'%')->orWhere('APE_PAC', 'like', '%'.$palabra.'%');
            });
        }
        $pacientes = $consulta->orderBy('APE_PAC')->orderBy('NOM_PAC')->limit(12)->get(['COD_PAC', 'CI_PAC', 'NOM_PAC', 'APE_PAC']);
        return response()->json($pacientes)->header('Cache-Control', 'private, no-store');
    }

    public function especialidadesPaciente(string $paciente, AgendaRecepcionService $agenda): JsonResponse
    {
        abort_unless(DB::table('PACIENTE')->where('COD_PAC', $paciente)->exists(), 404);
        return response()->json($agenda->opciones($paciente))->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): View
    {
        $d = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d'], 'buscar' => ['nullable', 'string', 'max:100'], 'medico' => ['nullable', 'string', 'max:9']]);
        $fecha = $d['fecha'] ?? today()->toDateString();
        $buscar = trim($d['buscar'] ?? '');
        $citas = DB::table('CITA as c')->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')->leftJoin('MEDICO_ATENCION as a', 'a.COD_CIT', '=', 'c.COD_CIT')
            ->where('c.FEC_CIT', $fecha)->when($d['medico'] ?? null, fn ($q, $v) => $q->where('c.COD_EMP', $v))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('p.CI_PAC', 'like', "%{$buscar}%")->orWhere('p.NOM_PAC', 'like', "%{$buscar}%")->orWhere('p.APE_PAC', 'like', "%{$buscar}%")))
            ->select('c.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.TEL_PAC', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV', 's.PRE_SRV', 'k.NOM_CON', 'a.ESTADO as ATENCION')
            ->selectSub(DB::table('PAGO')->selectRaw('COALESCE(SUM(MON_PAG),0)')->whereColumn('COD_CIT', 'c.COD_CIT')->where('EST_PAG', 'Confirmado'), 'PAGADO')
            ->selectSub(DB::table('SALDO_APLICACION')->selectRaw('COALESCE(SUM(MONTO),0)')->whereColumn('COD_CIT', 'c.COD_CIT'), 'CREDITO')
            ->selectSub(DB::table('PAGO')->selectRaw('COUNT(*)')->whereColumn('COD_CIT', 'c.COD_CIT')->where('EST_PAG', 'Confirmado')->where('TIP_PAG', 'Total'), 'LIQUIDADO')
            ->orderBy('c.HOR_CIT')->get()->each(function ($cita) { $cita->PAGADO = (float) $cita->PAGADO + (float) $cita->CREDITO; });
        $pacientes = DB::table('PACIENTE')->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('CI_PAC', 'like', "%{$buscar}%")->orWhere('NOM_PAC', 'like', "%{$buscar}%")->orWhere('APE_PAC', 'like', "%{$buscar}%")->orWhere('COD_PAC', 'like', "%{$buscar}%")))
            ->select('PACIENTE.*')->selectSub(DB::table('USUARIO')->selectRaw('COUNT(*)')->whereColumn('USUARIO.COD_PAC', 'PACIENTE.COD_PAC'), 'CUENTAS')
            ->orderBy('APE_PAC')->limit(100)->get();
        $medicos = DB::table('EMPLEADO as e')->join('USUARIO as u', 'u.COD_EMP', '=', 'e.COD_EMP')->leftJoin('ESPECIALIDAD as s', 's.COD_ESP', '=', 'e.COD_ESP')
            ->where('u.COD_ROL', 'ROL003')->where('u.EST_USR', 'Activo')->select('e.COD_EMP', 'e.NOM_EMP', 'e.APE_EMP', 's.DES_ESP')->distinct()->orderBy('e.APE_EMP')->get();
        $camas = DB::table('CAMA as c')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')->select('c.COD_CAM', 'c.NRO_CAM', 'c.EST_CAM', 's.NOM_SAL')->orderBy('s.NOM_SAL')->get();
        $consultorios = DB::table('CONSULTORIO')->orderBy('NOM_CON')->get();
        $seguros = DB::table('SEGURO_PACIENTE as s')->join('PACIENTE as p', 'p.COD_PAC', '=', 's.COD_PAC')->join('ASEGURADORA as a', 'a.COD_ASE', '=', 's.COD_ASE')
            ->select('s.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'a.DES_ASE')->orderByDesc('s.id')->limit(100)->get();
        $aseguradoras = DB::table('ASEGURADORA')->orderBy('DES_ASE')->get();
        $servicios = $this->servicio->serviciosConsulta();
        $disponibles = !empty($d['medico']) ? $this->servicio->horariosLibres($d['medico'], Carbon::parse($fecha)) : [];
        return view('recepcion.index', compact('citas', 'pacientes', 'medicos', 'camas', 'consultorios', 'seguros', 'aseguradoras', 'servicios', 'disponibles', 'fecha', 'buscar'));
    }

    public function paciente(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'ci' => ['required', 'regex:/^[0-9]{5,10}$/', Rule::unique('PACIENTE', 'CI_PAC')],
            'nombres' => ['required', 'string', 'max:40'], 'apellidos' => ['required', 'string', 'max:40'],
            'sexo' => ['required', Rule::in(['M', 'F'])], 'nacimiento' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'estado_civil' => ['required', Rule::in(['Soltero', 'Casado', 'Divorciado', 'Viudo', 'Concubino'])],
            'telefono' => ['required', 'regex:/^[0-9]{7,8}$/'], 'correo' => ['required', 'email', 'max:60'], 'direccion' => ['required', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($d) {
            do { $codigo = 'PAC'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('PACIENTE')->where('COD_PAC', $codigo)->exists());
            DB::table('PACIENTE')->insert(['COD_PAC' => $codigo, 'CI_PAC' => $d['ci'], 'NOM_PAC' => trim($d['nombres']), 'APE_PAC' => trim($d['apellidos']), 'SEX_PAC' => $d['sexo'], 'FECNAC_PAC' => $d['nacimiento'], 'ESTCIV_PAC' => $d['estado_civil'], 'TEL_PAC' => $d['telefono'], 'COR_PAC' => mb_strtolower(trim($d['correo'])), 'DIR_PAC' => trim($d['direccion'])]);
            Auditoria::registrar('Registrar paciente', 'Media', 'DOM01', 'PACIENTE', $codigo, 'Ficha creada en recepción', codPac: $codigo);
        });
        return redirect()->route('recepcion.dashboard', ['buscar' => $d['ci']])->with('ok', 'Ficha registrada. Envía el enlace de activación al correo del paciente desde la lista.');
    }

    public function reservar(Request $request, AgendaRecepcionService $agenda): RedirectResponse
    {
        $d = $request->validate(['paciente' => ['required', 'exists:PACIENTE,COD_PAC'], 'especialidad' => ['required', 'string', 'max:7'], 'fecha' => ['required', 'date_format:Y-m-d'], 'hora' => ['required', 'date_format:H:i'], 'motivo' => ['required', 'string', 'max:200']]);
        if (!DB::table('USUARIO')->where('COD_PAC', $d['paciente'])->where('COD_ROL', 'ROL006')->where('EST_USR', 'Activo')->exists()) {
            return back()->withInput()->withErrors(['paciente' => 'El paciente necesita una cuenta activa. Busca su ficha y envía el enlace de activación desde recepción.']);
        }
        try {
            $cita = $agenda->reservar($d['paciente'], $d['especialidad'], $d['fecha'], $d['hora'], $d['motivo']);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['cita' => $e->getMessage()]);
        }
        return redirect()->route('recepcion.dashboard', ['fecha' => $d['fecha']])->with('ok', "Reserva {$cita->COD_CIT} creada. El paciente debe pagar el anticipo del 50 % en los próximos 15 minutos desde su portal.");
    }

    public function llegada(string $codigo): RedirectResponse
    {
        DB::transaction(function () use ($codigo) {
            $c = DB::table('CITA')->where('COD_CIT', $codigo)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->EST_CIT === 'Confirmada' && $c->FEC_CIT === today()->toDateString(), 422, 'Solo se registra la llegada de citas confirmadas de hoy.');
            $a = DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->first();
            abort_if($a && $a->ESTADO === 'En consulta', 422, 'La consulta ya comenzó.');
            DB::table('MEDICO_ATENCION')->updateOrInsert(['COD_CIT' => $codigo], ['ESTADO' => 'En espera', 'LLEGADA' => $a?->LLEGADA ?? now(), 'INICIO' => null]);
            Auditoria::registrar('Registrar llegada', 'Baja', 'DOM04', 'CITA', $codigo, 'Llegada registrada por recepción', codPac: $c->COD_PAC);
        });
        return back()->with('ok', 'Llegada registrada; el médico verá al paciente en espera.');
    }

    public function reprogramar(Request $request, string $codigo): RedirectResponse
    {
        $d = $request->validate(['fecha' => ['required', 'date_format:Y-m-d'], 'hora' => ['required', 'date_format:H:i'], 'motivo' => ['required', 'string', 'min:10', 'max:200']]);
        DB::transaction(function () use ($codigo, $d) {
            $c = DB::table('CITA')->where('COD_CIT', $codigo)->first();
            abort_unless($c, 404);
            DB::table('EMPLEADO')->where('COD_EMP', $c->COD_EMP)->lockForUpdate()->first();
            $c = DB::table('CITA')->where('COD_CIT', $codigo)->lockForUpdate()->first();
            abort_unless($c && $c->EST_CIT === 'Confirmada', 422, 'Solo se reprograman citas confirmadas.');
            abort_if(Carbon::parse($c->FEC_CIT.' '.$c->HOR_CIT)->lt(now()->addDay()), 422, 'La cita debe reprogramarse con al menos 24 horas de anticipación.');
            $nueva = Carbon::parse($d['fecha'].' '.$d['hora']);
            abort_if($nueva->lt(now()->addDay()) || $nueva->gt(today()->addDays(config('cies.dias_max_anticipacion'))->endOfDay()), 422, 'La nueva fecha debe estar dentro del período permitido y al menos a 24 horas.');
            abort_if(DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->where('ESTADO', 'En consulta')->exists(), 422, 'La consulta ya comenzó.');
            $slot = collect($this->servicio->horariosLibres($c->COD_EMP, $nueva))->firstWhere('hora', $d['hora']);
            abort_unless($slot, 422, 'El horario no está disponible para el médico.');
            DB::table('CITA')->where('COD_CIT', $codigo)->update(['FEC_CIT' => $d['fecha'], 'HOR_CIT' => $d['hora'].':00', 'COD_CON' => $slot['cod_con']]);
            DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->delete();
            Auditoria::registrar('Reprogramar cita', 'Media', 'DOM04', 'CITA', $codigo, "De {$c->FEC_CIT} {$c->HOR_CIT} a {$d['fecha']} {$d['hora']}. Motivo: {$d['motivo']}", codPac: $c->COD_PAC);
        });
        return back()->with('ok', 'Cita reprogramada; los pagos permanecen asociados.');
    }

    public function cancelar(Request $request, string $codigo): RedirectResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:200']]);
        $monto = app(\App\Services\SaldoPacienteService::class)->cancelar($codigo, null, $d['motivo']);
        return back()->with('ok', 'Cita cancelada. Saldo conservado: Bs '.number_format($monto, 2));
    }

    public function seguro(Request $request): RedirectResponse
    {
        $d = $request->validate(['paciente' => ['required', 'exists:PACIENTE,COD_PAC'], 'aseguradora' => ['required', 'exists:ASEGURADORA,COD_ASE'], 'afiliacion' => ['required', 'string', 'max:15'], 'desde' => ['required', 'date_format:Y-m-d'], 'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'], 'estado' => ['required', Rule::in(['Vigente', 'Vencido', 'Pendiente', 'Rechazado'])]]);
        abort_if($d['estado'] === 'Vigente' && (today()->lt(Carbon::parse($d['desde'])) || today()->gt(Carbon::parse($d['hasta']))), 422, 'Las fechas no corresponden a un seguro vigente.');
        $empleado = auth()->user()->COD_EMP;
        abort_unless($empleado, 403, 'Tu cuenta no está vinculada a un empleado.');
        DB::transaction(function () use ($d, $empleado) {
            do { $codigo = 'SEG'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('SEGURO_PACIENTE')->where('COD_SEG', $codigo)->exists());
            DB::table('SEGURO_PACIENTE')->insert(['COD_SEG' => $codigo, 'NROAFIL_SEG' => $d['afiliacion'], 'FECVAL_SEG' => $d['desde'], 'FECVEN_SEG' => $d['hasta'], 'EST_SEG' => $d['estado'], 'COD_PAC' => $d['paciente'], 'COD_ASE' => $d['aseguradora'], 'COD_EMP' => $empleado]);
            Auditoria::registrar('Registrar seguro', 'Media', 'DOM01', 'SEGURO_PACIENTE', $codigo, 'Estado de seguro registrado por recepción: '.$d['estado'], codPac: $d['paciente']);
        });
        return back()->with('ok', 'Seguro registrado. La cobertura debe verificarse con la aseguradora.');
    }

    public function recordatorio(string $codigo): RedirectResponse
    {
        DB::transaction(function () use ($codigo) {
            $c = DB::table('CITA')->where('COD_CIT', $codigo)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->EST_CIT === 'Confirmada' && Carbon::parse($c->FEC_CIT.' '.$c->HOR_CIT)->isFuture(), 422, 'Solo se recuerdan citas confirmadas futuras.');
            $usuarios = DB::table('USUARIO')->where('COD_PAC', $c->COD_PAC)->where('COD_ROL', 'ROL006')->where('EST_USR', 'Activo')->get();
            abort_if($usuarios->isEmpty(), 422, 'El paciente no tiene una cuenta activa para recibir el aviso.');
            foreach ($usuarios as $u) {
                do { $id = 'NOT'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('NOTIFICACION')->where('COD_NOT', $id)->exists());
                DB::table('NOTIFICACION')->insert(['COD_NOT' => $id, 'FEC_NOT' => now(), 'TIP_NOT' => 'Recordatorio de cita', 'MSG_NOT' => 'Recuerda tu cita del '.Carbon::parse($c->FEC_CIT)->format('d/m/Y').' a las '.substr($c->HOR_CIT, 0, 5).'.', 'CAN_NOT' => 'Sistema', 'EST_NOT' => 'Enviada', 'REF_NOT' => $codigo, 'COD_USR' => $u->COD_USR]);
            }
            Auditoria::registrar('Recordatorio cita', 'Baja', 'DOM04', 'CITA', $codigo, 'Aviso enviado al portal del paciente', codPac: $c->COD_PAC);
        });
        return back()->with('ok', 'Recordatorio enviado al portal del paciente.');
    }
}
