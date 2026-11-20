<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerificarPermiso;
use App\Services\Auditoria;
use App\Services\RespaldoService;
use App\Support\Parametros;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RF044 configuración general: parámetros, especialidades, tarifas de servicios y respaldos.
 * RF043 asignación de permisos mínimos por rol (ROL_PERMISO).
 */
class ConfiguracionController extends Controller
{
    public function __construct(private RespaldoService $respaldos)
    {
    }

    public function index(Request $request)
    {
        $roles = DB::table('ROL')->orderBy('COD_ROL')->get();
        $asignados = DB::table('ROL_PERMISO')->get()->groupBy('COD_ROL')->map(fn ($g) => $g->pluck('COD_PER')->all());

        return view('admin.configuracion.index', [
            'seccion'        => $request->query('seccion', 'parametros'),
            'parametros'     => DB::table('PARAMETRO as p')->leftJoin('USUARIO as u', 'u.COD_USR', '=', 'p.COD_USR')
                                  ->orderBy('p.id')->get(['p.*', 'u.USR_USR']),
            'especialidades' => DB::table('ESPECIALIDAD as e')
                                  ->leftJoin('EMPLEADO as m', 'm.COD_ESP', '=', 'e.COD_ESP')
                                  ->groupBy('e.COD_ESP', 'e.DES_ESP')->orderBy('e.DES_ESP')
                                  ->get(['e.COD_ESP', 'e.DES_ESP', DB::raw('COUNT(m.COD_EMP) as MEDICOS')]),
            'servicios'      => DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
                                  ->orderBy('c.DES_CLA')->orderBy('s.NOM_SRV')->get(['s.*', 'c.DES_CLA']),
            'clasificaciones'=> DB::table('CLASIFICACION_SERVICIO')->orderBy('DES_CLA')->get(),
            'medicamentos'   => DB::table('MEDICAMENTO')->orderBy('DES_MED')->get(),
            'roles'          => $roles,
            'modulos'        => config('permisos.modulos'),
            'asignados'      => $asignados,
            'protegidos'     => config('permisos.protegidos'),
            'respaldos'      => $this->respaldos->lista(),
        ]);
    }

