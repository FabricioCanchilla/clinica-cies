<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Portal público (RF001, RF002, RF004): páginas institucionales sin iniciar sesión.
 * Los datos de la institución vienen de config/clinica.php (tomados de cies.org.bo);
 * especialidades, médicos, horarios y tarifas vienen de la base del sistema.
 */
class PublicoController extends Controller
{
    private const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /** Cifras del sistema para el portal (se recalculan cada 10 minutos). */
    public static function cifras(): array
    {
        return Cache::remember('publico.cifras', 600, function () {
            $anio = Carbon::today()->subYear();
            return [
                'especialidades' => DB::table('ESPECIALIDAD')->count(),
                'medicos'        => DB::table('EMPLEADO')->whereNotNull('COD_ESP')->count(),
                'atenciones'     => DB::table('CITA')->where('EST_CIT', 'Atendida')->where('FEC_CIT', '>=', $anio->toDateString())->count(),
                'pacientes'      => DB::table('CITA')->where('EST_CIT', 'Atendida')->where('FEC_CIT', '>=', $anio->toDateString())->distinct()->count('COD_PAC'),
                'examenes'       => DB::table('ORDEN_EXAMEN')->where('FEC_ORD', '>=', $anio->toDateString())->count(),
                'camas'          => DB::table('CAMA')->count(),
                'consultorios'   => DB::table('CONSULTORIO')->count(),
            ];
        });
    }

    /** Especialidades con sus médicos, días de atención, consultorio y precio de la consulta. */
    public static function especialidades()
    {
        $precios = DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
            ->where('c.DES_CLA', 'like', 'Consulta%')->pluck('s.PRE_SRV', 's.NOM_SRV');
        $horarios = DB::table('HORARIO_MEDICO as h')->join('CONSULTORIO as k', 'k.COD_CON', '=', 'h.COD_CON')
            ->get(['h.COD_EMP', 'h.DIA_HOR', 'h.HORINI_HOR', 'h.HORFIN_HOR', 'k.NOM_CON', 'k.UBI_CON'])->groupBy('COD_EMP');
        $medicos = DB::table('EMPLEADO')->whereNotNull('COD_ESP')->orderBy('APE_EMP')
            ->get(['COD_EMP', 'TIT_EMP', 'NOM_EMP', 'APE_EMP', 'SEX_EMP', 'COD_ESP'])->groupBy('COD_ESP');

        return DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get()->map(function ($e) use ($precios, $horarios, $medicos) {
            $e->PRECIO = $precios['Consulta de ' . $e->DES_ESP] ?? ($e->DES_ESP === 'Medicina General' ? ($precios['Consulta general'] ?? null) : null);
            $e->MEDICOS = ($medicos[$e->COD_ESP] ?? collect())->map(function ($m) use ($horarios) {
                $h = ($horarios[$m->COD_EMP] ?? collect())->sortBy(fn ($x) => array_search($x->DIA_HOR, self::DIAS));
                $m->DIAS = $h->pluck('DIA_HOR')->unique()->values()->all();
                $m->TURNOS = $h->groupBy('DIA_HOR')->map(fn ($g) => $g->map(fn ($x) => substr($x->HORINI_HOR, 0, 5) . '–' . substr($x->HORFIN_HOR, 0, 5))->implode(', '))->all();
                $m->CONSULTORIO = $h->first() ? $h->first()->NOM_CON . ' · ' . $h->first()->UBI_CON : null;
                return $m;
            })->values();
            return $e;
        });
    }

    public function nosotros()
    {
        return view('publico.nosotros', ['cifras' => self::cifras()]);
    }

    public function servicios()
    {
        $examenes = DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
            ->where('c.DES_CLA', 'not like', 'Consulta%')->orderBy('c.DES_CLA')->orderBy('s.NOM_SRV')
            ->get(['s.NOM_SRV', 's.PRE_SRV', 'c.DES_CLA'])->groupBy('DES_CLA');

        return view('publico.servicios', [
            'especialidades' => self::especialidades(),
            'examenes'       => $examenes,
            'anticipo'       => (int) config('cies.anticipo_porcentaje'),
            'cifras'         => self::cifras(),
        ]);
    }

    public function investigacion()
    {
        // Perfil de atención agregado y anónimo de los últimos 12 meses (sin datos personales)
        $desde = Carbon::today()->subYear();
        $diagnosticos = Cache::remember('publico.dx', 600, fn () => DB::table('CONSULTA as c')->join('CIE10 as x', 'x.COD_CIE', '=', 'c.COD_CIE')
            ->where('c.FEC_CNS', '>=', $desde)->groupBy('x.COD_CIE', 'x.DES_CIE')->orderByDesc(DB::raw('COUNT(*)'))->limit(10)
            ->get(['x.COD_CIE', 'x.DES_CIE', DB::raw('COUNT(*) as N')]));
        $porEspecialidad = Cache::remember('publico.esp', 600, fn () => DB::table('CITA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')->where('c.EST_CIT', 'Atendida')
            ->where('c.FEC_CIT', '>=', $desde->toDateString())->groupBy('es.DES_ESP')->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['es.DES_ESP', DB::raw('COUNT(*) as N')]));

        return view('publico.investigacion', [
            'diagnosticos'    => $diagnosticos,
            'porEspecialidad' => $porEspecialidad,
            'cifras'          => self::cifras(),
        ]);
    }

    public function ubicacion()
    {
        return view('publico.ubicacion');
    }

    public function contacto()
    {
        return view('publico.contacto');
    }

    /** «Agendar cita»: el paciente va al formulario; sin sesión, primero inicia sesión o crea su cuenta. */
    public function agendar()
    {
        $u = auth()->user();
        if ($u && $u->COD_ROL === 'ROL006') {
            return redirect()->route('paciente.citas.crear');
        }
        if ($u) {
            return redirect()->route('panel')->with('aviso', 'Las citas en línea se agendan desde una cuenta de paciente. Recepción puede agendar desde la ficha del paciente.');
        }
        session()->put('url.intended', route('paciente.citas.crear'));
        return redirect()->to(route('login') . '?acceso=agendar');
    }
}
