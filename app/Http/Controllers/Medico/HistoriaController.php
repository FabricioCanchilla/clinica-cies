<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\HistoriaClinicaService;
use App\Support\Firma;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RF017 historia clínica completa · RF020/RN41 adendas · RF023 derivaciones e interconsultas ·
 * RF024 consentimiento informado con firma · RF022 buscador CIE-10 · RF047 acceso de emergencia auditado.
 */
class HistoriaController extends Controller
{
    public function __construct(private HistoriaClinicaService $historias)
    {
    }

    private function codEmp(): string
    {
        $cod = auth()->user()->COD_EMP;
        abort_if(!$cod, 403, 'Tu usuario no está vinculado a un empleado.');
        return $cod;
    }

    /** El médico ve la historia si es tratante o si abrió un acceso de emergencia (queda auditado). */
    private function puedeVer(string $codPac): bool
    {
        return $this->historias->medicoTratante($this->codEmp(), $codPac)
            || in_array($codPac, session('acceso_emergencia', []), true);
    }

    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $pacientes = collect();
        if ($q !== '') {
            $codEmp = $this->codEmp();
            $pacientes = DB::table('PACIENTE as p')
                ->where(fn ($w) => $w->where('p.CI_PAC', $q)->orWhere('p.COD_PAC', $q)
                    ->orWhere(DB::raw("CONCAT(p.NOM_PAC, ' ', p.APE_PAC)"), 'like', "%{$q}%"))
                ->orderBy('p.APE_PAC')->limit(25)
                ->get(['p.COD_PAC', 'p.CI_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.FECNAC_PAC'])
                ->each(fn ($p) => $p->TRATANTE = $this->historias->medicoTratante($codEmp, $p->COD_PAC));
        }
        return view('medico.historia-buscar', ['q' => $q, 'pacientes' => $pacientes]);
    }

    public function show(string $codPac)
    {
        if (!$this->puedeVer($codPac)) {
            $pac = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();
            abort_if(!$pac, 404);
            return view('medico.historia-emergencia', ['paciente' => $pac]);
        }

        try {
            $exp = $this->historias->expediente($codPac);
        } catch (DomainException $e) {
            abort(404, $e->getMessage());
        }

        Auditoria::registrar('Ver historia', 'Media', 'DOM03', 'HISTORIA_CLINICA', $exp['historia']->COD_HIS ?? $codPac,
            "Lectura del expediente completo del paciente {$codPac}", codPac: $codPac);

        return view('medico.historia', $exp + [
            'especialidades' => DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get(),
            'emergencia'     => !$this->historias->medicoTratante($this->codEmp(), $codPac),
        ]);
    }

    /** RF047: acceso de emergencia a una historia ajena, con motivo obligatorio y criticidad Alta. */
    public function emergencia(Request $request, string $codPac)
    {
        $d = $request->validate(['motivo' => 'required|string|min:10|max:200']);
        abort_if(!DB::table('PACIENTE')->where('COD_PAC', $codPac)->exists(), 404);

        $lista = session('acceso_emergencia', []);
        $lista[] = $codPac;
        session(['acceso_emergencia' => array_values(array_unique($lista))]);

        Auditoria::registrar('Acceso emergencia', 'Alta', 'DOM03', 'HISTORIA_CLINICA', $codPac,
            'Acceso de emergencia a la historia: ' . trim($d['motivo']), codPac: $codPac);

        return redirect()->route('medico.historia', $codPac)->with('aviso', 'Acceso de emergencia registrado en auditoría.');
    }

    public function antecedente(Request $request, string $codPac)
    {
        abort_if(!$this->puedeVer($codPac), 403);
        $d = $request->validate([
            'tipo'        => 'required|in:Personal,Familiar,Quirurgico',
            'descripcion' => 'required|string|max:200',
        ]);
        $this->historias->agregarAntecedente($codPac, $d['tipo'], trim($d['descripcion']));
        return back()->with('ok', 'Antecedente registrado.');
    }

    public function alergia(Request $request, string $codPac)
    {
        abort_if(!$this->puedeVer($codPac), 403);
        $d = $request->validate([
            'descripcion' => 'required|string|max:60',
            'severidad'   => 'required|in:Leve,Moderada,Grave',
        ]);
        $this->historias->agregarAlergia($codPac, trim($d['descripcion']), $d['severidad']);
        return back()->with('ok', 'Alergia registrada. Se alertará al prescribir (RN42).');
    }

    public function adenda(Request $request, string $codCns)
    {
        $codPac = DB::table('CONSULTA as c')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('c.COD_CNS', $codCns)->value('h.COD_PAC');
        abort_if(!$codPac || !$this->puedeVer($codPac), 403);
        $d = $request->validate(['texto' => 'required|string|min:5|max:500'], [], ['texto' => 'texto de la adenda']);
        try {
            $this->historias->agregarAdenda($codCns, $this->codEmp(), trim($d['texto']));
        } catch (DomainException $e) {
            return back()->withErrors(['adenda' => $e->getMessage()]);
        }
        return back()->with('ok', 'Adenda registrada. La consulta original se conserva sin cambios (RN41).');
    }

    public function derivar(Request $request, string $codPac)
    {
        abort_if(!$this->puedeVer($codPac), 403);
        $d = $request->validate([
            'especialidad' => 'required|exists:ESPECIALIDAD,COD_ESP',
            'tipo'         => 'required|in:Derivación,Interconsulta',
            'prioridad'    => 'required|in:Normal,Urgente',
            'motivo'       => 'required|string|min:10|max:300',
        ]);
        $cod = $this->historias->derivar($codPac, $this->codEmp(), $d['especialidad'], $d['tipo'], $d['prioridad'], trim($d['motivo']));
        return back()->with('ok', "{$d['tipo']} {$cod} enviada a la especialidad.");
    }

    // ---------- RF023: interconsultas recibidas ----------

    public function interconsultas()
    {
        $esp = DB::table('EMPLEADO')->where('COD_EMP', $this->codEmp())->value('COD_ESP');
        $lista = DB::table('DERIVACION as d')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'd.COD_PAC')
            ->join('EMPLEADO as o', 'o.COD_EMP', '=', 'd.ORI_EMP')
            ->leftJoin('ESPECIALIDAD as oe', 'oe.COD_ESP', '=', 'o.COD_ESP')
            ->where('d.COD_ESP', $esp)
            ->orderByRaw("FIELD(d.EST_DER, 'Pendiente') DESC")
            ->orderByRaw("FIELD(d.PRI_DER, 'Urgente') DESC")
            ->orderByDesc('d.FEC_DER')->limit(80)
            ->get(['d.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'o.TIT_EMP', 'o.NOM_EMP', 'o.APE_EMP', 'oe.DES_ESP as ESP_ORI']);
        $enviadas = DB::table('DERIVACION as d')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'd.COD_PAC')
            ->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'd.COD_ESP')
            ->where('d.ORI_EMP', $this->codEmp())->orderByDesc('d.FEC_DER')->limit(40)
            ->get(['d.*', 'p.NOM_PAC', 'p.APE_PAC', 'es.DES_ESP']);

        return view('medico.interconsultas', ['recibidas' => $lista, 'enviadas' => $enviadas]);
    }

    public function responder(Request $request, string $codDer)
    {
        $d = $request->validate([
            'estado'    => 'required|in:Respondida,Rechazada',
            'respuesta' => 'required|string|min:5|max:500',
        ]);
        try {
            $this->historias->responderDerivacion($codDer, $this->codEmp(), $d['estado'], trim($d['respuesta']));
        } catch (DomainException $e) {
            return back()->withErrors(['derivacion' => $e->getMessage()]);
        }
        return back()->with('ok', "Derivación {$codDer} {$d['estado']}.");
    }

    // ---------- RF024: consentimiento informado ----------

    public function consentimientoForm(string $codPac)
    {
        abort_if(!$this->puedeVer($codPac), 403);
        $pac = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();
        $citas = DB::table('CITA')->where('COD_PAC', $codPac)->where('COD_EMP', $this->codEmp())
            ->orderByDesc('FEC_CIT')->limit(10)->get(['COD_CIT', 'FEC_CIT', 'HOR_CIT', 'MOT_CIT', 'EST_CIT']);
        return view('medico.consentimiento', ['paciente' => $pac, 'citas' => $citas]);
    }

    /** RN16: el consentimiento requiere solo la firma del paciente (o su representante). */
    public function consentimientoGuardar(Request $request, string $codPac)
    {
        abort_if(!$this->puedeVer($codPac), 403);
        $d = $request->validate([
            'procedimiento' => 'required|string|max:150',
            'riesgos'       => 'required|string|min:20|max:500',
            'firmante'      => 'required|string|max:100',
            'cita'          => 'nullable|exists:CITA,COD_CIT',
            'acepta'        => 'accepted',
            'firma'         => 'required|string',
        ], ['acepta.accepted' => 'El paciente debe declarar que recibió la información y acepta el procedimiento.'],
           ['riesgos' => 'riesgos y alternativas']);

        try {
            $png = Firma::decodificar($d['firma'], 'el paciente');
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['firma' => $e->getMessage()]);
        }

        $cod = DB::transaction(function () use ($d, $codPac, $png) {
            $cod = sprintf('CNT%07d', (int) DB::table('CONSENTIMIENTO')->lockForUpdate()->max('id') + 1);
            $ruta = Firma::guardar($png, "firmas/consentimientos/{$cod}.png");
            DB::table('CONSENTIMIENTO')->insert([
                'COD_CNT' => $cod, 'FEC_CNT' => now(), 'PRO_CNT' => trim($d['procedimiento']),
                'RIE_CNT' => trim($d['riesgos']), 'FIR_CNT' => $ruta, 'FIRNOM_CNT' => trim($d['firmante']),
                'IP_CNT' => request()->ip() ?? '0.0.0.0', 'COD_PAC' => $codPac, 'COD_EMP' => $this->codEmp(),
                'COD_CIT' => $d['cita'] ?? null,
            ]);
            Auditoria::registrar('Consentimiento', 'Alta', 'DOM03', 'CONSENTIMIENTO', $cod,
                "Consentimiento firmado por {$d['firmante']} para: {$d['procedimiento']}", codPac: $codPac);
            return $cod;
        });

        return redirect()->route('medico.historia', $codPac)->with('ok', "Consentimiento {$cod} firmado y guardado.");
    }

    public function consentimientoFirma(string $codCnt)
    {
        $c = DB::table('CONSENTIMIENTO')->where('COD_CNT', $codCnt)->first();
        abort_if(!$c || !$this->puedeVer($c->COD_PAC), 404);
        $png = Firma::leer($c->FIR_CNT);
        abort_if(!$png, 404);
        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    // ---------- RF022: buscador del catálogo CIE-10 ----------

    public function cie10(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        return DB::table('CIE10')
            ->when($q !== '', fn ($w) => $w->where('COD_CIE', 'like', "{$q}%")->orWhere('DES_CIE', 'like', "%{$q}%"))
            ->orderBy('COD_CIE')->limit(20)->get(['COD_CIE', 'DES_CIE', 'CAP_CIE']);
    }
}
