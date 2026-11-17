<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\CitaService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GestionController extends Controller
{
    public function citas(Request $request): View
    {
        $d = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d'], 'buscar' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', 'string', 'max:20']]);
        $citas = DB::table('CITA as c')->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->leftJoin('MEDICO_ATENCION as a', 'a.COD_CIT', '=', 'c.COD_CIT')
            ->when($d['fecha'] ?? null, fn ($q, $v) => $q->where('c.FEC_CIT', $v))
            ->when($d['estado'] ?? null, fn ($q, $v) => $q->where('c.EST_CIT', $v))
            ->when($d['buscar'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('p.NOM_PAC', 'like', "%{$v}%")->orWhere('p.APE_PAC', 'like', "%{$v}%")->orWhere('p.CI_PAC', 'like', "%{$v}%")))
            ->select('c.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.TEL_PAC', 'e.NOM_EMP', 'e.APE_EMP', 's.NOM_SRV', 'a.ESTADO as ATENCION')
            ->orderByDesc('c.FEC_CIT')->orderBy('c.HOR_CIT')->paginate(30)->withQueryString();
        return view('admin.gestion.citas', compact('citas'));
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
            Auditoria::registrar('Registrar llegada', 'Baja', 'DOM04', 'CITA', $codigo, 'Paciente registrado en espera', codPac: $c->COD_PAC);
        });
        return back()->with('ok', 'Llegada registrada.');
    }

    public function cancelar(Request $request, string $codigo): RedirectResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:200']]);
        $monto = app(\App\Services\SaldoPacienteService::class)->cancelar($codigo, null, $d['motivo']);
        return back()->with('ok', 'Cita cancelada. Saldo conservado: Bs '.number_format($monto, 2));
    }

    public function reprogramar(Request $request, string $codigo, CitaService $servicio): RedirectResponse
    {
        $d = $request->validate(['fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'hora' => ['required', 'date_format:H:i'], 'motivo' => ['required', 'string', 'min:10', 'max:200']]);
        DB::transaction(function () use ($codigo, $d, $servicio) {
            $cita = DB::table('CITA')->where('COD_CIT', $codigo)->first();
            abort_unless($cita, 404);
            DB::table('EMPLEADO')->where('COD_EMP', $cita->COD_EMP)->lockForUpdate()->first();
            $cita = DB::table('CITA')->where('COD_CIT', $codigo)->lockForUpdate()->first();
            abort_unless($cita->EST_CIT === 'Confirmada', 422, 'Solo se pueden reprogramar citas confirmadas.');
            abort_if(DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->where('ESTADO', 'En consulta')->exists(), 422, 'La consulta ya comenzó.');
            $horario = collect($servicio->horariosLibres($cita->COD_EMP, Carbon::parse($d['fecha'])))->firstWhere('hora', $d['hora']);
            abort_unless($horario, 422, 'El horario no está disponible para este médico.');
            DB::table('CITA')->where('COD_CIT', $codigo)->update(['FEC_CIT' => $d['fecha'], 'HOR_CIT' => $d['hora'].':00', 'COD_CON' => $horario['cod_con']]);
            DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->delete();
            Auditoria::registrar('Reprogramar cita', 'Media', 'DOM04', 'CITA', $codigo, "De {$cita->FEC_CIT} {$cita->HOR_CIT} a {$d['fecha']} {$d['hora']}. Motivo: {$d['motivo']}", codPac: $cita->COD_PAC);
        });
        return back()->with('ok', 'Cita reprogramada.');
    }

    public function caja(Request $request): View
    {
        $d = $request->validate(['desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde']]);
        $cajas = DB::table('CAJA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->when($d['desde'] ?? null, fn ($q, $v) => $q->whereDate('c.FECAPE_CAJ', '>=', $v))
            ->when($d['hasta'] ?? null, fn ($q, $v) => $q->whereDate('c.FECAPE_CAJ', '<=', $v))
            ->select('c.*', 'e.NOM_EMP', 'e.APE_EMP')
            ->selectSub(DB::table('PAGO')->selectRaw('COALESCE(SUM(MON_PAG),0)')->whereColumn('COD_CAJ', 'c.COD_CAJ')->where('EST_PAG', 'Confirmado'), 'COBRADO')
              ->selectSub(DB::table('CAJA_EXAMEN')->selectRaw('COALESCE(SUM(MONTO),0)')->whereColumn('COD_CAJ', 'c.COD_CAJ')->where('ESTADO', 'Confirmado'), 'COBRADO_EXAMEN')
              ->selectSub(DB::table('CAJA_MEDICAMENTO')->selectRaw('COALESCE(SUM(MONTO),0)')->whereColumn('COD_CAJ', 'c.COD_CAJ')->where('ESTADO', 'Confirmado'), 'COBRADO_MEDICAMENTO')
            ->orderByDesc('c.FECAPE_CAJ')->paginate(30)->withQueryString();
          $cajas->getCollection()->each(function ($caja) { $caja->COBRADO = (float) $caja->COBRADO + (float) $caja->COBRADO_EXAMEN + (float) $caja->COBRADO_MEDICAMENTO; });
        $pagos = DB::table('PAGO as p')->join('CITA as c', 'c.COD_CIT', '=', 'p.COD_CIT')->join('PACIENTE as a', 'a.COD_PAC', '=', 'c.COD_PAC')
            ->join('METODO_PAGO as m', 'm.COD_MET', '=', 'p.COD_MET')->select('p.*', 'a.NOM_PAC', 'a.APE_PAC', 'm.DES_MET')->orderByDesc('p.FEC_PAG')->limit(50)->get();
          $examenes = DB::table('CAJA_EXAMEN as x')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'x.COD_ORD')->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')->orderByDesc('x.id')->limit(50)->get(['x.*', 'p.NOM_PAC', 'p.APE_PAC', 's.NOM_SRV']);
          $medicamentos = DB::table('CAJA_MEDICAMENTO as x')->join('PACIENTE as p', 'p.COD_PAC', '=', 'x.COD_PAC')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'x.COD_MED')->orderByDesc('x.id')->limit(50)->get(['x.*', 'p.NOM_PAC', 'p.APE_PAC', 'm.DES_MED']);
          return view('admin.gestion.caja', compact('cajas', 'pagos', 'examenes', 'medicamentos'));
    }

    public function internacion(): View
    {
        $camas = DB::table('CAMA as c')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')->select('c.*', 's.NOM_SAL', 's.UBI_SAL')->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')->get();
        $internaciones = DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')->select('i.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'c.NRO_CAM', 's.NOM_SAL', 'e.NOM_EMP', 'e.APE_EMP')
            ->orderByDesc('i.FECING_INT')->paginate(30);
        $pacientes = DB::table('PACIENTE')->orderBy('APE_PAC')->get();
        $medicos = DB::table('EMPLEADO as e')->join('USUARIO as u', 'u.COD_EMP', '=', 'e.COD_EMP')->where('u.COD_ROL', 'ROL003')->where('u.EST_USR', 'Activo')->select('e.COD_EMP', 'e.NOM_EMP', 'e.APE_EMP')->distinct()->get();
        return view('admin.gestion.internacion', compact('camas', 'internaciones', 'pacientes', 'medicos'));
    }

    public function ingresar(Request $request): RedirectResponse
    {
        $d = $request->validate(['paciente' => ['required', 'exists:PACIENTE,COD_PAC'], 'cama' => ['required', 'exists:CAMA,COD_CAM'], 'medico' => ['required', 'exists:EMPLEADO,COD_EMP'], 'motivo' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($d) {
            DB::table('PACIENTE')->where('COD_PAC', $d['paciente'])->lockForUpdate()->first();
            $cama = DB::table('CAMA')->where('COD_CAM', $d['cama'])->lockForUpdate()->first();
            abort_unless($cama && $cama->EST_CAM === 'Disponible', 422, 'La cama ya no está disponible.');
            abort_if(DB::table('INTERNACION')->where('COD_PAC', $d['paciente'])->where('EST_INT', 'Internado')->exists(), 422, 'El paciente ya está internado.');
            abort_unless(DB::table('USUARIO')->where('COD_EMP', $d['medico'])->where('COD_ROL', 'ROL003')->where('EST_USR', 'Activo')->exists(), 422, 'El médico no está activo.');
            do { $codigo = 'INT'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT); } while (DB::table('INTERNACION')->where('COD_INT', $codigo)->exists());
            DB::table('INTERNACION')->insert(['COD_INT' => $codigo, 'FECING_INT' => now(), 'MOT_INT' => $d['motivo'], 'EST_INT' => 'Internado', 'COD_PAC' => $d['paciente'], 'COD_CAM' => $d['cama'], 'COD_EMP' => $d['medico']]);
            DB::table('CAMA')->where('COD_CAM', $d['cama'])->update(['EST_CAM' => 'Ocupada']);
            Auditoria::registrar('Ingreso internacion', 'Alta', 'DOM03', 'INTERNACION', $codigo, 'Registro administrativo de ingreso y asignación de cama', codPac: $d['paciente']);
        });
        return back()->with('ok', 'Ingreso registrado y cama asignada.');
    }

    public function alta(string $codigo): RedirectResponse
    {
        DB::transaction(function () use ($codigo) {
            $i = DB::table('INTERNACION')->where('COD_INT', $codigo)->lockForUpdate()->first();
            abort_unless($i, 404);
            abort_unless($i->EST_INT === 'Internado', 422, 'La internación ya está cerrada.');
            DB::table('CAMA')->where('COD_CAM', $i->COD_CAM)->lockForUpdate()->first();
            DB::table('INTERNACION')->where('COD_INT', $codigo)->update(['EST_INT' => 'Alta', 'FECALT_INT' => now()]);
            DB::table('CAMA')->where('COD_CAM', $i->COD_CAM)->update(['EST_CAM' => 'Limpieza']);
            Auditoria::registrar('Alta administrat.', 'Alta', 'DOM03', 'INTERNACION', $codigo, 'Cierre administrativo de internación; cama enviada a limpieza', codPac: $i->COD_PAC);
        });
        return back()->with('ok', 'Internación cerrada; cama en limpieza.');
    }

    public function cama(Request $request, string $codigo): RedirectResponse
    {
        $request->validate(['estado' => ['required', Rule::in(['Disponible'])]]);
        DB::transaction(function () use ($codigo) {
            $c = DB::table('CAMA')->where('COD_CAM', $codigo)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->EST_CAM === 'Limpieza' && !DB::table('INTERNACION')->where('COD_CAM', $codigo)->where('EST_INT', 'Internado')->exists(), 422, 'La cama no puede liberarse.');
            DB::table('CAMA')->where('COD_CAM', $codigo)->update(['EST_CAM' => 'Disponible']);
            Auditoria::registrar('Liberar cama', 'Media', 'DOM03', 'CAMA', $codigo, 'Limpieza completada; cama disponible');
        });
        return back()->with('ok', 'Cama disponible.');
    }
}
