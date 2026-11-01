<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);

        $buscar     = trim($request->query('buscar', ''));
        $criticidad = $request->query('criticidad', '');
        $desde      = $request->query('desde', '');
        $hasta      = $request->query('hasta', '');

        $registros = DB::table('AUDITORIA')
            ->join('USUARIO', 'AUDITORIA.COD_USR', '=', 'USUARIO.COD_USR')
            ->join('ROL', 'AUDITORIA.COD_ROL', '=', 'ROL.COD_ROL')
            ->join('DOMINIO_AUD', 'AUDITORIA.COD_DOM', '=', 'DOMINIO_AUD.COD_DOM')
            ->select('AUDITORIA.*', 'USUARIO.USR_USR', 'ROL.DES_ROL', 'DOMINIO_AUD.DES_DOM')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('USUARIO.USR_USR', 'like', "%{$buscar}%")
                      ->orWhere('AUDITORIA.DET_AUD', 'like', "%{$buscar}%")
                      ->orWhere('AUDITORIA.REG_AUD', 'like', "%{$buscar}%")
                      ->orWhere('AUDITORIA.COD_AUD', 'like', "%{$buscar}%");
                });
            })
            ->when(
                in_array($criticidad, ['Baja', 'Media', 'Alta', 'Crítica'], true),
                fn ($q) => $q->where('AUDITORIA.CRI_AUD', $criticidad)
            )
            ->when($desde !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '>=', $desde . ' 00:00:00'))
            ->when($hasta !== '', fn ($q) => $q->where('AUDITORIA.FEC_AUD', '<=', $hasta . ' 23:59:59'))
            ->orderByDesc('AUDITORIA.id')
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.auditoria.index', compact('registros', 'buscar', 'criticidad', 'desde', 'hasta'));
    }
}