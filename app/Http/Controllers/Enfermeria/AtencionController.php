<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Support\Firma;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AtencionController extends Controller
{
    private function empleado(): string
    {
        abort_unless(auth()->user()->COD_EMP, 403);
        return auth()->user()->COD_EMP;
    }

    private function internacion(string $codigo): object
    {
        return DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')
            ->where('i.COD_INT', $codigo)->select('i.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'c.NRO_CAM', 'c.EST_CAM', 's.NOM_SAL', 'e.NOM_EMP', 'e.APE_EMP')->first() ?? abort(404);
    }

    private function activa(object $internacion): void
    {
        abort_unless($internacion->EST_INT === 'Internado', 422, 'La internación ya terminó.');
    }

    private function auditar(string $accion, string $tabla, string $codigo, string $paciente): void
    {
        Auditoria::registrar($accion, 'Media', 'DOM03', $tabla, $codigo, 'Atención de enfermería', codPac: $paciente);
    }

    /** El insumo debe estar bloqueado por la transacción antes de llamar a este método. */
    private function ocupacion(string $insumo, ?string $excluir = null): int
    {
        $dosis = DB::table('DOSIS as d')->leftJoin('ENFERMERIA_UNIDADES_DOSIS as u', 'u.COD_DOS', '=', 'd.COD_DOS')
            ->where('d.COD_INS', $insumo)->whereIn('d.EST_DOS', ['Programada', 'Administrada'])
            ->when($excluir, fn ($q) => $q->where('d.COD_DOS', '!=', $excluir))
            ->get(['d.EST_DOS', 'u.PROGRAMADAS', 'u.USADAS']);
        $total = 0;
        foreach ($dosis as $d) {
            $cantidad = $d->EST_DOS === 'Administrada' ? $d->USADAS : $d->PROGRAMADAS;
            if ($cantidad === null) {
                throw ValidationException::withMessages(['insumo' => 'Hay dosis anteriores sin unidades verificadas. Revísalas antes de usar o cerrar este insumo.']);
            }
            $total += (int) round((float) $cantidad * 1000);
        }
        return $total;
    }

    public function index(): View
    {
        $this->empleado();
        $pacientes = DB::table('INTERNACION as i')->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')
            ->join('CAMA as c', 'c.COD_CAM', '=', 'i.COD_CAM')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')
            ->where('i.EST_INT', 'Internado')->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')
            ->select('i.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'c.NRO_CAM', 'c.EST_CAM', 's.NOM_SAL')
            ->selectSub(DB::table('ENFERMERIA_NOTA as n')->select('n.TEXTO')->whereColumn('n.COD_INT', 'i.COD_INT')->orderByDesc('n.id')->limit(1), 'ULTIMA_NOTA')->get();
        $camas = DB::table('CAMA as c')->join('SALA as s', 's.COD_SAL', '=', 'c.COD_SAL')->orderBy('s.NOM_SAL')->orderBy('c.NRO_CAM')->get();
        $pendientes = DB::table('DOSIS as d')->join('INTERNACION as i', 'i.COD_INT', '=', 'd.COD_INT')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'i.COD_PAC')->join('DETALLE_RECETA as r', 'r.COD_DRE', '=', 'd.COD_DRE')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'r.COD_MED')->where('i.EST_INT', 'Internado')->where('d.EST_DOS', 'Programada')
            ->orderBy('d.FECPRG_DOS')->select('d.*', 'p.NOM_PAC', 'p.APE_PAC', 'm.DES_MED')->get();
        Auditoria::registrar('Leer', 'Media', 'DOM03', 'INTERNACION', 'TURNO', 'Listado de pacientes y dosis del turno');
        return view('enfermeria.pacientes', compact('pacientes', 'camas', 'pendientes'));
    }

    public function show(string $internacion): View
    {
        $i = $this->internacion($internacion);
        $this->auditar('Leer', 'INTERNACION', $i->COD_INT, $i->COD_PAC);
        $historias = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $i->COD_PAC)->pluck('COD_HIS');
        $alergias = DB::table('ALERGIA')->whereIn('COD_HIS', $historias)->get();
        $antecedentes = DB::table('ANTECEDENTE')->whereIn('COD_HIS', $historias)->get();
        $signos = DB::table('SIGNO_VITAL')->where('COD_PAC', $i->COD_PAC)->orderByDesc('FEC_SIV')->limit(20)->get();
        $notas = DB::table('ENFERMERIA_NOTA as n')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'n.COD_EMP')->where('n.COD_INT', $internacion)->orderByDesc('n.id')->get();
        $balances = DB::table('ENFERMERIA_BALANCE')->where('COD_INT', $internacion)->orderByDesc('id')->limit(50)->get();
        $recetas = DB::table('DETALLE_RECETA as d')->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')
            ->whereIn('c.COD_HIS', $historias)->where('r.EST_REC', 'Activa')->select('d.*', 'r.FEC_REC', 'r.OBS_REC', 'm.DES_MED', 'v.DES_VIA')->get();
        $insumos = DB::table('INSUMO_TERCERO as x')->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'x.COD_MED')
            ->leftJoin('ENFERMERIA_CIERRE_INSUMO as c', 'c.COD_INS', '=', 'x.COD_INS')
            ->where('x.COD_PAC', $i->COD_PAC)->where('x.EST_INS', 'Verificado')->select('x.*', 'm.DES_MED', 'c.TIPO', 'c.CANTIDAD as CERRADO')
            ->selectSub(DB::table('COMPROBANTE_ENTREGA')->select('NRO_CEN')->whereColumn('COD_INS', 'x.COD_INS')->where('EST_CEN', 'Emitido')->limit(1), 'NRO_CEN')->get();
        foreach ($insumos as $insumo) {
            $movimientos = DB::table('DOSIS as d')->leftJoin('ENFERMERIA_UNIDADES_DOSIS as u', 'u.COD_DOS', '=', 'd.COD_DOS')
                ->where('d.COD_INS', $insumo->COD_INS)->whereIn('d.EST_DOS', ['Programada', 'Administrada'])->get(['d.EST_DOS', 'u.PROGRAMADAS', 'u.USADAS']);
            $insumo->PENDIENTE_REVISION = $movimientos->contains(fn ($d) => $d->EST_DOS === 'Programada' ? $d->PROGRAMADAS === null : $d->USADAS === null);
            $insumo->RESERVADO = $movimientos->where('EST_DOS', 'Programada')->sum('PROGRAMADAS');
            $insumo->UTILIZADO = $movimientos->where('EST_DOS', 'Administrada')->sum('USADAS');
            $insumo->DISPONIBLE = round((float) $insumo->CAN_INS - (float) $insumo->RESERVADO - (float) $insumo->UTILIZADO, 3);
        }
        $dosis = DB::table('DOSIS as d')->join('DETALLE_RECETA as r', 'r.COD_DRE', '=', 'd.COD_DRE')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'r.COD_MED')->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'r.COD_VIA')
            ->leftJoin('INCIDENCIA_DOSIS as inc', 'inc.COD_DOS', '=', 'd.COD_DOS')
            ->leftJoin('ENFERMERIA_UNIDADES_DOSIS as u', 'u.COD_DOS', '=', 'd.COD_DOS')
            ->leftJoin('ENFERMERIA_CIERRE_INSUMO as cierre', 'cierre.COD_INS', '=', 'd.COD_INS')
            ->where('d.COD_INT', $internacion)->orderByDesc('d.FECPRG_DOS')->select('d.*', 'm.DES_MED', 'v.DES_VIA', 'r.DOS_DRE', 'r.FRE_DRE', 'inc.MOT_INC', 'u.PROGRAMADAS', 'u.USADAS', 'cierre.TIPO as INSUMO_CERRADO')->get();
        $revisiones = DB::table('DOSIS as d')->join('INSUMO_TERCERO as x', 'x.COD_INS', '=', 'd.COD_INS')
            ->leftJoin('ENFERMERIA_UNIDADES_DOSIS as u', 'u.COD_DOS', '=', 'd.COD_DOS')
            ->leftJoin('ENFERMERIA_CIERRE_INSUMO as c', 'c.COD_INS', '=', 'x.COD_INS')
            ->where('x.COD_PAC', $i->COD_PAC)->where('d.COD_INT', '!=', $internacion)->whereIn('d.EST_DOS', ['Programada', 'Administrada'])
            ->whereNull('u.COD_DOS')->whereNull('c.COD_INS')->get(['d.COD_DOS', 'd.COD_INT', 'd.COD_INS']);
        return view('enfermeria.paciente', compact('i', 'alergias', 'antecedentes', 'signos', 'notas', 'balances', 'recetas', 'insumos', 'dosis', 'revisiones'));
    }

    public function signos(Request $request, string $internacion): RedirectResponse
    {
        $i = $this->internacion($internacion); $this->activa($i);
        $d = $request->validate(['sistolica' => 'required|integer|min:1|max:400|gt:diastolica', 'diastolica' => 'required|integer|min:1|max:300', 'pulso' => 'required|integer|min:1|max:300', 'temperatura' => 'required|numeric|between:30,45']);
        DB::transaction(function () use ($i, $d) {
            $codigo = sprintf('SIV%07d', (int) DB::table('SIGNO_VITAL')->max('id') + 1);
            DB::table('SIGNO_VITAL')->insert(['COD_SIV' => $codigo, 'FEC_SIV' => now(), 'SIS_SIV' => $d['sistolica'], 'DIA_SIV' => $d['diastolica'], 'PUL_SIV' => $d['pulso'], 'TEM_SIV' => $d['temperatura'], 'COD_PAC' => $i->COD_PAC, 'COD_EMP' => $this->empleado()]);
            $this->auditar('Registrar signos', 'SIGNO_VITAL', $codigo, $i->COD_PAC);
        });
        return back()->with('ok', 'Signos vitales registrados.');
    }

    public function nota(Request $request, string $internacion): RedirectResponse
    {
        $i = $this->internacion($internacion); $this->activa($i);
        $d = $request->validate(['texto' => 'required|string|max:500']);
        DB::transaction(function () use ($i, $d) {
            $id = DB::table('ENFERMERIA_NOTA')->insertGetId(['COD_INT' => $i->COD_INT, 'COD_EMP' => $this->empleado(), 'FECHA' => now(), 'TEXTO' => trim($d['texto'])]);
            $this->auditar('Registrar nota', 'ENFERMERIA_NOTA', (string) $id, $i->COD_PAC);
        });
        return back()->with('ok', 'Nota registrada. Las correcciones se agregan como una nueva nota.');
    }

    public function balance(Request $request, string $internacion): RedirectResponse
    {
        $i = $this->internacion($internacion); $this->activa($i);
        $d = $request->validate(['ingreso' => 'required|numeric|between:0,9999999', 'egreso' => 'required|numeric|between:0,9999999', 'observacion' => 'nullable|string|max:250']);
        DB::transaction(function () use ($i, $d) {
            $id = DB::table('ENFERMERIA_BALANCE')->insertGetId(['COD_INT' => $i->COD_INT, 'COD_EMP' => $this->empleado(), 'FECHA' => now(), 'INGRESO' => $d['ingreso'], 'EGRESO' => $d['egreso'], 'OBSERVACION' => $d['observacion'] ?? null]);
            $this->auditar('Registrar balance', 'ENFERMERIA_BALANCE', (string) $id, $i->COD_PAC);
        });
        return back()->with('ok', 'Balance registrado en mililitros.');
    }

    public function programar(Request $request, string $internacion): RedirectResponse
    {
        $i = $this->internacion($internacion); $this->activa($i);
        $d = $request->validate(['receta' => 'required|string|max:10', 'insumo' => 'required|string|max:10', 'fecha' => 'required|date', 'unidades' => 'required|numeric|between:0.001,32000|decimal:0,3']);
        DB::transaction(function () use ($i, $d) {
            $vigente = DB::table('INTERNACION')->where('COD_INT', $i->COD_INT)->lockForUpdate()->first();
            abort_unless($vigente, 404);
            $this->activa($vigente);
            $ins = DB::table('INSUMO_TERCERO')->where('COD_INS', $d['insumo'])->lockForUpdate()->first();
            $rec = DB::table('DETALLE_RECETA as d')->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')->where('d.COD_DRE', $d['receta'])->where('h.COD_PAC', $i->COD_PAC)->where('r.EST_REC', 'Activa')->first(['d.*']);
            if (!$ins || !$rec || $ins->COD_PAC !== $i->COD_PAC || $ins->COD_MED !== $rec->COD_MED || $ins->EST_INS !== 'Verificado' || $ins->FECVEN_INS < today()->toDateString() || DB::table('ENFERMERIA_CIERRE_INSUMO')->where('COD_INS', $ins->COD_INS)->exists()) {
                throw ValidationException::withMessages(['insumo' => 'Seleccione un insumo verificado y vigente que corresponda al paciente y medicamento recetado.']);
            }
            if (!DB::table('COMPROBANTE_ENTREGA')->where('COD_INS', $ins->COD_INS)->where('EST_CEN', 'Emitido')->exists()) {
                throw ValidationException::withMessages(['insumo' => 'El insumo aún no tiene comprobante de entrega. Reintenta generarlo antes de programar dosis.']);
            }
            $ocupadas = $this->ocupacion($ins->COD_INS);
            if ($ocupadas + (int) round((float) $d['unidades'] * 1000) > (int) $ins->CAN_INS * 1000) {
                throw ValidationException::withMessages(['insumo' => 'No quedan unidades disponibles para programar.']);
            }
            if (DB::table('DOSIS')->where('COD_INT', $i->COD_INT)->where('COD_DRE', $rec->COD_DRE)->where('FECPRG_DOS', \Carbon\Carbon::parse($d['fecha'])->format('Y-m-d H:i:s'))->whereIn('EST_DOS', ['Programada', 'Administrada'])->exists()) {
                throw ValidationException::withMessages(['fecha' => 'Ya existe una dosis de esta indicación a esa hora.']);
            }
            $codigo = sprintf('DOS%07d', (int) DB::table('DOSIS')->max('id') + 1);
            DB::table('DOSIS')->insert(['COD_DOS' => $codigo, 'FECPRG_DOS' => \Carbon\Carbon::parse($d['fecha'])->format('Y-m-d H:i:s'), 'DOS_DOS' => $rec->DOS_DRE, 'EST_DOS' => 'Programada', 'COD_INT' => $i->COD_INT, 'COD_DRE' => $rec->COD_DRE, 'COD_INS' => $ins->COD_INS]);
            DB::table('ENFERMERIA_UNIDADES_DOSIS')->insert(['COD_DOS' => $codigo, 'PROGRAMADAS' => $d['unidades'], 'USADAS' => null]);
            Auditoria::registrar('Programar dosis', 'Media', 'DOM03', 'DOSIS', $codigo, 'Unidades previstas del insumo: '.$d['unidades'], codPac: $i->COD_PAC);
        });
        return back()->with('ok', 'Dosis programada con las unidades indicadas.');
    }

    public function administrar(Request $request, string $dosis): RedirectResponse
    {
        $d = $request->validate(['firma' => 'required|string|max:410000', 'paciente' => 'accepted', 'medicamento' => 'accepted', 'dosis' => 'accepted', 'via' => 'accepted', 'hora' => 'accepted', 'unidades' => 'required|numeric|between:0.001,32000|decimal:0,3']);
        try { Firma::decodificar($d['firma'], 'enfermería'); } catch (DomainException $e) { throw ValidationException::withMessages(['firma' => $e->getMessage()]); }
        DB::transaction(function () use ($dosis, $d) {
            $x = DB::table('DOSIS')->where('COD_DOS', $dosis)->first();
            abort_unless($x, 404);
            $vigente = DB::table('INTERNACION')->where('COD_INT', $x->COD_INT)->lockForUpdate()->first();
            abort_unless($vigente, 404);
            $this->activa($vigente);
            $ins = DB::table('INSUMO_TERCERO')->where('COD_INS', $x->COD_INS)->lockForUpdate()->first();
            $x = DB::table('DOSIS')->where('COD_DOS', $dosis)->lockForUpdate()->first();
            abort_unless($x && $x->EST_DOS === 'Programada', 422, 'La dosis ya fue registrada o no existe.');
            $i = $this->internacion($x->COD_INT); $this->activa($i);
            if (!$ins || $ins->EST_INS !== 'Verificado' || $ins->FECVEN_INS < today()->toDateString() || DB::table('ENFERMERIA_CIERRE_INSUMO')->where('COD_INS', $x->COD_INS)->exists()) {
                throw ValidationException::withMessages(['insumo' => 'El insumo ya no está disponible o está vencido.']);
            }
            if (!DB::table('COMPROBANTE_ENTREGA')->where('COD_INS', $x->COD_INS)->where('EST_CEN', 'Emitido')->exists()) {
                throw ValidationException::withMessages(['insumo' => 'El comprobante de entrega no está emitido.']);
            }
            $unidades = DB::table('ENFERMERIA_UNIDADES_DOSIS')->where('COD_DOS', $dosis)->first();
            if (!$unidades || $unidades->PROGRAMADAS === null) {
                throw ValidationException::withMessages(['unidades' => 'Revisa y registra primero las unidades previstas de esta dosis anterior.']);
            }
            if ($this->ocupacion($x->COD_INS, $dosis) + (int) round((float) $d['unidades'] * 1000) > (int) $ins->CAN_INS * 1000) {
                throw ValidationException::withMessages(['unidades' => 'Las unidades utilizadas exceden el insumo disponible, considerando otras dosis reservadas.']);
            }
            $activa = DB::table('DETALLE_RECETA as d')->join('RECETA as r', 'r.COD_REC', '=', 'd.COD_REC')->where('d.COD_DRE', $x->COD_DRE)->where('r.EST_REC', 'Activa')->exists();
            if (!$activa) { throw ValidationException::withMessages(['receta' => 'La receta ya no está activa.']); }
            DB::table('DOSIS')->where('COD_DOS', $dosis)->update(['EST_DOS' => 'Administrada', 'FECADM_DOS' => now(), 'COD_EMP' => $this->empleado()]);
            DB::table('ENFERMERIA_UNIDADES_DOSIS')->where('COD_DOS', $dosis)->update(['USADAS' => $d['unidades']]);
            DB::table('ENFERMERIA_FIRMA_DOSIS')->insert(['COD_DOS' => $dosis, 'FIRMA' => $d['firma']]);
            Auditoria::registrar('Administrar dosis', 'Media', 'DOM03', 'DOSIS', $dosis, 'Unidades utilizadas del insumo: '.$d['unidades'], codPac: $i->COD_PAC);
        });
        return back()->with('ok', 'Dosis administrada y firmada.');
    }

    public function unidadesAnteriores(Request $request, string $dosis): RedirectResponse
    {
        $this->empleado();
        $d = $request->validate(['unidades' => 'required|numeric|between:0.001,32000|decimal:0,3', 'confirmacion' => 'accepted']);
        DB::transaction(function () use ($dosis, $d) {
            $x = DB::table('DOSIS')->where('COD_DOS', $dosis)->first();
            abort_unless($x, 404);
            $ins = DB::table('INSUMO_TERCERO')->where('COD_INS', $x->COD_INS)->lockForUpdate()->first();
            $x = DB::table('DOSIS')->where('COD_DOS', $dosis)->lockForUpdate()->first();
            abort_unless($ins && $x && in_array($x->EST_DOS, ['Programada', 'Administrada'], true), 422);
            if (DB::table('ENFERMERIA_UNIDADES_DOSIS')->where('COD_DOS', $dosis)->exists() || DB::table('ENFERMERIA_CIERRE_INSUMO')->where('COD_INS', $x->COD_INS)->exists()) {
                throw ValidationException::withMessages(['unidades' => 'La dosis ya tiene unidades registradas o el insumo está cerrado.']);
            }
            $conocidas = DB::table('DOSIS as d')->join('ENFERMERIA_UNIDADES_DOSIS as u', 'u.COD_DOS', '=', 'd.COD_DOS')->where('d.COD_INS', $x->COD_INS)
                ->whereIn('d.EST_DOS', ['Programada', 'Administrada'])->get(['d.EST_DOS', 'u.PROGRAMADAS', 'u.USADAS']);
            $ocupado = $conocidas->sum(fn ($v) => (int) round((float) ($v->EST_DOS === 'Administrada' ? $v->USADAS : $v->PROGRAMADAS) * 1000));
            if ($ocupado + (int) round((float) $d['unidades'] * 1000) > (int) $ins->CAN_INS * 1000) {
                throw ValidationException::withMessages(['unidades' => 'La cantidad supera el insumo recibido considerando las dosis ya revisadas.']);
            }
            DB::table('ENFERMERIA_UNIDADES_DOSIS')->insert(['COD_DOS' => $dosis, 'PROGRAMADAS' => $x->EST_DOS === 'Programada' ? $d['unidades'] : null, 'USADAS' => $x->EST_DOS === 'Administrada' ? $d['unidades'] : null]);
            $i = $this->internacion($x->COD_INT);
            Auditoria::registrar('Revisar unidades', 'Alta', 'DOM03', 'DOSIS', $dosis, 'Unidades de registro anterior verificadas: '.$d['unidades'], codPac: $i->COD_PAC);
        });
        return back()->with('ok', 'Unidades de la dosis anterior registradas.');
    }

    public function omitir(Request $request, string $dosis): RedirectResponse
    {
        $d = $request->validate(['motivo' => 'required|string|max:200']);
        DB::transaction(function () use ($dosis, $d) {
            $referencia = DB::table('DOSIS')->where('COD_DOS', $dosis)->first();
            abort_unless($referencia, 404);
            $vigente = DB::table('INTERNACION')->where('COD_INT', $referencia->COD_INT)->lockForUpdate()->first();
            abort_unless($vigente, 404);
            $this->activa($vigente);
            $x = DB::table('DOSIS')->where('COD_DOS', $dosis)->lockForUpdate()->first();
            abort_unless($x && $x->EST_DOS === 'Programada', 422);
            $i = $this->internacion($x->COD_INT); $this->activa($i);
            $usuario = DB::table('USUARIO')->where('COD_EMP', $i->COD_EMP)->where('COD_ROL', 'ROL003')->where('EST_USR', 'Activo')->first();
            if (!$usuario) { throw ValidationException::withMessages(['motivo' => 'El médico asignado no tiene una cuenta activa para recibir el aviso.']); }
            $codigo = sprintf('INC%07d', (int) DB::table('INCIDENCIA_DOSIS')->max('id') + 1);
            DB::table('DOSIS')->where('COD_DOS', $dosis)->update(['EST_DOS' => 'Omitida', 'COD_EMP' => $this->empleado()]);
            DB::table('INCIDENCIA_DOSIS')->insert(['COD_INC' => $codigo, 'FEC_INC' => now(), 'MOT_INC' => trim($d['motivo']), 'FECNOT_INC' => now(), 'COD_DOS' => $dosis, 'COD_EMP' => $this->empleado()]);
            DB::table('NOTIFICACION')->insert(['COD_NOT' => sprintf('NOT%07d', (int) DB::table('NOTIFICACION')->max('id') + 1), 'FEC_NOT' => now(), 'TIP_NOT' => 'Incidencia de dosis', 'MSG_NOT' => mb_substr("Dosis omitida del paciente {$i->NOM_PAC} {$i->APE_PAC} (CI {$i->CI_PAC}): {$d['motivo']}", 0, 255), 'CAN_NOT' => 'Sistema', 'EST_NOT' => 'Enviada', 'REF_NOT' => $codigo, 'COD_USR' => $usuario->COD_USR]);
            $this->auditar('Omitir dosis', 'DOSIS', $dosis, $i->COD_PAC);
        });
        return back()->with('ok', 'Omisión registrada y aviso enviado al médico asignado.');
    }

    public function cerrarInsumo(Request $request, string $codIns): RedirectResponse
    {
        $d = $request->validate(['tipo' => 'required|in:Devolución,Descarte', 'detalle' => 'required|string|max:250']);
        DB::transaction(function () use ($codIns, $d) {
            $ins = DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->lockForUpdate()->first();
            abort_unless($ins && $ins->EST_INS === 'Verificado', 404);
            if (DB::table('ENFERMERIA_CIERRE_INSUMO')->where('COD_INS', $codIns)->exists() || DB::table('DOSIS')->where('COD_INS', $codIns)->where('EST_DOS', 'Programada')->exists()) {
                throw ValidationException::withMessages(['insumo' => 'El insumo ya fue cerrado o tiene dosis pendientes.']);
            }
            $cantidad = ((int) $ins->CAN_INS * 1000 - $this->ocupacion($codIns)) / 1000;
            if ($cantidad <= 0) { throw ValidationException::withMessages(['insumo' => 'No quedan unidades para devolver o descartar.']); }
            DB::table('ENFERMERIA_CIERRE_INSUMO')->insert(['COD_INS' => $codIns, 'COD_EMP' => $this->empleado(), 'FECHA' => now(), 'TIPO' => $d['tipo'], 'CANTIDAD' => $cantidad, 'DETALLE' => trim($d['detalle'])]);
            $this->auditar('Cerrar insumo', 'INSUMO_TERCERO', $codIns, $ins->COD_PAC);
        });
        return back()->with('ok', 'Destino del insumo registrado.');
    }
}
