<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\MedicoService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request, MedicoService $medico): View
    {
        $d = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'], 'vista' => ['nullable', Rule::in(['dia', 'semana', 'mes'])],
            'estado' => ['nullable', Rule::in(['Confirmada', 'En espera', 'En consulta', 'Atendida', 'Cancelada', 'Expirada', 'No asistio', 'Pendiente de pago'])],
            'buscar' => ['nullable', 'string', 'max:100'], 'servicio' => ['nullable', 'string', 'max:8'],
            'dia' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $fecha = Carbon::parse($d['fecha'] ?? today()->toDateString());
        $vista = $d['vista'] ?? 'mes';
        $desde = $fecha->copy();
        $hasta = $fecha->copy();
        if ($vista === 'semana') {
            $desde->startOfWeek();
            $hasta->endOfWeek();
        }
        if ($vista === 'mes') {
            $desde->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $hasta->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        }
        $emp = $medico->empleado();
        $citas = DB::table('CITA as c')->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')
            ->leftJoin('MEDICO_ATENCION as a', 'a.COD_CIT', '=', 'c.COD_CIT')
            ->where('c.COD_EMP', $emp)->whereBetween('c.FEC_CIT', [$desde->toDateString(), $hasta->toDateString()])
            ->when($d['servicio'] ?? '', fn ($q, $s) => $q->where('c.COD_SRV', $s))
            ->when($d['buscar'] ?? '', fn ($q, $b) => $q->where(fn ($q) => $q->where('p.NOM_PAC', 'like', "%{$b}%")
                ->orWhere('p.APE_PAC', 'like', "%{$b}%")->orWhere('p.CI_PAC', 'like', "%{$b}%")))
            ->select('c.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.TEL_PAC', 's.NOM_SRV', 's.PRE_SRV', 'k.NOM_CON', 'a.ESTADO')
            ->orderBy('c.FEC_CIT')->orderBy('c.HOR_CIT')->get()
            ->each(function ($c) {
                $c->estado = $c->EST_CIT === 'Confirmada' ? ($c->ESTADO ?? 'Confirmada') : $c->EST_CIT;
            })->when($d['estado'] ?? '', fn ($items, $e) => $items->where('estado', $e))->values();
        $pacientes = $citas->pluck('COD_PAC');
        $alergias = DB::table('ALERGIA as a')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'a.COD_HIS')
            ->whereIn('h.COD_PAC', $pacientes)->where('a.SEV_ALE', 'Grave')->select('a.*', 'h.COD_PAC')->get()->groupBy('COD_PAC');
        $pagos = DB::table('PAGO')->whereIn('COD_CIT', $citas->pluck('COD_CIT'))->where('EST_PAG', 'Confirmado')
            ->select('COD_CIT')->selectRaw("SUM(MON_PAG) as total, MAX(CASE WHEN TIP_PAG = 'Total' THEN 1 ELSE 0 END) as liquidado")->groupBy('COD_CIT')->get()->keyBy('COD_CIT');
        $saldos = DB::table('SALDO_APLICACION')->whereIn('COD_CIT', $citas->pluck('COD_CIT'))
            ->select('COD_CIT')->selectRaw('SUM(MONTO) as total')->groupBy('COD_CIT')->get();
        foreach ($saldos as $saldo) {
            if ($pagos->has($saldo->COD_CIT)) {
                $pagos[$saldo->COD_CIT]->total += (float) $saldo->total;
            } else {
                $pagos[$saldo->COD_CIT] = (object) ['total' => (float) $saldo->total, 'liquidado' => 0];
            }
        }
        $notasAdministrativas = DB::table('HISTORIA_CLINICA')->whereIn('COD_PAC', $pacientes)
            ->whereNotNull('NOTADM_HIS')->get()->groupBy('COD_PAC');
        $diaSeleccionado = $d['dia'] ?? ($fecha->isSameMonth(today()) ? today()->toDateString() : $fecha->copy()->startOfMonth()->toDateString());
        if ($diaSeleccionado < $desde->toDateString() || $diaSeleccionado > $hasta->toDateString()) {
            $diaSeleccionado = $fecha->toDateString();
        }
        $diasCalendario = collect();
        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            $diasCalendario->push($dia->copy());
        }

        return view('medico.dashboard', [
            'citas' => $citas, 'fecha' => $fecha, 'vista' => $vista, 'desde' => $desde, 'hasta' => $hasta,
            'alergias' => $alergias, 'pagos' => $pagos, 'notasAdministrativas' => $notasAdministrativas,
            'diasCalendario' => $diasCalendario, 'diaSeleccionado' => $diaSeleccionado,
            'estados' => ['Confirmada', 'En espera', 'En consulta', 'Atendida', 'Cancelada', 'No asistio', 'Pendiente de pago', 'Expirada'],
            'servicios' => DB::table('CITA as c')->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')->where('c.COD_EMP', $emp)
                ->select('s.COD_SRV', 's.NOM_SRV')->distinct()->get(),
            'interconsultas' => DB::table('INTERCONSULTA as i')->join('CONSULTA as c', 'c.COD_CNS', '=', 'i.COD_CNS')
                ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
                ->where('i.COD_EMP', $emp)->where('i.ESTADO', 'Pendiente')->select('i.*', 'p.NOM_PAC', 'p.APE_PAC')->orderBy('i.FECHA')->get(),
            'notificaciones' => DB::table('NOTIFICACION')->where('COD_USR', auth()->user()->COD_USR)
                ->where('EST_NOT', '!=', 'Leída')->orderByDesc('FEC_NOT')->limit(5)->get(),
        ]);
    }
}
