<?php

namespace App\Http\Controllers\Comun;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

/**
 * Orden de pago de una cita: detalle del servicio, lo ya pagado (anticipo) y el saldo a pagar en caja.
 * Número de orden: OP-<código de cita>. La caja la busca por ese número (RF039).
 * La emiten recepción y administración; la caja y el paciente (la suya) también la pueden ver.
 */
class OrdenPagoController extends Controller
{
    public function show(string $codCit)
    {
        return view('ordenes.pago', ['o' => $this->datos($codCit)]);
    }

    public function pdf(string $codCit)
    {
        $o = $this->datos($codCit);
        Auditoria::registrar('Orden de pago', 'Baja', 'DOM04', 'CITA', $codCit,
            "Emisión de la orden de pago {$o->NRO} por Bs {$o->SALDO}", codPac: $o->COD_PAC);

        $op = new Options();
        $op->set('isRemoteEnabled', false);
        $op->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($op);
        $pdf->loadHtml(view('ordenes.pago-pdf', ['o' => $o])->render(), 'UTF-8');
        $pdf->setPaper('A5', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="orden_pago_' . $codCit . '.pdf"',
        ]);
    }

    private function datos(string $codCit): object
    {
        $o = DB::table('CITA as c')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'c.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->leftJoin('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'c.COD_SRV')
            ->join('CONSULTORIO as k', 'k.COD_CON', '=', 'c.COD_CON')
            ->where('c.COD_CIT', $codCit)
            ->first(['c.*', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP',
                     'es.DES_ESP', 's.COD_SRV', 's.NOM_SRV', 's.PRE_SRV', 'k.NOM_CON', 'k.UBI_CON']);
        abort_unless($o, 404, 'La cita no existe.');

        $u = auth()->user();
        if ($u->COD_ROL === 'ROL006') {
            abort_unless($u->COD_PAC === $o->COD_PAC, 403);
        }

        $o->PAGOS = DB::table('PAGO as pg')->join('METODO_PAGO as m', 'm.COD_MET', '=', 'pg.COD_MET')
            ->where('pg.COD_CIT', $codCit)->where('pg.EST_PAG', 'Confirmado')
            ->orderBy('pg.FEC_PAG')->get(['pg.COD_PAG', 'pg.FEC_PAG', 'pg.MON_PAG', 'pg.TIP_PAG', 'm.DES_MET']);
        $o->PAGADO = round((float) $o->PAGOS->sum('MON_PAG'), 2);
        $o->SALDO = max(0, round((float) $o->PRE_SRV - $o->PAGADO, 2));
        $o->NRO = 'OP-' . $codCit;
        $o->ESTADO_ORDEN = match (true) {
            in_array($o->EST_CIT, ['Cancelada', 'Expirada', 'No asistio'], true) => 'Anulada (cita ' . strtolower($o->EST_CIT) . ')',
            $o->SALDO <= 0 => 'Pagada',
            $o->EST_CIT === 'Pendiente de pago' => 'Pendiente: pagar el anticipo para confirmar la cita',
            default => 'Pendiente de pago en caja',
        };
        $o->COBRABLE = $o->SALDO > 0 && in_array($o->EST_CIT, ['Confirmada', 'En espera', 'En consulta', 'Atendida'], true);
        return $o;
    }
}
