<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaldoPacienteService
{
    public function disponible(string $paciente): float
    {
        $creditos = (float) DB::table('SALDO_CREDITO')->where('COD_PAC', $paciente)->sum('MONTO');
        $usado = (float) DB::table('SALDO_APLICACION as a')->join('SALDO_CREDITO as c', 'c.id', '=', 'a.CREDITO_ID')
            ->where('c.COD_PAC', $paciente)->sum('a.MONTO');
        return round($creditos - $usado, 2);
    }

    public function cancelar(string $codigo, ?string $paciente, string $motivo): float
    {
        return DB::transaction(function () use ($codigo, $paciente, $motivo) {
            $referencia = DB::table('CITA')->where('COD_CIT', $codigo)->first();
            abort_unless($referencia && (!$paciente || $referencia->COD_PAC === $paciente), 404);
            DB::table('PACIENTE')->where('COD_PAC', $referencia->COD_PAC)->lockForUpdate()->first();
            $cita = DB::table('CITA')->where('COD_CIT', $codigo)->lockForUpdate()->first();
            abort_unless(in_array($cita->EST_CIT, ['Pendiente de pago', 'Confirmada'], true), 422, 'Esta cita no se puede cancelar.');
            abort_if($cita->EST_CIT === 'Confirmada' && Carbon::parse($cita->FEC_CIT.' '.$cita->HOR_CIT)->lt(now()->addDay()), 422, 'La cancelación requiere al menos 24 horas de anticipación.');
            abort_if(DB::table('MEDICO_ATENCION')->where('COD_CIT', $codigo)->where('ESTADO', 'En consulta')->exists(), 422, 'La consulta ya comenzó.');
            abort_if(DB::table('PAGO')->where('COD_CIT', $codigo)->where('EST_PAG', 'Confirmado')->where('TIP_PAG', 'Total')->exists(), 422, 'La cita tiene un pago final y requiere revisión de caja.');
            $pagado = (float) DB::table('PAGO')->where('COD_CIT', $codigo)->where('EST_PAG', 'Confirmado')->where('TIP_PAG', 'Adelanto')->sum('MON_PAG');
            $aplicado = (float) DB::table('SALDO_APLICACION')->where('COD_CIT', $codigo)->sum('MONTO');
            $monto = round($pagado + $aplicado, 2);
            if ($monto > 0) {
                DB::table('SALDO_CREDITO')->insert(['COD_PAC' => $cita->COD_PAC, 'COD_CIT' => $codigo, 'MONTO' => $monto, 'FECHA' => now()]);
            }
            DB::table('CITA')->where('COD_CIT', $codigo)->update(['EST_CIT' => 'Cancelada', 'FECEXP_CIT' => null]);
            Auditoria::registrar('Cancelar cita', 'Media', 'DOM04', 'CITA', $codigo, "Motivo: {$motivo}. Saldo conservado: Bs {$monto}", codPac: $cita->COD_PAC);
            return $monto;
        });
    }

    public function aplicar(string $codigo, string $paciente): float
    {
        return DB::transaction(function () use ($codigo, $paciente) {
            DB::table('PACIENTE')->where('COD_PAC', $paciente)->lockForUpdate()->first();
            $cita = DB::table('CITA as c')->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
                ->where('c.COD_CIT', $codigo)->where('c.COD_PAC', $paciente)->lockForUpdate()->first(['c.*', 's.PRE_SRV']);
            if (!$cita || $cita->EST_CIT !== 'Pendiente de pago' || !$cita->FECEXP_CIT || Carbon::parse($cita->FECEXP_CIT)->lt(now())) {
                throw ValidationException::withMessages(['saldo' => 'La reserva ya no está disponible para aplicar el saldo.']);
            }
            $centavos = (int) round(app(CitaService::class)->montoAnticipo((float) $cita->PRE_SRV) * 100);
            if ($centavos <= 0 || (int) round($this->disponible($paciente) * 100) < $centavos) {
                throw ValidationException::withMessages(['saldo' => 'El saldo disponible no cubre el anticipo completo.']);
            }
            $resto = $centavos;
            $creditos = DB::table('SALDO_CREDITO')->where('COD_PAC', $paciente)->orderBy('id')->lockForUpdate()->get();
            foreach ($creditos as $credito) {
                $usado = (float) DB::table('SALDO_APLICACION')->where('CREDITO_ID', $credito->id)->sum('MONTO');
                $libre = (int) round(((float) $credito->MONTO - $usado) * 100);
                $tomar = min($resto, max(0, $libre));
                if ($tomar > 0) {
                    DB::table('SALDO_APLICACION')->insert(['CREDITO_ID' => $credito->id, 'COD_CIT' => $codigo, 'MONTO' => $tomar / 100, 'FECHA' => now()]);
                    $resto -= $tomar;
                }
                if ($resto === 0) { break; }
            }
            if ($resto !== 0) { throw ValidationException::withMessages(['saldo' => 'El saldo cambió. Vuelve a intentarlo.']); }
            DB::table('CITA')->where('COD_CIT', $codigo)->update(['EST_CIT' => 'Confirmada', 'FECEXP_CIT' => null]);
            Auditoria::registrar('Aplicar saldo', 'Media', 'DOM04', 'CITA', $codigo, 'Anticipo cubierto con saldo: Bs '.($centavos / 100), codPac: $paciente);
            return $centavos / 100;
        });
    }
}
