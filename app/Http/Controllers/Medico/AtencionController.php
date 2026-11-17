<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Atención de una cita desde la agenda (RF021 → RF020):
 * registra la CONSULTA (diagnóstico y plan) y la NOTA_EVOLUCION en la historia clínica del paciente
 * y pasa la cita de «Confirmada» a «Atendida» (RN25). Las notas no se editan ni se eliminan.
 */
class AtencionController extends Controller
{
    private function citaDelMedico(string $codCit): object
    {
        $c = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->where('c.COD_CIT', $codCit)
            ->where('c.COD_EMP', auth()->user()->COD_EMP)
            ->first(['c.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'p.FECNAC_PAC', 'p.SEX_PAC', 's.NOM_SRV']);
        abort_if(!$c, 404);
        return $c;
    }

    public function form(string $codCit)
    {
        $c = $this->citaDelMedico($codCit);
        if ($c->EST_CIT !== 'Confirmada') {
            return redirect()->route('medico.dashboard')->withErrors(['cita' => "La cita está {$c->EST_CIT}: solo se atienden citas confirmadas."]);
        }

        $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $c->COD_PAC)->first();
        $anteriores = $his
            ? DB::table('CONSULTA as co')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
                ->where('co.COD_HIS', $his->COD_HIS)->orderByDesc('co.FEC_CNS')->limit(10)
                ->get(['co.FEC_CNS', 'co.DIA_CNS', 'co.PLA_CNS', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP'])
            : collect();
        $notas = $his
            ? DB::table('NOTA_EVOLUCION')->where('COD_HIS', $his->COD_HIS)->orderByDesc('FEC_NEV')->limit(10)->get()
            : collect();

        Auditoria::registrar('Ver historia', 'Media', 'DOM03', 'HISTORIA_CLINICA', $his->COD_HIS ?? $c->COD_PAC,
            "Consulta del expediente del paciente {$c->COD_PAC} para atender la cita {$codCit}", codPac: $c->COD_PAC);

        return view('medico.atender', ['cita' => $c, 'historia' => $his, 'anteriores' => $anteriores, 'notas' => $notas]);
    }

    public function guardar(Request $request, string $codCit)
    {
        $datos = $request->validate([
            'diagnostico' => 'required|string|max:200',
            'plan'        => 'required|string|max:255',
            'evolucion'   => 'required|string|max:500',
        ], [], ['diagnostico' => 'diagnóstico', 'plan' => 'plan de tratamiento', 'evolucion' => 'nota de evolución']);

        $codEmp = auth()->user()->COD_EMP;

        try {
        DB::transaction(function () use ($codCit, $codEmp, $datos) {
            $c = DB::table('CITA')->where('COD_CIT', $codCit)->where('COD_EMP', $codEmp)->lockForUpdate()->first();
            abort_if(!$c, 404);
            if ($c->EST_CIT !== 'Confirmada') {
                throw new \DomainException("La cita está {$c->EST_CIT}: solo se atienden citas confirmadas.");
            }

            // La historia se abre en la primera atención del paciente
            $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $c->COD_PAC)->lockForUpdate()->first();
            if (!$his) {
                $n = (int) DB::table('HISTORIA_CLINICA')->max('id') + 1;
                DB::table('HISTORIA_CLINICA')->insert([
                    'COD_HIS'    => sprintf('HIS%06d', $n),
                    'NRO_HIS'    => sprintf('HC-%06d', $n),
                    'FECAPE_HIS' => now()->toDateString(),
                    'EST_HIS'    => 'Activa',
                    'NOTADM_HIS' => null,
                    'COD_PAC'    => $c->COD_PAC,
                ]);
                $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $c->COD_PAC)->first();
                Auditoria::registrar('Abrir historia', 'Media', 'DOM03', 'HISTORIA_CLINICA', $his->COD_HIS,
                    "Apertura de la historia {$his->NRO_HIS}", codPac: $c->COD_PAC);
            }

            $codCns = sprintf('CNS%07d', (int) DB::table('CONSULTA')->max('id') + 1);
            DB::table('CONSULTA')->insert([
                'COD_CNS' => $codCns,
                'FEC_CNS' => now(),
                'DIA_CNS' => trim($datos['diagnostico']),
                'PLA_CNS' => trim($datos['plan']),
                'COD_CIT' => $codCit,
                'COD_HIS' => $his->COD_HIS,
                'COD_EMP' => $codEmp,
            ]);

            DB::table('NOTA_EVOLUCION')->insert([
                'COD_NEV' => sprintf('NEV%07d', (int) DB::table('NOTA_EVOLUCION')->max('id') + 1),
                'FEC_NEV' => now(),
                'TXT_NEV' => trim($datos['evolucion']),
                'COD_HIS' => $his->COD_HIS,
                'COD_EMP' => $codEmp,
            ]);

            DB::table('CITA')->where('COD_CIT', $codCit)->update(['EST_CIT' => 'Atendida']);

            Auditoria::registrar('Atender cita', 'Media', 'DOM03', 'CONSULTA', $codCns,
                "Consulta {$codCns} de la cita {$codCit}: diagnóstico y nota de evolución registrados", codPac: $c->COD_PAC);
        });
        } catch (\DomainException $e) {
            return redirect()->route('medico.dashboard')->withErrors(['cita' => $e->getMessage()]);
        }

        return redirect()->route('medico.dashboard')->with('ok', "Cita {$codCit} atendida. La consulta quedó en la historia clínica.");
    }
}
