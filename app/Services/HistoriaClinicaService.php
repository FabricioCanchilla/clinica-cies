<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * RF017: expediente clínico completo de un paciente. Reúne lo que está repartido en varias tablas
 * (historia, antecedentes, alergias, consultas con sus adendas, notas de evolución, signos vitales,
 * recetas, resultados publicados, internaciones, consentimientos y derivaciones).
 * RN41: consultas y notas no se editan ni se eliminan; las correcciones son adendas.
 */
class HistoriaClinicaService
{
    /** Devuelve la historia del paciente; la abre si todavía no existe. */
    public function historia(string $codPac, bool $crear = false): ?object
    {
        $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $codPac)->first();
        if ($his || !$crear) {
            return $his;
        }
        $n = (int) DB::table('HISTORIA_CLINICA')->max('id') + 1;
        DB::table('HISTORIA_CLINICA')->insert([
            'COD_HIS' => sprintf('HIS%06d', $n), 'NRO_HIS' => sprintf('HC-%06d', $n),
            'FECAPE_HIS' => now()->toDateString(), 'EST_HIS' => 'Activa', 'NOTADM_HIS' => null, 'COD_PAC' => $codPac,
        ]);
        $his = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $codPac)->first();
        Auditoria::registrar('Abrir historia', 'Media', 'DOM03', 'HISTORIA_CLINICA', $his->COD_HIS,
            "Apertura de la historia {$his->NRO_HIS}", codPac: $codPac);
        return $his;
    }

    /** ¿El médico tiene relación asistencial con el paciente? (cita, internación o derivación a su especialidad) */
    public function medicoTratante(string $codEmp, string $codPac): bool
    {
        $esp = DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->value('COD_ESP');
        return DB::table('CITA')->where('COD_EMP', $codEmp)->where('COD_PAC', $codPac)->exists()
            || DB::table('INTERNACION')->where('COD_EMP', $codEmp)->where('COD_PAC', $codPac)->exists()
            || ($esp && DB::table('DERIVACION')->where('COD_ESP', $esp)->where('COD_PAC', $codPac)->exists());
    }

    public function expediente(string $codPac): array
    {
        $pac = DB::table('PACIENTE')->where('COD_PAC', $codPac)->first();
        if (!$pac) {
            throw new DomainException('El paciente no existe.');
        }
        $his = $this->historia($codPac);
        $codHis = $his->COD_HIS ?? '__';

        $consultas = DB::table('CONSULTA as co')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'co.COD_EMP')
            ->leftJoin('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->leftJoin('CIE10 as x', 'x.COD_CIE', '=', 'co.COD_CIE')
            ->where('co.COD_HIS', $codHis)
            ->orderByDesc('co.FEC_CNS')->limit(60)
            ->get(['co.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'es.DES_ESP', 'x.DES_CIE']);

        $adendas = DB::table('ADENDA as a')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'a.COD_EMP')
            ->whereIn('a.COD_CNS', $consultas->pluck('COD_CNS'))
            ->orderBy('a.FEC_ADE')
            ->get(['a.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP'])
            ->groupBy('COD_CNS');

        return [
            'paciente'     => $pac,
            'historia'     => $his,
            'antecedentes' => DB::table('ANTECEDENTE')->where('COD_HIS', $codHis)->orderBy('TIP_ANT')->get(),
            'alergias'     => DB::table('ALERGIA')->where('COD_HIS', $codHis)->get(),
            'consultas'    => $consultas,
            'adendas'      => $adendas,
            'notas'        => DB::table('NOTA_EVOLUCION as n')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'n.COD_EMP')
                                ->where('n.COD_HIS', $codHis)->orderByDesc('n.FEC_NEV')->limit(30)
                                ->get(['n.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']),
            'signos'       => DB::table('SIGNO_VITAL')->where('COD_PAC', $codPac)->orderByDesc('FEC_SIV')->limit(15)->get(),
            'recetas'      => DB::table('RECETA as r')->join('CONSULTA as co', 'co.COD_CNS', '=', 'r.COD_CNS')
                                ->join('DETALLE_RECETA as d', 'd.COD_REC', '=', 'r.COD_REC')
                                ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
                                ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')
                                ->where('co.COD_HIS', $codHis)->orderByDesc('r.FEC_REC')->limit(40)
                                ->get(['r.COD_REC', 'r.FEC_REC', 'r.EST_REC', 'm.DES_MED', 'd.DOS_DRE', 'd.FRE_DRE', 'd.DUR_DRE', 'v.DES_VIA']),
            'resultados'   => DB::table('ORDEN_EXAMEN as o')->join('CONSULTA as co', 'co.COD_CNS', '=', 'o.COD_CNS')
                                ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
                                ->leftJoin('RESULTADO_EXAMEN as r', 'r.COD_ORD', '=', 'o.COD_ORD')
                                ->where('co.COD_HIS', $codHis)->orderByDesc('o.FEC_ORD')->limit(30)
                                ->get(['o.COD_ORD', 'o.FEC_ORD', 'o.EST_ORD', 'o.PRI_ORD', 's.NOM_SRV', 'r.COD_RES', 'r.ARC_RES', 'r.EST_RES', 'r.DES_RES', 'r.CRI_RES']),
            'internaciones'=> DB::table('INTERNACION')->where('COD_PAC', $codPac)->orderByDesc('FECING_INT')->limit(10)->get(),
            'consentimientos' => DB::table('CONSENTIMIENTO as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
                                ->where('c.COD_PAC', $codPac)->orderByDesc('c.FEC_CNT')
                                ->get(['c.*', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP']),
            'derivaciones' => DB::table('DERIVACION as d')->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'd.COD_ESP')
                                ->join('EMPLEADO as o', 'o.COD_EMP', '=', 'd.ORI_EMP')
                                ->where('d.COD_PAC', $codPac)->orderByDesc('d.FEC_DER')
                                ->get(['d.*', 'es.DES_ESP', 'o.TIT_EMP', 'o.NOM_EMP', 'o.APE_EMP']),
        ];
    }

    public function agregarAntecedente(string $codPac, string $tipo, string $desc): string
    {
        return DB::transaction(function () use ($codPac, $tipo, $desc) {
            $his = $this->historia($codPac, true);
            $cod = sprintf('ANT%05d', (int) DB::table('ANTECEDENTE')->lockForUpdate()->max('id') + 1);
            DB::table('ANTECEDENTE')->insert(['COD_ANT' => $cod, 'TIP_ANT' => $tipo, 'DES_ANT' => $desc, 'COD_HIS' => $his->COD_HIS]);
            Auditoria::registrar('Registrar anteced.', 'Media', 'DOM03', 'ANTECEDENTE', $cod,
                "Antecedente {$tipo}: {$desc}", codPac: $codPac);
            return $cod;
        });
    }

    public function agregarAlergia(string $codPac, string $desc, string $sev): string
    {
        return DB::transaction(function () use ($codPac, $desc, $sev) {
            $his = $this->historia($codPac, true);
            $cod = sprintf('ALE%05d', (int) DB::table('ALERGIA')->lockForUpdate()->max('id') + 1);
            DB::table('ALERGIA')->insert(['COD_ALE' => $cod, 'DES_ALE' => $desc, 'SEV_ALE' => $sev, 'COD_HIS' => $his->COD_HIS]);
            Auditoria::registrar('Registrar alergia', 'Media', 'DOM03', 'ALERGIA', $cod,
                "Alergia «{$desc}» ({$sev}) en la historia {$his->COD_HIS}", codPac: $codPac);
            return $cod;
        });
    }

    /** RN41: la corrección de una consulta se registra como adenda, con autor, fecha y hora. */
    public function agregarAdenda(string $codCns, string $codEmp, string $texto): string
    {
        $cns = DB::table('CONSULTA as c')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('c.COD_CNS', $codCns)->first(['c.COD_CNS', 'h.COD_PAC']);
        if (!$cns) {
            throw new DomainException('La consulta no existe.');
        }
        return DB::transaction(function () use ($codCns, $codEmp, $texto, $cns) {
            $cod = sprintf('ADE%07d', (int) DB::table('ADENDA')->lockForUpdate()->max('id') + 1);
            DB::table('ADENDA')->insert([
                'COD_ADE' => $cod, 'FEC_ADE' => now(), 'TXT_ADE' => $texto, 'COD_CNS' => $codCns, 'COD_EMP' => $codEmp,
            ]);
            Auditoria::registrar('Adenda', 'Alta', 'DOM03', 'ADENDA', $cod,
                "Adenda a la consulta {$codCns}: " . mb_substr($texto, 0, 150), codPac: $cns->COD_PAC);
            return $cod;
        });
    }

    /** RF023: derivación a otra especialidad o interconsulta. */
    public function derivar(string $codPac, string $oriEmp, string $codEsp, string $tipo, string $prioridad,
                            string $motivo, ?string $codCns = null): string
    {
        $cod = sprintf('DER%07d', (int) DB::table('DERIVACION')->max('id') + 1);
        DB::table('DERIVACION')->insert([
            'COD_DER' => $cod, 'FEC_DER' => now(), 'TIP_DER' => $tipo, 'PRI_DER' => $prioridad,
            'MOT_DER' => $motivo, 'EST_DER' => 'Pendiente', 'COD_PAC' => $codPac, 'COD_CNS' => $codCns,
            'ORI_EMP' => $oriEmp, 'COD_ESP' => $codEsp,
        ]);
        $esp = DB::table('ESPECIALIDAD')->where('COD_ESP', $codEsp)->value('DES_ESP');
        Auditoria::registrar('Derivar paciente', 'Media', 'DOM03', 'DERIVACION', $cod,
            "{$tipo} {$prioridad} a {$esp}: " . mb_substr($motivo, 0, 150), codPac: $codPac);

        // Aviso a los médicos de la especialidad destino
        $usuarios = DB::table('USUARIO as u')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'u.COD_EMP')
            ->where('e.COD_ESP', $codEsp)->where('u.COD_ROL', 'ROL003')->where('u.EST_USR', 'Activo')
            ->where('e.COD_EMP', '!=', $oriEmp)->pluck('u.COD_USR');
        foreach ($usuarios as $codUsr) {
            Notificador::sistema($codUsr, $prioridad === 'Urgente' ? 'Interconsulta urgente' : $tipo,
                "{$tipo} {$cod} ({$prioridad}): " . mb_substr($motivo, 0, 180), $cod);
        }
        return $cod;
    }

    public function responderDerivacion(string $codDer, string $codEmp, string $estado, string $respuesta): void
    {
        DB::transaction(function () use ($codDer, $codEmp, $estado, $respuesta) {
            $esp = DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->value('COD_ESP');
            $d = DB::table('DERIVACION')->where('COD_DER', $codDer)->lockForUpdate()->first();
            if (!$d || $d->COD_ESP !== $esp) {
                throw new DomainException('La derivación no es para tu especialidad.');
            }
            if ($d->EST_DER !== 'Pendiente') {
                throw new DomainException("La derivación ya está «{$d->EST_DER}».");
            }
            DB::table('DERIVACION')->where('COD_DER', $codDer)->update([
                'EST_DER' => $estado, 'RES_DER' => $respuesta, 'FECRES_DER' => now(), 'DES_EMP' => $codEmp,
            ]);
            Auditoria::registrar('Responder deriv.', 'Media', 'DOM03', 'DERIVACION', $codDer,
                "{$estado}: " . mb_substr($respuesta, 0, 180), codPac: $d->COD_PAC);

            $codUsr = DB::table('USUARIO')->where('COD_EMP', $d->ORI_EMP)->value('COD_USR');
            if ($codUsr) {
                Notificador::sistema($codUsr, 'Respuesta de interconsulta', "{$codDer} {$estado}: " . mb_substr($respuesta, 0, 180), $codDer);
            }
        });
    }
}
