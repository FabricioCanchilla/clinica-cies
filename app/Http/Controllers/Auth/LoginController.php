<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\Auditoria;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 3;

    public function showForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'usuario'    => 'required|string',
            'contrasena' => 'required|string',
        ]);

        $input = trim($request->usuario);

        // Buscamos únicamente por el nombre de usuario
        $user = Usuario::where('USR_USR', $input)->first();

        $errorGenerico = ['usuario' => 'Usuario o contraseña incorrectos.'];

        if (!$user) {
            // No se puede guardar en AUDITORIA (COD_USR es NOT NULL con FK): queda en el log de Laravel
            Log::warning('Login fallido con usuario inexistente', [
                'usuario' => mb_substr($input, 0, 50),
                'ip'      => $request->ip(),
            ]);

            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        if ($user->EST_USR !== 'Activo') {
            Auditoria::registrar(
                'Login rechazado', 'Media', 'DOM02', 'USUARIO', $user->COD_USR,
                "Intento de ingreso de {$user->USR_USR} con cuenta " . strtolower($user->EST_USR),
                $user
            );

            return back()->withErrors([
                'usuario' => 'Cuenta ' . strtolower($user->EST_USR) . '. Contacta al administrador.',
            ])->onlyInput('usuario');
        }

        if (!Hash::check($request->contrasena, $user->CON_USR)) {
            $user->INTFAL_USR++;

            if ($user->INTFAL_USR >= self::MAX_INTENTOS) {
                $user->EST_USR = 'Bloqueado';
            }
            $user->save();

            Auditoria::registrar(
                'Login fallido', 'Media', 'DOM02', 'USUARIO', $user->COD_USR,
                "Contraseña incorrecta para {$user->USR_USR} (intento {$user->INTFAL_USR} de " . self::MAX_INTENTOS . ')',
                $user
            );

            if ($user->EST_USR === 'Bloqueado') {
                Auditoria::registrar(
                    'Bloqueo automático', 'Crítica', 'DOM02', 'USUARIO', $user->COD_USR,
                    "Bloqueo automático de {$user->USR_USR} por " . self::MAX_INTENTOS . ' intentos fallidos',
                    $user
                );
            }

            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        // Login correcto
        $user->INTFAL_USR = 0;
        $user->FECULT_USR = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        Auditoria::registrar(
            'Login', 'Baja', 'DOM02', 'USUARIO', $user->COD_USR,
            "Inicio de sesión de {$user->USR_USR}"
        );

        return redirect()->route('panel');
    }

    public function logout(Request $request)
    {
        // Se audita ANTES de cerrar la sesión, si no Auth::user() ya es null
        if ($usuario = Auth::user()) {
            Auditoria::registrar(
                'Logout', 'Baja', 'DOM02', 'USUARIO', $usuario->COD_USR,
                "Cierre de sesión de {$usuario->USR_USR}"
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}