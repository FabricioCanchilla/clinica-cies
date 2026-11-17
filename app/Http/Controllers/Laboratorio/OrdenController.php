<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Services\LaboratorioService;
use App\Services\Auditoria;
use App\Services\MedicoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        Auditoria::registrar('Leer', 'Media', 'DOM03', 'ORDEN_EXAMEN', 'LISTADO', 'Consulta de órdenes de laboratorio');

        return view('laboratorio.index', [
            'contadores' => $this->lab->contadores(),
            'ordenes'    => $this->lab->ordenes($buscar, $estado, $prioridad),
            'estados'    => LaboratorioService::ESTADOS,
            'buscar'     => $buscar,
            'estado'     => $estado,
            'prioridad'  => $prioridad,
        ]);
    }

    public function avanzar(Request $request, string $codOrd)
    {
        $d = $request->validate(['muestra' => 'nullable|string|max:80']);
        return $this->ejecutar(function () use ($codOrd, $d) {
            $nuevo = $this->lab->avanzar($codOrd, auth()->user(), $d['muestra'] ?? null);
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
        Auditoria::registrar('Leer', 'Media', 'DOM03', 'ORDEN_EXAMEN', $codOrd, 'Consulta para cargar resultado', codPac: $orden->COD_PAC);

        return view('laboratorio.resultado', ['orden' => $orden]);
    }

    public function guardarResultado(Request $request, string $codOrd)
    {
        $d = $request->validate([
            'descripcion' => ['required', 'string', 'max:500'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'descripcion.required' => 'Escribe el resultado del examen.',
            'descripcion.max'      => 'El resultado no puede pasar de 500 caracteres.',
        ]);

        $archivo = null;
        if ($request->hasFile('archivo')) {
            $f = $request->file('archivo');
            $archivo = $f->storeAs('laboratorio', Str::uuid().'.'.$f->extension(), 'local');
            if (!$archivo) {
                return back()->withInput()->withErrors(['archivo' => 'No se pudo guardar el archivo. Intenta nuevamente.']);
            }
        }
        try {
            $resultado = $this->lab->guardarResultado($codOrd, trim($d['descripcion']), $request->boolean('critico'), auth()->user(), $archivo);
        } catch (\Throwable $e) {
            if ($archivo) {
                Storage::disk('local')->delete($archivo);
            }
            if (!$e instanceof DomainException) {
                throw $e;
            }
            return redirect()->route('laboratorio.dashboard')->withErrors(['orden' => $e->getMessage()]);
        }

        $respuesta = redirect()->route('laboratorio.dashboard')->with('ok', "Resultado de la orden {$codOrd} cargado. Falta validarlo y publicarlo.");
        if ($request->boolean('critico') && !$resultado['medico_notificado']) {
            $respuesta->with('aviso', 'No se pudo avisar al médico porque no tiene una cuenta activa. Comunícate con él inmediatamente.');
        } elseif ($request->boolean('critico')) {
            $respuesta->with('aviso', 'Se envió una alerta preliminar al médico. Confirma su recepción por el medio de contacto habitual.');
        }
        return $respuesta;
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
                . ($notificado['medico'] ? ' Se avisó al médico.' : ' El médico no tiene usuario activo para recibir el aviso.')
                . ($notificado['paciente'] ? ' Se avisó al paciente.' : ' El paciente no tiene usuario activo para recibir el aviso.');
        });
    }

    public function archivo(string $codRes)
    {
        $resultado = DB::table('RESULTADO_EXAMEN as r')->join('ORDEN_EXAMEN as o', 'o.COD_ORD', '=', 'r.COD_ORD')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('r.COD_RES', $codRes)->first(['r.*', 'h.COD_PAC']);
        abort_unless($resultado && $resultado->ARC_RES && str_starts_with($resultado->ARC_RES, 'laboratorio/'), 404);
        $usuario = auth()->user();
        if ($usuario->COD_ROL === 'ROL006') {
            abort_unless($resultado->EST_RES === 'Publicado' && $usuario->COD_PAC === $resultado->COD_PAC, 404);
        } elseif ($usuario->COD_ROL === 'ROL003') {
            abort_unless($resultado->EST_RES === 'Publicado', 404);
            app(MedicoService::class)->paciente($resultado->COD_PAC);
        } else {
            abort_unless($usuario->COD_ROL === 'ROL007', 403);
        }
        abort_unless(Storage::disk('local')->exists($resultado->ARC_RES), 404);
        Auditoria::registrar('Descargar', 'Alta', 'DOM03', 'RESULTADO_EXAMEN', $codRes, 'Descarga de archivo de resultado', codPac: $resultado->COD_PAC);
        return Storage::disk('local')->download($resultado->ARC_RES, 'resultado-'.$codRes.'.'.pathinfo($resultado->ARC_RES, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
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
