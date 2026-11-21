<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use App\Services\RecomendadorCamasService;
use App\Support\ModeloCamas;
use Illuminate\Http\Request;

/**
 * RF050 · Recomendador de capacidad de camas (solo rol Administrador, RNF001).
 * La consulta y la decisión final quedan registradas en AUDITORIA (RN44).
 */
class RecomendadorCamasController extends Controller
{
    public function __construct(private RecomendadorCamasService $servicio)
    {
    }

    public function index(Request $request)
    {
        $capRegistrada = $this->servicio->capacidadRegistrada();

        $datos = $request->validate([
            'capRef'          => 'nullable|integer|min:1|max:500',
            'u1'              => 'nullable|numeric|min:1|max:98',
            'u2'              => 'nullable|numeric|min:2|max:99',
            'n'               => 'nullable|integer|min:1|max:12',
            'nmin'            => 'nullable|integer|min:1|max:24',
            'fuente'          => 'nullable|in:auto,historial,referencia',
            'inicio'          => 'nullable|in:auto,0,1,2',
            'modo'            => 'nullable|in:matriz,costos',
            'cap'             => 'nullable|array|size:3',
            'cap.*'           => 'integer|min:1|max:500|distinct',
            'b'               => 'nullable|array|size:3',
            'b.*'             => 'array|size:3',
            'b.*.*'           => 'numeric|min:-10000000|max:10000000',
            'ic'              => 'nullable|numeric|min:0|max:1000000',
            'cf'              => 'nullable|numeric|min:0|max:1000000',
            'cv'              => 'nullable|numeric|min:0|max:1000000',
            'dem'             => 'nullable|array|size:3',
            'dem.*'           => 'numeric|min:0|max:500',
        ], [
            'cap.*.distinct' => 'Las tres alternativas deben tener capacidades distintas.',
        ]);

        $u1 = (float) ($datos['u1'] ?? 50);
        $u2 = (float) ($datos['u2'] ?? 75);
        if ($u1 >= $u2) {
            return back()->withErrors(['u1' => 'El umbral de demanda baja debe ser menor que el de demanda alta.'])->withInput();
        }

        $capacidades = array_map('intval', $datos['cap'] ?? [10, 12, 15]);
        $modo = $datos['modo'] ?? 'costos';
        $costos = [
            'ic'  => (float) ($datos['ic'] ?? 410),    // tarifa por día-cama (Bs.)
            'cf'  => (float) ($datos['cf'] ?? 2300),   // costo fijo por cama habilitada al mes (Bs.)
            'cv'  => (float) ($datos['cv'] ?? 105),    // costo de atención por día-cama ocupada (Bs.)
            'dem' => array_map('floatval', $datos['dem'] ?? [4.0, 6.8, 12.4]),
        ];

        if ($modo === 'costos') {
            $pagos = ModeloCamas::pagosDesdeCostos($capacidades, $costos['dem'], $costos['ic'], $costos['cf'], $costos['cv']);
        } else {
            $pagos = isset($datos['b'])
                ? array_map(fn ($f) => array_map('floatval', array_values($f)), array_values($datos['b']))
                : ModeloCamas::PAGOS_REFERENCIA;
        }

        $param = [
            'capRef'      => (int) ($datos['capRef'] ?? $capRegistrada),
            'u1'          => $u1,
            'u2'          => $u2,
            'n'           => (int) ($datos['n'] ?? 3),
            'nmin'        => (int) ($datos['nmin'] ?? 3),
            'fuente'      => $datos['fuente'] ?? 'auto',
            'inicio'      => $datos['inicio'] ?? 'auto',
            'capacidades' => $capacidades,
            'pagos'       => $pagos,
        ];

        try {
            $r = $this->servicio->calcular($param);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['modelo' => 'No se pudo resolver el modelo: ' . $e->getMessage()])->withInput();
        }

        $alt = $r['recLargo'];
        Auditoria::registrar(
            'Planificación camas', 'Baja', 'DOM04', 'CAMA', 'RF050',
            sprintf('Consulta de planificación de camas: sugerida %d camas, resultado esperado Bs. %s/mes, %d meses, datos: %s',
                $capacidades[$alt], number_format($r['ve']['π (largo plazo)'][$alt], 2, '.', ','), $param['n'],
                $r['usaHistorial'] ? 'historial' : 'referencia')
        );

        return view('admin.recomendador.index', [
            'r'             => $r,
            'param'         => $param,
            'modo'          => $modo,
            'costos'        => $costos,
            'capRegistrada' => $capRegistrada,
            'estados'       => RecomendadorCamasService::ESTADOS,
            'decisiones'    => $this->servicio->decisiones(),
        ]);
    }

    /** El administrador confirma la capacidad; la decisión queda en AUDITORIA sin edición ni borrado. */
    public function decidir(Request $request)
    {
        $datos = $request->validate([
            'alternativa' => 'required|integer|in:0,1,2',
            'capacidad'   => 'required|integer|min:1|max:500',
            'criterio'    => 'required|string|max:40',
            'valor'       => 'required|numeric',
            'fuente'      => 'required|in:historial,referencia',
            'observacion' => 'nullable|string|max:120',
        ]);

        $detalle = sprintf('Habilitar %d camas · motivo: %s · resultado esperado Bs. %s/mes · datos: %s',
            $datos['capacidad'], $datos['criterio'], number_format((float) $datos['valor'], 2, '.', ','), $datos['fuente']);
        if (! empty($datos['observacion'])) {
            $detalle .= ' · ' . $datos['observacion'];
        }

        Auditoria::registrar('Decisión capacidad', 'Media', 'DOM04', 'CAMA', 'RF050', $detalle);

        return redirect()->to(url()->previous() . '#decision')
            ->with('ok', "Decisión registrada: habilitar {$datos['capacidad']} camas.");
    }
}
