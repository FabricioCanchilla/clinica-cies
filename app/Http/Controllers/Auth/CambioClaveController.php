<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CambioClaveController extends Controller
{
    public function form()
    {
        return view('auth.cambiar-clave', [
            'obligatorio' => (bool) Auth::user()->CAMCON_USR,
        ]);
    }

    public function actualizar(Request $request)
    {
        $request->validate([
            'actual' => 'required|string',
            'nueva'  => 'required|string|min:8|confirmed|different:actual',
        ], [
            'actual.required'  => 'Escribe tu contraseña actual.',
            'nueva.required'   => 'Escribe la nueva contraseña.',
            'nueva.min'        => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'nueva.confirmed'  => 'La confirmación no coincide con la nueva contraseña.',
            'nueva.different'  => 'La nueva contraseña debe ser distinta de la actual.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->actual, $user->CON_USR)) {
            return back()->withErrors(['actual' => 'La contraseña actual no es correcta.']);
        }

        DB::transaction(function () use ($user, $request) {
            DB::table('USUARIO')->where('COD_USR', $user->COD_USR)->update([
                'CON_USR'    => Hash::make($request->nueva),
                'CAMCON_USR' => 0,
            ]);

            Auditoria::registrar(
                'Actualizar', 'Media', 'DOM01', 'USUARIO', $user->COD_USR,
                "Cambio de contraseña de {$user->USR_USR}"
            );
        });

        return redirect()->route('panel')->with('ok', 'Contraseña actualizada.');
    }
}