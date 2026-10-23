<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Auth;
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
        public function cambiarEstado(Request $request, string $codUsr)
    {
        $request->validate([
            'accion' => 'required|in:bloquear,desbloquear',
        ]);

        $usuario = DB::table('USUARIO')->where('COD_USR', $codUsr)->first();
        abort_if(!$usuario, 404);

        // Un admin no puede bloquearse a sí mismo
        if ($usuario->COD_USR === Auth::user()->COD_USR) {
            return back()->withErrors(['usuario' => 'No puedes cambiar el estado de tu propia cuenta.']);
        }

        if ($request->accion === 'bloquear') {
            if ($usuario->EST_USR !== 'Activo') {
                return back()->withErrors(['usuario' => 'Solo se puede bloquear una cuenta activa.']);
            }
            DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
                'EST_USR' => 'Bloqueado',
            ]);
            return back()->with('ok', "Usuario {$usuario->USR_USR} bloqueado.");
        }

        // desbloquear
        if ($usuario->EST_USR !== 'Bloqueado') {
            return back()->withErrors(['usuario' => 'Solo se puede desbloquear una cuenta bloqueada.']);
        }
        DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
            'EST_USR'    => 'Activo',
            'INTFAL_USR' => 0, // si no, la primera clave mala ya sumaría 4 intentos
        ]);
        return back()->with('ok', "Usuario {$usuario->USR_USR} desbloqueado.");
    }
}