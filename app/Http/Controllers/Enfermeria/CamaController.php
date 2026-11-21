<?php

namespace App\Http\Controllers\Enfermeria;

use App\Http\Controllers\Controller;
use App\Services\CamaService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Camas de internación: internar, alta, aislamiento y limpieza (RF035, RN39, AJ07, AJ08). */
class CamaController extends Controller
{
    public function __construct(private CamaService $camas)
    {
    }

    public function index()
    {
        $mapa = $this->camas->mapa();

        return view('enfermeria.camas', [
            'camas'         => $mapa,
            'porEstado'     => $mapa->countBy('EST_CAM'),
            'historial'     => $this->camas->historialLimpiezas(),
            'pacientes'     => DB::table('PACIENTE as p')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('INTERNACION as i')
                    ->whereColumn('i.COD_PAC', 'p.COD_PAC')->where('i.EST_INT', 'Internado'))
                ->orderBy('p.APE_PAC')->get(['p.COD_PAC', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC']),
            'medicos'       => DB::table('EMPLEADO as e')->join('ESPECIALIDAD as es', 'es.COD_ESP', '=', 'e.COD_ESP')
                ->orderBy('es.DES_ESP')->orderBy('e.APE_EMP')->get(['e.COD_EMP', 'e.TIT_EMP', 'e.NOM_EMP', 'e.APE_EMP', 'es.DES_ESP']),
            'aislamientos'  => CamaService::AISLAMIENTOS,
            'motivos'       => CamaService::MOTIVOS,
            'minTerminal'   => $this->camas->minutosTerminal(),
        ]);
    }

    public function internar(Request $request, string $codCam)
    {
        $d = $request->validate([
            'paciente'    => 'required|string|max:10',
            'medico'      => 'required|string|max:9',
            'motivo'      => 'required|string|max:200',
            'aislamiento' => 'nullable|string|max:40',
        ]);
        return $this->ejecutar(function () use ($d, $codCam) {
            $cod = $this->camas->internar($codCam, $d['paciente'], $d['medico'], $d['motivo'], $d['aislamiento'] ?? null, auth()->user());
            return "Paciente internado ({$cod}).";
        });
    }

    public function aislamiento(Request $request, string $codInt)
    {
        $d = $request->validate(['aislamiento' => 'nullable|string|max:40']);
        return $this->ejecutar(function () use ($d, $codInt) {
            $this->camas->marcarAislamiento($codInt, $d['aislamiento'] ?? null, auth()->user());
            return empty($d['aislamiento']) ? 'Se retiró el aislamiento.' : "Aislamiento registrado: {$d['aislamiento']}. Al alta, la cama exigirá desinfección terminal.";
        });
    }

    public function alta(string $codInt)
    {
        return $this->ejecutar(function () use ($codInt) {
            $tipo = $this->camas->alta($codInt, auth()->user());
            return "Alta registrada. La cama queda fuera de servicio hasta completar la limpieza {$tipo}.";
        });
    }

    public function reportar(Request $request, string $codCam)
    {
        $d = $request->validate(['motivo' => 'required|string|max:60', 'observacion' => 'nullable|string|max:150']);
        return $this->ejecutar(function () use ($d, $codCam) {
            $tipo = $this->camas->reportar($codCam, $d['motivo'], $d['observacion'] ?? null, auth()->user());
            return "Reporte registrado: se requiere limpieza {$tipo}.";
        });
    }

    public function iniciar(string $codLim)
    {
        return $this->ejecutar(function () use ($codLim) {
            $this->camas->iniciar($codLim, auth()->user());
            return 'Limpieza iniciada.';
        });
    }

    public function completar(Request $request, string $codLim)
    {
        $d = $request->validate(['observacion' => 'nullable|string|max:150']);
        return $this->ejecutar(fn () => $this->camas->completar($codLim, [
            'sabanas'     => $request->boolean('sabanas'),
            'superficies' => $request->boolean('superficies'),
            'colchon'     => $request->boolean('colchon'),
            'ventilacion' => $request->boolean('ventilacion'),
        ], $d['observacion'] ?? null, auth()->user()));
    }

    private function ejecutar(callable $accion)
    {
        try {
            $mensaje = $accion();
        } catch (DomainException $e) {
            return back()->withErrors(['cama' => $e->getMessage()])->withInput();
        }
        return back()->with('ok', $mensaje);
    }
}
