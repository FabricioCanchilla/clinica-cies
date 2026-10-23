<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));

        $usuarios = DB::table('USUARIO')
            ->join('ROL', 'USUARIO.COD_ROL', '=', 'ROL.COD_ROL')
            ->leftJoin('EMPLEADO', 'USUARIO.COD_EMP', '=', 'EMPLEADO.COD_EMP')
            ->leftJoin('PACIENTE', 'USUARIO.COD_PAC', '=', 'PACIENTE.COD_PAC')
            ->select(
                'USUARIO.COD_USR',
                'USUARIO.USR_USR',
                'USUARIO.EST_USR',
                'USUARIO.INTFAL_USR',
                'USUARIO.FECULT_USR',
                'ROL.DES_ROL',
                DB::raw("COALESCE(
                    CONCAT(EMPLEADO.NOM_EMP, ' ', EMPLEADO.APE_EMP),
                    CONCAT(PACIENTE.NOM_PAC, ' ', PACIENTE.APE_PAC)
                ) AS PERSONA")
            )
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('USUARIO.USR_USR', 'like', "%{$buscar}%")
                      ->orWhere('EMPLEADO.NOM_EMP', 'like', "%{$buscar}%")
                      ->orWhere('EMPLEADO.APE_EMP', 'like', "%{$buscar}%")
                      ->orWhere('PACIENTE.NOM_PAC', 'like', "%{$buscar}%")
                      ->orWhere('PACIENTE.APE_PAC', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('USUARIO.id')
            ->get();

        return view('admin.usuarios.index', compact('usuarios', 'buscar'));
    }
}