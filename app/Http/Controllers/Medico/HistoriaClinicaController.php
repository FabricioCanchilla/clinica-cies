<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\MedicoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HistoriaClinicaController extends Controller
{
    public function __construct(private MedicoService $medico) {}

    public function index(Request $request): View
    {
        $d = $request->validate(['buscar' => ['nullable', 'string', 'max:100']]);
        $buscar = trim($d['buscar'] ?? '');
        $pacientes = $this->medico->pacientes()->when($buscar !== '', function ($q) use ($buscar) {
            $q->where(fn ($s) => $s->where('p.NOM_PAC', 'like', "%{$buscar}%")
                ->orWhere('p.APE_PAC', 'like', "%{$buscar}%")->orWhere('p.CI_PAC', $buscar)
                ->orWhere('p.COD_PAC', $buscar));
        })->orderBy('p.APE_PAC')->paginate(15)->withQueryString();

        return view('medico.historias', compact('pacientes', 'buscar'));
    }

    public function emergencia(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'paciente' => ['required', 'string', 'exists:PACIENTE,COD_PAC'],
            'motivo' => ['required', 'string', 'min:15', 'max:200'],
        ]);
        $this->medico->empleado();
        $this->medico->auditar('Acceso emergencia', 'PACIENTE', $d['paciente'], $d['paciente'], $d['motivo']);
        session(['medico_emergencia.'.$d['paciente'] => now()->addMinutes(30)->timestamp]);

        return redirect()->route('medico.historias.ver', $d['paciente']);
    }

    public function show(string $paciente): View
    {
        $p = $this->medico->paciente($paciente);
        $h = $this->medico->historia($paciente);
        $this->medico->auditar('Leer', 'HISTORIA_CLINICA', $h->COD_HIS, $paciente, 'Consulta de expediente');
        $consultas = DB::table('CONSULTA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->where('c.COD_HIS', $h->COD_HIS)->select('c.*', 'e.NOM_EMP', 'e.APE_EMP')->orderByDesc('c.FEC_CNS')->get();
        $codigos = $consultas->pluck('COD_CNS');
        $notas = DB::table('NOTA_EVOLUCION as n')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'n.COD_EMP')
            ->where('n.COD_HIS', $h->COD_HIS)->select('n.*', 'e.NOM_EMP', 'e.APE_EMP')->orderByDesc('n.FEC_NEV')->get();

        return view('medico.historia', [
            'p' => $p, 'h' => $h, 'consultas' => $consultas, 'notas' => $notas,
            'alergias' => DB::table('ALERGIA')->where('COD_HIS', $h->COD_HIS)->get(),
            'antecedentes' => DB::table('ANTECEDENTE')->where('COD_HIS', $h->COD_HIS)->get(),
            'signos' => DB::table('SIGNO_VITAL')->where('COD_PAC', $paciente)->orderByDesc('FEC_SIV')->limit(20)->get(),
            'adendas' => DB::table('ADENDA')->whereIn('COD_CNS', $codigos)->orderBy('FEC_ADE')->get()->groupBy('COD_CNS'),
            'adendasNotas' => DB::table('ADENDA_EVOLUCION')->whereIn('COD_NEV', $notas->pluck('COD_NEV'))->orderBy('FECHA')->get()->groupBy('COD_NEV'),
        ]);
    }

    public function guardar(Request $request, string $paciente): RedirectResponse
    {
        $d = $request->validate([
            'tipo' => ['required', Rule::in(['alergia', 'antecedente', 'nota', 'administrativa'])],
            'texto' => ['required', 'string', 'max:500'],
            'severidad' => ['required_if:tipo,alergia', Rule::in(['Leve', 'Moderada', 'Grave'])],
            'clase' => ['required_if:tipo,antecedente', Rule::in(['Personal', 'Familiar', 'Quirurgico'])],
        ]);
        $h = $this->medico->historia($paciente);
        abort_unless($h->EST_HIS === 'Activa', 422, 'El expediente está inactivo.');
        $limites = ['alergia' => 60, 'antecedente' => 200, 'nota' => 500, 'administrativa' => 255];
        $request->validate(['texto' => ['max:'.$limites[$d['tipo']]]]);
        DB::transaction(function () use ($d, $h, $paciente) {
            DB::table('PACIENTE')->where('COD_PAC', $paciente)->lockForUpdate()->first();
            if ($d['tipo'] === 'administrativa') {
                DB::table('HISTORIA_CLINICA')->where('COD_HIS', $h->COD_HIS)->update(['NOTADM_HIS' => $d['texto']]);
                $this->medico->auditar('Actualizar', 'HISTORIA_CLINICA', $h->COD_HIS, $paciente, 'Actualización de nota administrativa');

                return;
            }
            [$tabla, $campo, $prefijo, $digitos] = match ($d['tipo']) {
                'alergia' => ['ALERGIA', 'COD_ALE', 'ALE', 5],
                'antecedente' => ['ANTECEDENTE', 'COD_ANT', 'ANT', 5],
                'nota' => ['NOTA_EVOLUCION', 'COD_NEV', 'NEV', 7],
            };
            $codigo = $this->medico->codigo($tabla, $campo, $prefijo, $digitos);
            $fila = [$campo => $codigo, 'COD_HIS' => $h->COD_HIS];
            $fila += match ($d['tipo']) {
                'alergia' => ['DES_ALE' => $d['texto'], 'SEV_ALE' => $d['severidad']],
                'antecedente' => ['DES_ANT' => $d['texto'], 'TIP_ANT' => $d['clase']],
                'nota' => ['TXT_NEV' => $d['texto'], 'FEC_NEV' => now(), 'COD_EMP' => $this->medico->empleado()],
            };
            DB::table($tabla)->insert($fila);
            $this->medico->auditar('Crear', $tabla, $codigo, $paciente, 'Registro clínico: '.$d['tipo']);
        });

        return back()->with('ok', 'Registro guardado y auditado.');
    }

    public function adenda(Request $request, string $paciente): RedirectResponse
    {
        $d = $request->validate([
            'tipo' => ['required', Rule::in(['consulta', 'nota'])],
            'codigo' => ['required', 'string', 'max:10'], 'texto' => ['required', 'string', 'max:500'],
        ]);
        $h = $this->medico->historia($paciente);
        DB::transaction(function () use ($d, $h, $paciente) {
            $consulta = $d['tipo'] === 'consulta';
            $tabla = $consulta ? 'CONSULTA' : 'NOTA_EVOLUCION';
            $campo = $consulta ? 'COD_CNS' : 'COD_NEV';
            $original = DB::table($tabla)->where($campo, $d['codigo'])->where('COD_HIS', $h->COD_HIS)->first();
            abort_unless($original, 404);
            $datos = [$campo => $d['codigo'], 'COD_EMP' => $this->medico->empleado()];
            if ($consulta) {
                $datos += ['COD_ADE' => $this->medico->codigo('ADENDA', 'COD_ADE', 'ADE'), 'FEC_ADE' => now(), 'TXT_ADE' => $d['texto']];
            } else {
                $datos += ['FECHA' => now(), 'TEXTO' => $d['texto']];
            }
            DB::table($consulta ? 'ADENDA' : 'ADENDA_EVOLUCION')->insert($datos);
            $this->medico->auditar('Adenda', $tabla, $d['codigo'], $paciente, 'Adenda añadida; original conservado');
        });

        return back()->with('ok', 'Adenda guardada. El registro original se conserva.');
    }
}
