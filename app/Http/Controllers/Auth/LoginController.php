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

        // RN01: usuario, correo, CI o código de paciente
        $user = $this->buscarCuenta($input);

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
            // RN01: con una contraseña incorrecta el mensaje es el genérico, así no se revela el estado de la cuenta
            if (!Hash::check($request->contrasena, $user->CON_USR)) {
                Auditoria::registrar(
                    'Login rechazado', 'Media', 'DOM02', 'USUARIO', $user->COD_USR,
                    "Intento de ingreso con contraseña incorrecta a la cuenta " . strtolower($user->EST_USR) . " {$user->USR_USR}",
                    $user
                );
                return back()->withErrors($errorGenerico)->onlyInput('usuario');
            }

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

    /**
     * RN01: el dato de ingreso puede ser el nombre de usuario, el correo o el CI (del empleado o del
     * paciente) o el código de paciente. Si el dato coincide con más de una cuenta (por ejemplo, un
     * correo compartido), solo se acepta la coincidencia exacta por nombre de usuario.
     */
    private function buscarCuenta(string $dato): ?Usuario
    {
        if ($dato === '') {
            return null;
        }

        $porUsuario = Usuario::where('USR_USR', $dato)->first();
        if ($porUsuario) {
            return $porUsuario;
        }

        $ids = Usuario::query()
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'USUARIO.COD_EMP')
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'USUARIO.COD_PAC')
            ->where(function ($q) use ($dato) {
                $q->where('USUARIO.COD_PAC', $dato)
                  ->orWhere('p.CI_PAC', $dato)->orWhere('p.COR_PAC', $dato)
                  ->orWhere('e.CI_EMP', $dato)->orWhere('e.COR_EMP', $dato);
            })
            ->pluck('USUARIO.id')
            ->unique();

        if ($ids->count() !== 1) {
            if ($ids->count() > 1) {
                Log::warning('Login ambiguo: el dato coincide con varias cuentas', ['dato' => mb_substr($dato, 0, 50)]);
            }
            return null;
        }

        return Usuario::find($ids->first());
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