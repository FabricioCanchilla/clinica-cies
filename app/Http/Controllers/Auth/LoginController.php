<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $user = Usuario::where('USR_USR', $request->usuario)->first();

        // Mismo mensaje si el usuario no existe o la clave es mala,
        // así nadie puede averiguar qué usuarios existen
        $errorGenerico = ['usuario' => 'Usuario o contraseña incorrectos.'];

        if (!$user) {
            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        if ($user->EST_USR !== 'Activo') {
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

            return back()->withErrors($errorGenerico)->onlyInput('usuario');
        }

        // Login correcto: reiniciar intentos y registrar último acceso
        $user->INTFAL_USR = 0;
        $user->FECULT_USR = now();
        $user->save();

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