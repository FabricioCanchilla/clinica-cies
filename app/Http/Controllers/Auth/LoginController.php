<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        // 1. Validar los datos del formulario (Sintaxis correcta en inglés)
        $request->validate([
            'usuario'    => 'required|string',
            'contrasena' => 'required|string',
        ]);

        $input = trim($request->usuario);

        // 2. Buscar al usuario SOLO por la columna USR_USR 
        // (Ya que sabemos que la columna existe por el error anterior)
        $user = Usuario::where('USR_USR', $input)->first();

        $errorGenerico = ['usuario' => 'Usuario o contraseña incorrectos.'];

        // 3. Verificar si el usuario existe
        if (!$user) {
            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        // 4. Verificar si la cuenta está activa
        if ($user->EST_USR !== 'Activo') {
            return back()->withErrors([
                'usuario' => 'Cuenta ' . strtolower($user->EST_USR) . '. Contacta al administrador.',
            ])->onlyInput('usuario');
        }

        // 5. Verificar la contraseña
        if (!Hash::check($request->contrasena, $user->CON_USR)) {
            $user->INTFAL_USR++;

            if ($user->INTFAL_USR >= self::MAX_INTENTOS) {
                $user->EST_USR = 'Bloqueado';
            }
            $user->save();

            if ($user->EST_USR === 'Bloqueado') {
                Auditoria::registrar(
                    'Actualizar', 'Alta', 'DOM01', 'USUARIO', $user->COD_USR,
                    "Bloqueo automático de {$user->USR_USR} por " . self::MAX_INTENTOS . ' intentos fallidos',
                    $user
                );
            }

            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        // 6. Login correcto: reiniciar intentos y registrar último acceso
        $user->INTFAL_USR = 0;
        $user->FECULT_USR = now();
        $user->save();

        // 7. Autenticar al usuario y regenerar la sesión
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}