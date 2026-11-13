<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    private const CRITICIDADES = ['Baja', 'Media', 'Alta', 'Crítica'];

    public function index(Request $request)
    {
        [$query, $f, $dominios, $roles, $acciones] = $this->consulta($request);

        $registros = $query
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.auditoria.index', [
            'registros' => $registros,
            'dominios'  => $dominios,
            'roles'     => $roles,
            'acciones'  => $acciones,
            'f'         => $f,
        ]);
    }

    /**
     * Consulta compartida por la pantalla y (luego) la exportación a Excel/PDF.
     * Devuelve: [query, filtros ya saneados, dominios, roles, acciones].
     */
    private function consulta(Request $request): array
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
        ]);

        // Catálogos para los desplegables (y para validar lo que llega por URL)
        $dominios = DB::table('DOMINIO_AUD')->orderBy('COD_DOM')->get(['COD_DOM', 'DES_DOM']);
        $roles    = DB::table('ROL')->orderBy('DES_ROL')->get(['COD_ROL', 'DES_ROL']);
        $acciones = DB::table('AUDITORIA')->distinct()->orderBy('ACC_AUD')->pluck('ACC_AUD');

        // Filtros saneados: lo que no es válido se descarta (queda vacío)
        $criticidad = (string) $request->query('criticidad', '');
        $dominio    = (string) $request->query('dominio', '');
        $rol        = (string) $request->query('rol', '');
        $accion     = (string) $request->query('accion', '');

        $f = [
            'buscar'     => trim((string) $request->query('buscar', '')),
            'usuario'    => trim((string) $request->query('usuario', '')),
            'paciente'   => trim((string) $request->query('paciente', '')),
            'criticidad' => in_array($criticidad, self::CRITICIDADES, true) ? $criticidad : '',
            'dominio'    => $dominios->contains('COD_DOM', $dominio) ? $dominio : '',
            'rol'        => $roles->contains('COD_ROL', $rol) ? $rol : '',
            'accion'     => $acciones->contains($accion) ? $accion : '',
            'desde'      => (string) $request->query('desde', ''),
            'hasta'      => (string) $request->query('hasta', ''),
        ];

        $query = DB::table('AUDITORIA')
            ->join('USUARIO', 'AUDITORIA.COD_USR', '=', 'USUARIO.COD_USR')
            ->join('ROL', 'AUDITORIA.COD_ROL', '=', 'ROL.COD_ROL')
            ->join('DOMINIO_AUD', 'AUDITORIA.COD_DOM', '=', 'DOMINIO_AUD.COD_DOM')
            ->leftJoin('PACIENTE', 'AUDITORIA.COD_PAC', '=', 'PACIENTE.COD_PAC')
            ->select(
                'AUDITORIA.*',
                'USUARIO.USR_USR',
                'ROL.DES_ROL',
                'DOMINIO_AUD.DES_DOM',
                'PACIENTE.NOM_PAC',
                'PACIENTE.APE_PAC',
                'PACIENTE.CI_PAC'
            )
            // Texto libre: detalle, registro o código del evento
            ->when($f['buscar'] !== '', function ($q) use ($f) {
                $q->where(function ($q) use ($f) {
                    $q->where('AUDITORIA.DET_AUD', 'like', "%{$f['buscar']}%")
                      ->orWhere('AUDITORIA.REG_AUD', 'like', "%{$f['buscar']}%")
                      ->orWhere('AUDITORIA.COD_AUD', 'like', "%{$f['buscar']}%");
                });
            })
            // Usuario que realizó la acción
            ->when($f['usuario'] !== '', fn ($q) => $q->where('USUARIO.USR_USR', 'like', "%{$f['usuario']}%"))
            // Paciente/expediente afectado: nombre, apellido, CI, código o nro. de historia
            ->when($f['paciente'] !== '', function ($q) use ($f) {
                $q->where(function ($q) use ($f) {
                    $q->where('PACIENTE.CI_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('PACIENTE.NOM_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('PACIENTE.APE_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhere('AUDITORIA.COD_PAC', 'like', "%{$f['paciente']}%")
                      ->orWhereExists(function ($sub) use ($f) {
                          $sub->select(DB::raw(1))
                              ->from('HISTORIA_CLINICA')
                              ->whereColumn('HISTORIA_CLINICA.COD_PAC', 'AUDITORIA.COD_PAC')
                              ->where('HISTORIA_CLINICA.NRO_HIS', 'like', "%{$f['paciente']}%");
                      });
                });
            })
            ->when($f['criticidad'] !== '', fn ($q) => $q->where('AUDITORIA.CRI_AUD', $f['criticidad']))
            ->when($f['dominio'] !== '', fn ($q) => $q->where('AUDITORIA.COD_DOM', $f['dominio']))
            ->when($f['rol'] !== '', fn ($q) => $q->where('AUDITORIA.COD_ROL', $f['rol']))
            ->when($f['accion'] !== '', fn ($q) => $q->where('AUDITORIA.ACC_AUD', $f['accion']))
            ->when($f['desde'] !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '>=', $f['desde'] . ' 00:00:00'))
            ->when($f['hasta'] !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '<=', $f['hasta'] . ' 23:59:59'))
            ->orderByDesc('AUDITORIA.id');

        return [$query, $f, $dominios, $roles, $acciones];
    }
}