    public function parametros(Request $request)
    {
        $params = DB::table('PARAMETRO')->get()->keyBy('COD_PAR');
        $reglas = [];
        foreach ($params as $cod => $p) {
            $reglas["p.$cod"] = "required|integer|min:{$p->MIN_PAR}|max:{$p->MAX_PAR}";
        }
        $d = $request->validate($reglas, [], collect($params)->mapWithKeys(fn ($p, $c) => ["p.$c" => $p->DES_PAR])->all());

        $cambios = [];
        foreach ($d['p'] as $cod => $val) {
            if ((string) $params[$cod]->VAL_PAR !== (string) $val) {
                DB::table('PARAMETRO')->where('COD_PAR', $cod)->update([
                    'VAL_PAR' => (string) $val, 'FEC_PAR' => now(), 'COD_USR' => auth()->user()->COD_USR,
                ]);
                $cambios[] = "{$cod}: {$params[$cod]->VAL_PAR} → {$val}";
            }
        }
        Parametros::olvidar();
        if ($cambios) {
            Auditoria::registrar('Configuración', 'Alta', 'DOM01', 'PARAMETRO', 'PARAMETRO', implode('; ', $cambios));
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'parametros'])
            ->with('ok', $cambios ? 'Parámetros actualizados: ' . implode('; ', $cambios) : 'Sin cambios.');
    }

    public function especialidad(Request $request)
    {
        $d = $request->validate([
            'codigo'      => 'nullable|exists:ESPECIALIDAD,COD_ESP',
            'descripcion' => 'required|string|max:40',
        ]);
        $desc = trim($d['descripcion']);
        $dup = DB::table('ESPECIALIDAD')->whereRaw('LOWER(DES_ESP) = ?', [mb_strtolower($desc)])
            ->when($d['codigo'] ?? null, fn ($q, $c) => $q->where('COD_ESP', '!=', $c))->exists();
        if ($dup) {
            return back()->withErrors(['descripcion' => 'Ya existe una especialidad con ese nombre.']);
        }
        if (!empty($d['codigo'])) {
            $antes = DB::table('ESPECIALIDAD')->where('COD_ESP', $d['codigo'])->value('DES_ESP');
            DB::table('ESPECIALIDAD')->where('COD_ESP', $d['codigo'])->update(['DES_ESP' => $desc]);
            Auditoria::registrar('Editar especialidad', 'Media', 'DOM01', 'ESPECIALIDAD', $d['codigo'], "{$antes} → {$desc}");
            $msg = 'Especialidad actualizada.';
        } else {
            $cod = sprintf('ESP%04d', (int) DB::table('ESPECIALIDAD')->max(DB::raw('CAST(SUBSTRING(COD_ESP, 4) AS UNSIGNED)')) + 1);
            DB::table('ESPECIALIDAD')->insert(['COD_ESP' => $cod, 'DES_ESP' => $desc]);
            Auditoria::registrar('Crear especialidad', 'Media', 'DOM01', 'ESPECIALIDAD', $cod, $desc);
            $msg = "Especialidad {$cod} creada.";
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'especialidades'])->with('ok', $msg);
    }

    public function servicio(Request $request)
    {
        $d = $request->validate([
            'codigo'        => 'nullable|exists:SERVICIO,COD_SRV',
            'nombre'        => 'required|string|max:50',
            'precio'        => 'required|numeric|min:0|max:99999',
            'clasificacion' => 'required|exists:CLASIFICACION_SERVICIO,COD_CLA',
        ]);
        $fila = ['NOM_SRV' => trim($d['nombre']), 'PRE_SRV' => round((float) $d['precio'], 2), 'COD_CLA' => $d['clasificacion']];
        if (!empty($d['codigo'])) {
            $antes = DB::table('SERVICIO')->where('COD_SRV', $d['codigo'])->first();
            DB::table('SERVICIO')->where('COD_SRV', $d['codigo'])->update($fila);
            Auditoria::registrar('Cambiar tarifa', 'Alta', 'DOM04', 'SERVICIO', $d['codigo'],
                "{$antes->NOM_SRV}: Bs {$antes->PRE_SRV} → Bs {$fila['PRE_SRV']}");
            $msg = 'Tarifa actualizada.';
        } else {
            $cod = sprintf('SRV%05d', (int) DB::table('SERVICIO')->max(DB::raw('CAST(SUBSTRING(COD_SRV, 4) AS UNSIGNED)')) + 1);
            DB::table('SERVICIO')->insert(['COD_SRV' => $cod] + $fila);
            Auditoria::registrar('Crear servicio', 'Media', 'DOM04', 'SERVICIO', $cod, "{$fila['NOM_SRV']} Bs {$fila['PRE_SRV']}");
            $msg = "Servicio {$cod} creado.";
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'tarifas'])->with('ok', $msg);
    }

    public function medicamento(Request $request)
    {
        $d = $request->validate([
            'codigo' => 'required|exists:MEDICAMENTO,COD_MED',
            'precio' => 'required|numeric|min:0|max:99999',
        ]);
        $antes = DB::table('MEDICAMENTO')->where('COD_MED', $d['codigo'])->first();
        DB::table('MEDICAMENTO')->where('COD_MED', $d['codigo'])->update(['PRE_MED' => round((float) $d['precio'], 2)]);
        Auditoria::registrar('Precio medicamento', 'Media', 'DOM04', 'MEDICAMENTO', $d['codigo'],
            "{$antes->DES_MED}: Bs {$antes->PRE_MED} → Bs {$d['precio']}");
        return redirect()->route('admin.configuracion', ['seccion' => 'tarifas'])->with('ok', 'Precio actualizado.');
    }

    /** RF043: matriz rol × permiso. */
    public function permisos(Request $request)
    {
        $d = $request->validate(['m' => 'nullable|array', 'm.*' => 'array', 'm.*.*' => 'string']);
        $modulos = array_keys(config('permisos.modulos'));
        $roles = DB::table('ROL')->pluck('COD_ROL')->all();
        $nuevo = [];
        foreach ($roles as $rol) {
            $nuevo[$rol] = array_values(array_intersect($d['m'][$rol] ?? [], $modulos));
        }
        foreach (config('permisos.protegidos') as $rol => $pers) {
            $nuevo[$rol] = array_values(array_unique(array_merge($nuevo[$rol] ?? [], $pers)));
        }

        $antes = DB::table('ROL_PERMISO')->get()->groupBy('COD_ROL')->map(fn ($g) => $g->pluck('COD_PER')->all())->all();
        $cambios = [];
        DB::transaction(function () use ($nuevo, $antes, &$cambios) {
            foreach ($nuevo as $rol => $pers) {
                $previos = $antes[$rol] ?? [];
                $mas = array_diff($pers, $previos); $menos = array_diff($previos, $pers);
                if ($mas || $menos) {
                    $cambios[] = $rol . ($mas ? ' +' . implode(',', $mas) : '') . ($menos ? ' -' . implode(',', $menos) : '');
                }
                DB::table('ROL_PERMISO')->where('COD_ROL', $rol)->delete();
                foreach ($pers as $per) {
                    DB::table('ROL_PERMISO')->insert(['COD_ROL' => $rol, 'COD_PER' => $per]);
                }
            }
        });
        VerificarPermiso::limpiarCache();
        if ($cambios) {
            Auditoria::registrar('Cambiar permisos', 'Alta', 'DOM01', 'ROL_PERMISO', 'ROL_PERMISO', implode('; ', $cambios));
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'permisos'])
            ->with('ok', $cambios ? 'Permisos actualizados: ' . implode('; ', $cambios) : 'Sin cambios en los permisos.');
    }

    // ---------- RN48: respaldos ----------

    public function crearRespaldo()
    {
        try {
            $r = $this->respaldos->crear('Manual', auth()->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['respaldo' => 'No se pudo crear el respaldo: ' . $e->getMessage()]);
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'respaldos'])
            ->with('ok', "Respaldo {$r->COD_RES} creado: {$r->TAB_RES} tablas, " . number_format($r->FIL_RES) . ' filas.');
    }

    public function probarRespaldo(string $codRes)
    {
        try {
            $r = $this->respaldos->probar($codRes, auth()->user());
        } catch (DomainException $e) {
            return back()->withErrors(['respaldo' => $e->getMessage()]);
        }
        return redirect()->route('admin.configuracion', ['seccion' => 'respaldos'])
            ->with($r['ok'] ? 'ok' : 'aviso', $r['detalle']);
    }

    public function descargarRespaldo(string $codRes)
    {
        $ruta = $this->respaldos->ruta($codRes);
        abort_if(!$ruta, 404);
        Auditoria::registrar('Descargar respaldo', 'Crítica', 'DOM02', 'RESPALDO', $codRes,
            'Descarga del archivo de respaldo completo de la base');
        return response()->download($ruta);
    }
}
