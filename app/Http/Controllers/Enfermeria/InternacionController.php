<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Services\EnfermeriaService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** RF030, RF033, RF034 y RF035: atención de enfermería al paciente internado. */
class InternacionController extends Controller
{
    public function __construct(private EnfermeriaService $enf)
    {
    }

    public function index()
    {
        return view('enfermeria.internados', [
            'internados' => $this->enf->internados(),
            'alertas'    => $this->enf->alertasDosis(),
            'turno'      => EnfermeriaService::turno(),
        ]);
    }

    public function show(string $codInt)
    {
        try {
            $int = $this->enf->internacion($codInt);
        } catch (DomainException $e) {
            abort(404, $e->getMessage());
        }
        return view('enfermeria.internacion', $this->enf->resumen($int) + [
            'int'       => $int,
            'vias'      => DB::table('VIA_ADMIN')->orderBy('DES_VIA')->get(),
            'ingresos'  => EnfermeriaService::CONCEPTOS_INGRESO,
            'egresos'   => EnfermeriaService::CONCEPTOS_EGRESO,
            'borrador'  => $this->enf->borradorTurno($codInt),
            'turno'     => EnfermeriaService::turno(),
            'ventana'   => (int) config('cies.ventana_dosis_min', 30),
        ]);
    }

    public function signos(Request $request, string $codInt)
    {
        $d = $request->validate([
            'sistolica'   => 'required|integer|min:40|max:300',
            'diastolica'  => 'required|integer|min:20|max:200',
            'pulso'       => 'required|integer|min:1|max:250',
            'temperatura' => 'required|numeric',
        ]);
        try {
            $int = $this->enf->internacion($codInt);
            $this->enf->registrarSignos($int->COD_PAC, $d, auth()->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['signos' => $e->getMessage()]);
        }
        return back()->with('ok', 'Signos vitales registrados.');
    }

    public function programar(Request $request, string $codInt)
    {
        $d = $request->validate([
            'indicacion' => 'required|string',
            'inicio'     => 'required|date',
        ]);
        try {
            $n = $this->enf->programarDosis($codInt, $d['indicacion'], Carbon::parse($d['inicio']), auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['dosis' => $e->getMessage()]);
        }
        return back()->with('ok', "{$n} dosis programadas.");
    }

    public function administrar(Request $request, string $codDos)
    {
        $d = $request->validate([
            'paciente'    => 'required|string|max:20',
            'medicamento' => 'required|string|max:20',
            'dosis'       => 'required|string|max:20',
            'via'         => 'required|string|max:6',
        ], [], ['paciente' => 'pulsera del paciente', 'medicamento' => 'código del medicamento']);
        try {
            $r = $this->enf->administrar($codDos, $d, auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['dosis' => $e->getMessage()]);
        }
        if (!$r['ok']) {
            return back()->withErrors(['dosis' => 'No se administró: no se cumplen los 5 correctos (RN37). Falla: ' . implode(', ', $r['fallas']) . '.'])
                ->with('checks_' . $codDos, $r['checks']);
        }
        return back()->with('ok', 'Dosis administrada: los 5 correctos verificados.');
    }

    public function omitir(Request $request, string $codDos)
    {
        $d = $request->validate(['motivo' => 'required|string|min:5|max:200']);
        try {
            $this->enf->omitir($codDos, trim($d['motivo']), auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['dosis' => $e->getMessage()]);
        }
        return back()->with('ok', 'Dosis omitida: se registró la incidencia y se notificó al médico tratante.');
    }

    public function nota(Request $request, string $codInt)
    {
        $d = $request->validate([
            'tipo'  => 'required|in:Nota,Cambio de turno',
            'texto' => 'required|string|min:5|max:1000',
        ]);
        try {
            $this->enf->nota($codInt, $d['tipo'], trim($d['texto']), auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['nota' => $e->getMessage()]);
        }
        return back()->with('ok', $d['tipo'] === 'Nota' ? 'Nota de enfermería registrada.' : 'Resumen de cambio de turno registrado.');
    }

    public function balance(Request $request, string $codInt)
    {
        $d = $request->validate([
            'tipo'     => 'required|in:Ingreso,Egreso',
            'concepto' => 'required|string|max:20',
            'volumen'  => 'required|integer|min:1|max:5000',
        ]);
        try {
            $this->enf->registrarBalance($codInt, $d['tipo'], $d['concepto'], (int) $d['volumen'], auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['balance' => $e->getMessage()]);
        }
        return back()->with('ok', "{$d['tipo']} de {$d['volumen']} mL registrado.");
    }
}
