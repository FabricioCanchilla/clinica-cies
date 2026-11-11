<?php

namespace App\Http\Controllers\Farmacia;

use App\Http\Controllers\Controller;
use App\Services\FarmaciaService;
use DomainException;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    public function __construct(private FarmaciaService $farmacia)
    {
    }

    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');

        if (!in_array($estado, FarmaciaService::ESTADOS_LOTE, true)) {
            $estado = '';
        }

        return view('farmacia.lotes', [
            'contadores'   => $this->farmacia->contadoresLotes(),
            'existencias'  => $this->farmacia->existencias(),
            'lotes'        => $this->farmacia->lotes($buscar, $estado),
            'medicamentos' => $this->farmacia->medicamentos(),
            'estados'      => FarmaciaService::ESTADOS_LOTE,
            'diasAlerta'   => FarmaciaService::DIAS_ALERTA,
            'buscar'       => $buscar,
            'estado'       => $estado,
        ]);
    }

    public function entrada(Request $request)
    {
        $d = $request->validate([
            'cod_med'  => ['required', 'string', 'max:8'],
            'nro_lot'  => ['required', 'string', 'max:20'],
            'fecven'   => ['required', 'date', 'after:today'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'cod_med.required'  => 'Elige el medicamento.',
            'nro_lot.required'  => 'Escribe el número de lote.',
            'nro_lot.max'       => 'El número de lote no puede pasar de 20 caracteres.',
            'fecven.required'   => 'Indica la fecha de vencimiento.',
            'fecven.after'      => 'La fecha de vencimiento debe ser posterior a hoy.',
            'cantidad.required' => 'Indica la cantidad que ingresa.',
            'cantidad.min'      => 'La cantidad debe ser al menos 1.',
        ]);

        try {
            $cod = $this->farmacia->registrarEntrada(
                $d['cod_med'], trim($d['nro_lot']), $d['fecven'], (int) $d['cantidad'], auth()->user()
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lote' => $e->getMessage()]);
        }

        return redirect()->route('farmacia.lotes')->with('ok', "Entrada registrada: lote {$cod}.");
    }

    public function formBaja(string $codLot)
    {
        $lote = $this->farmacia->lote($codLot);

        if (!$lote || (int) $lote->CANDIS_LOT < 1) {
            return redirect()->route('farmacia.lotes')
                ->withErrors(['lote' => 'Ese lote no existe o no tiene stock para dar de baja.']);
        }

        return view('farmacia.baja', [
            'lote'    => $lote,
            'motivos' => FarmaciaService::MOTIVOS_BAJA,
        ]);
    }

    public function guardarBaja(Request $request, string $codLot)
    {
        $d = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo'   => ['required', 'in:' . implode(',', FarmaciaService::MOTIVOS_BAJA)],
        ], [
            'cantidad.required' => 'Indica la cantidad a dar de baja.',
            'cantidad.min'      => 'La cantidad debe ser al menos 1.',
            'motivo.required'   => 'El motivo de la baja es obligatorio.',
            'motivo.in'         => 'Elige un motivo válido.',
        ]);

        try {
            $this->farmacia->darBaja($codLot, (int) $d['cantidad'], $d['motivo'], auth()->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lote' => $e->getMessage()]);
        }

        return redirect()->route('farmacia.lotes')
            ->with('ok', "Baja registrada en el lote {$codLot} ({$d['motivo']}).");
    }
}