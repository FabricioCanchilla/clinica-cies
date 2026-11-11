<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Services\LaboratorioService;
use DomainException;
use Illuminate\Http\Request;

class OrdenController extends Controller
{
    public function __construct(private LaboratorioService $lab)
    {
    }

    public function index(Request $request)
    {
        $buscar    = trim((string) $request->query('buscar', ''));
        $estado    = (string) $request->query('estado', '');
        $prioridad = (string) $request->query('prioridad', '');

        if (!in_array($estado, LaboratorioService::ESTADOS, true)) {
            $estado = '';
        }
        if (!in_array($prioridad, ['Normal', 'Urgente'], true)) {
            $prioridad = '';
        }

        return view('laboratorio.index', [
            'contadores' => $this->lab->contadores(),
            'ordenes'    => $this->lab->ordenes($buscar, $estado, $prioridad),
            'estados'    => LaboratorioService::ESTADOS,
            'buscar'     => $buscar,
            'estado'     => $estado,
            'prioridad'  => $prioridad,
        ]);
    }

    public function avanzar(string $codOrd)
    {
        return $this->ejecutar(function () use ($codOrd) {
            $nuevo = $this->lab->avanzar($codOrd, auth()->user());
            return "Orden {$codOrd}: {$nuevo}.";
        });
    }

    public function cancelar(string $codOrd)
    {
        return $this->ejecutar(function () use ($codOrd) {
            $this->lab->cancelar($codOrd, auth()->user());
            return "Orden {$codOrd} cancelada.";
        });
    }

    public function formResultado(string $codOrd)
    {
        $orden = $this->lab->orden($codOrd);

        if (!$orden || $orden->EST_ORD !== 'En proceso' || $this->lab->resultadoDe($codOrd)) {
            return redirect()->route('laboratorio.dashboard')
                ->withErrors(['orden' => 'Esa orden no admite carga de resultado (debe estar «En proceso» y sin resultado).']);
        }

        return view('laboratorio.resultado', ['orden' => $orden]);
    }

    public function guardarResultado(Request $request, string $codOrd)
    {
        $d = $request->validate([
            'descripcion' => ['required', 'string', 'max:500'],
        ], [
            'descripcion.required' => 'Escribe el resultado del examen.',
            'descripcion.max'      => 'El resultado no puede pasar de 500 caracteres.',
        ]);

        try {
            $this->lab->guardarResultado($codOrd, trim($d['descripcion']), $request->boolean('critico'), auth()->user());
        } catch (DomainException $e) {
            return redirect()->route('laboratorio.dashboard')->withErrors(['orden' => $e->getMessage()]);
        }

        return redirect()->route('laboratorio.dashboard')
            ->with('ok', "Resultado de la orden {$codOrd} cargado. Falta validarlo y publicarlo.");
    }

    public function validar(string $codOrd)
    {
        return $this->ejecutar(function () use ($codOrd) {
            $this->lab->validar($codOrd, auth()->user());
            return "Resultado de la orden {$codOrd} validado.";
        });
    }

    public function publicar(string $codOrd)
    {
        return $this->ejecutar(function () use ($codOrd) {
            $notificado = $this->lab->publicar($codOrd, auth()->user());
            return "Resultado de la orden {$codOrd} publicado. La orden quedó «Listo»."
                . ($notificado ? ' Se avisó al médico del valor crítico.' : '');
        });
    }

    /** Ejecuta una acción y traduce los errores de negocio a un mensaje en pantalla. */
    private function ejecutar(callable $accion)
    {
        try {
            $mensaje = $accion();
        } catch (DomainException $e) {
            return back()->withErrors(['orden' => $e->getMessage()]);
        }

        return back()->with('ok', $mensaje);
    }
}