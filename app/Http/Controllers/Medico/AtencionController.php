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

        $alergias = $his ? DB::table('ALERGIA')->where('COD_HIS', $his->COD_HIS)->get() : collect();

        return view('medico.atender', [
            'cita'         => $c,
            'historia'     => $his,
            'anteriores'   => $anteriores,
            'notas'        => $notas,
            'alergias'     => $alergias,
            // RF018: catálogo de medicamentos y vías; RF019: exámenes (servicios de Laboratorio)
            'medicamentos' => DB::table('MEDICAMENTO')->orderBy('DES_MED')->get(),
            'vias'         => DB::table('VIA_ADMIN')->orderBy('DES_VIA')->get(),
            'examenes'     => DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
                                ->where('c.DES_CLA', '!=', 'Consulta')->orderBy('s.NOM_SRV')->get(['s.COD_SRV', 's.NOM_SRV', 'c.DES_CLA']),
        ]);
    }

    public function guardar(Request $request, string $codCit)
    {
        $datos = $request->validate([
            'diagnostico'          => 'required|string|max:200',
            'plan'                 => 'required|string|max:255',
            'evolucion'            => 'required|string|max:500',
            // Alergia nueva detectada en la consulta (opcional)
            'alergia_des'          => 'nullable|string|max:60',
            'alergia_sev'          => 'nullable|required_with:alergia_des|in:Leve,Moderada,Grave',
            // RF018: ítems de receta (opcionales)
            'items'                => 'nullable|array|max:10',
            'items.*.med'          => 'nullable|exists:MEDICAMENTO,COD_MED',
            'items.*.dosis'        => 'required_with:items.*.med|nullable|string|max:20',
            'items.*.via'          => 'required_with:items.*.med|nullable|exists:VIA_ADMIN,COD_VIA',
            'items.*.frecuencia'   => 'required_with:items.*.med|nullable|integer|min:1|max:72',
            'items.*.duracion'     => 'required_with:items.*.med|nullable|integer|min:1|max:365',
            'obs_receta'           => 'nullable|string|max:200',
            'justificacion'        => 'nullable|string|max:200',
            // RF019: órdenes de examen (opcionales)
            'examenes'             => 'nullable|array|max:10',
            'examenes.*'           => 'exists:SERVICIO,COD_SRV',
            'prioridad'            => 'nullable|in:Normal,Urgente',
            'indicaciones'         => 'nullable|required_with:examenes|string|max:200',
        ], [
            'items.*.dosis.required_with'      => 'Indica la dosis de cada medicamento.',
            'items.*.via.required_with'        => 'Indica la vía de cada medicamento.',
            'items.*.frecuencia.required_with' => 'Indica la frecuencia (horas) de cada medicamento.',
            'items.*.duracion.required_with'   => 'Indica la duración (días) de cada medicamento.',
            'indicaciones.required_with'       => 'Escribe las indicaciones clínicas de la orden de examen.',
        ], ['diagnostico' => 'diagnóstico', 'plan' => 'plan de tratamiento', 'evolucion' => 'nota de evolución']);

        $codEmp = auth()->user()->COD_EMP;
        $items = collect($datos['items'] ?? [])->filter(fn ($i) => !empty($i['med']))->values();

        // RN42: si un medicamento coincide con una alergia del paciente, el médico debe justificar
        $codPac = DB::table('CITA')->where('COD_CIT', $codCit)->value('COD_PAC');
        $alergias = DB::table('ALERGIA as a')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'a.COD_HIS')
            ->where('h.COD_PAC', $codPac)->pluck('a.DES_ALE')->all();
        if (!empty($datos['alergia_des'])) {
            $alergias[] = $datos['alergia_des'];
        }
        $conflictos = $this->conflictosAlergia($items->pluck('med')->all(), $alergias);
        if ($conflictos && empty(trim($datos['justificacion'] ?? ''))) {
            return back()->withInput()->with('alerta_alergia', $conflictos)
                ->withErrors(['justificacion' => 'Alerta de alergia (RN42): ' . implode('; ', $conflictos) . '. Para prescribirlo escribe una justificación.']);
        }

        try {
        DB::transaction(function () use ($codCit, $codEmp, $datos, $items, $conflictos) {
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

            // Alergia nueva registrada en la historia (RF017)
            if (!empty($datos['alergia_des'])) {
                $codAle = sprintf('ALE%05d', (int) DB::table('ALERGIA')->max('id') + 1);
                DB::table('ALERGIA')->insert([
                    'COD_ALE' => $codAle,
                    'DES_ALE' => trim($datos['alergia_des']),
                    'SEV_ALE' => $datos['alergia_sev'],
                    'COD_HIS' => $his->COD_HIS,
                ]);
                Auditoria::registrar('Registrar alergia', 'Media', 'DOM03', 'ALERGIA', $codAle,
                    "Alergia «{$datos['alergia_des']}» ({$datos['alergia_sev']}) en la historia {$his->COD_HIS}", codPac: $c->COD_PAC);
            }

            // RF018: receta
            if ($items->isNotEmpty()) {
                $codRec = sprintf('REC%07d', (int) DB::table('RECETA')->max('id') + 1);
                DB::table('RECETA')->insert([
                    'COD_REC' => $codRec,
                    'FEC_REC' => now()->toDateString(),
                    'EST_REC' => 'Activa',
                    'OBS_REC' => !empty($datos['obs_receta']) ? trim($datos['obs_receta']) : null,
                    'COD_CNS' => $codCns,
                ]);
                foreach ($items as $i) {
                    DB::table('DETALLE_RECETA')->insert([
                        'COD_DRE'    => sprintf('DRE%07d', (int) DB::table('DETALLE_RECETA')->max('id') + 1),
                        'DOS_DRE'    => trim($i['dosis']),
                        'FRE_DRE'    => (int) $i['frecuencia'],
                        'DUR_DRE'    => (int) $i['duracion'],
                        'CANENT_DRE' => 0,
                        'COD_REC'    => $codRec,
                        'COD_MED'    => $i['med'],
                        'COD_VIA'    => $i['via'],
                    ]);
                }
                Auditoria::registrar('Emitir receta', 'Media', 'DOM03', 'RECETA', $codRec,
                    "Receta {$codRec} con {$items->count()} medicamento(s) en la consulta {$codCns}", codPac: $c->COD_PAC);

                if ($conflictos) {
                    Auditoria::registrar('Alerta alergia', 'Alta', 'DOM03', 'RECETA', $codRec,
                        mb_substr('Prescripción confirmada pese a alergia (' . implode('; ', $conflictos) . '). Justificación: ' . trim($datos['justificacion']), 0, 255),
                        codPac: $c->COD_PAC);
                }
            }

            // RF019: órdenes de examen
            foreach ($datos['examenes'] ?? [] as $codSrv) {
                $codOrd = sprintf('ORD%07d', (int) DB::table('ORDEN_EXAMEN')->max('id') + 1);
                DB::table('ORDEN_EXAMEN')->insert([
                    'COD_ORD' => $codOrd,
                    'FEC_ORD' => now()->toDateString(),
                    'IND_ORD' => trim($datos['indicaciones']),
                    'EST_ORD' => 'Pendiente',
                    'PRI_ORD' => $datos['prioridad'] ?? 'Normal',
                    'COD_CNS' => $codCns,
                    'COD_SRV' => $codSrv,
                ]);
                Auditoria::registrar('Solicitar examen', 'Baja', 'DOM03', 'ORDEN_EXAMEN', $codOrd,
                    "Orden {$codOrd} (" . ($datos['prioridad'] ?? 'Normal') . ") de la consulta {$codCns}", codPac: $c->COD_PAC);
            }

            DB::table('CITA')->where('COD_CIT', $codCit)->update(['EST_CIT' => 'Atendida']);

            Auditoria::registrar('Atender cita', 'Media', 'DOM03', 'CONSULTA', $codCns,
                "Consulta {$codCns} de la cita {$codCit}: diagnóstico y nota de evolución registrados", codPac: $c->COD_PAC);
        });
        } catch (\DomainException $e) {
            return redirect()->route('medico.dashboard')->withErrors(['cita' => $e->getMessage()]);
        }

        return redirect()->route('medico.dashboard')->with('ok', "Cita {$codCit} atendida. La consulta quedó en la historia clínica.");
    }

    /**
     * RN42: un medicamento coincide con una alergia si el nombre del principio activo (primera palabra del
     * medicamento) aparece en la alergia registrada o la alergia aparece en el nombre del medicamento.
     */
    private function conflictosAlergia(array $codMeds, array $alergias): array
    {
        if (!$codMeds || !$alergias) {
            return [];
        }
        $norm = fn ($t) => mb_strtolower(trim(\Illuminate\Support\Str::ascii($t)));
        $meds = DB::table('MEDICAMENTO')->whereIn('COD_MED', $codMeds)->pluck('DES_MED');
        $res = [];
        foreach ($meds as $m) {
            $activo = $norm(explode(' ', trim($m))[0]);
            foreach ($alergias as $a) {
                $al = $norm($a);
                if ($al !== '' && (str_contains($al, $activo) || str_contains($norm($m), $al))) {
                    $res[] = "{$m} ↔ alergia a {$a}";
                }
            }
        }
        return array_values(array_unique($res));
    }
}
