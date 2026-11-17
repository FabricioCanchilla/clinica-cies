<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\Auditoria;
use App\Services\OtpClient;
use DomainException;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 3;

    /** RN05: roles con segundo factor obligatorio (Administrador, Caja, Auditor). */
    private const ROLES_2FA = ['ROL001', 'ROL005', 'ROL009'];

    public function __construct(private OtpClient $otp) {}

    public function showForm()
    {
        // RF001: el Dashboard público muestra las especialidades registradas
        $especialidades = \Illuminate\Support\Facades\DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get(['DES_ESP']);

        return view('auth.login', compact('especialidades'));
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

        // Contraseña correcta
        $user->INTFAL_USR = 0;
        $user->save();

        // RN05: Administrador, Auditor y Caja validan además un OTP enviado a su correo
        if (in_array(trim($user->COD_ROL), self::ROLES_2FA, true)) {
            return $this->iniciarSegundoFactor($request, $user);
        }

        return $this->completarLogin($request, $user);
    }

    /** RN05 / RN12: genera el OTP de inicio de sesión y pide el código. */
    private function iniciarSegundoFactor(Request $request, Usuario $user)
    {
        $correo = DB::table('EMPLEADO')->where('COD_EMP', $user->COD_EMP)->value('COR_EMP');
        if (!$correo) {
            return back()->withErrors(['usuario' => 'La cuenta no tiene un correo registrado para el segundo factor. Contacta al administrador.'])->onlyInput('usuario');
        }

        try {
            $r = $this->otp->generar($user->COD_USR, 'INICIO_SESION', 'CORREO', $correo);
        } catch (DomainException $e) {
            // RN13: sin OTP no se confirma el ingreso
            Auditoria::registrar('Fallo envio OTP', 'Media', 'DOM02', 'OTP', $user->COD_USR,
                mb_substr('Segundo factor: ' . $e->getMessage(), 0, 255), $user);
            return back()->withErrors(['usuario' => $e->getMessage()])->onlyInput('usuario');
        }

        $request->session()->put('login_2fa', [
            'codUsr' => $user->COD_USR,
            'codOtp' => $r['codOtp'],
            'correo' => $correo,
        ]);

        return redirect()->route('login.2fa');
    }

    public function form2fa(Request $request)
    {
        $pend = $request->session()->get('login_2fa');
        if (!$pend) {
            return redirect()->route('login');
        }

        return view('auth.segundo-factor', ['correo' => $this->enmascarar($pend['correo'])]);
    }

    public function verificar2fa(Request $request)
    {
        $pend = $request->session()->get('login_2fa');
        if (!$pend) {
            return redirect()->route('login');
        }

        $request->validate(['codigo' => ['required', 'digits:6']], [
            'codigo.required' => 'Escribe el código de 6 dígitos.',
            'codigo.digits'   => 'El código tiene 6 dígitos.',
        ]);

        $user = Usuario::where('COD_USR', $pend['codUsr'])->first();
        if (!$user || $user->EST_USR !== 'Activo') {
            $request->session()->forget('login_2fa');
            return redirect()->route('login')->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        try {
            $res = $this->otp->validar($pend['codOtp'], $request->codigo);
        } catch (DomainException $e) {
            return back()->withErrors(['codigo' => $e->getMessage()]);
        }

        if (!$res['valido']) {
            Auditoria::registrar('OTP login fallido', 'Media', 'DOM02', 'OTP', $user->COD_USR,
                "Código incorrecto en el segundo factor de {$user->USR_USR}", $user);
            return back()->withErrors(['codigo' => OtpClient::mensajeMotivo($res)]);
        }

        $request->session()->forget('login_2fa');

        return $this->completarLogin($request, $user, true);
    }

    public function reenviar2fa(Request $request)
    {
        $pend = $request->session()->get('login_2fa');
        $user = $pend ? Usuario::where('COD_USR', $pend['codUsr'])->first() : null;
        if (!$user) {
            return redirect()->route('login');
        }

        try {
            $r = $this->otp->generar($user->COD_USR, 'INICIO_SESION', 'CORREO', $pend['correo']);
        } catch (DomainException $e) {
            return back()->withErrors(['codigo' => $e->getMessage()]);
        }

        $pend['codOtp'] = $r['codOtp'];
        $request->session()->put('login_2fa', $pend);

        return back()->with('ok', 'Te enviamos un código nuevo.');
    }

    private function completarLogin(Request $request, Usuario $user, bool $con2fa = false)
    {
        $user->FECULT_USR = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        Auditoria::registrar(
            'Login', 'Baja', 'DOM02', 'USUARIO', $user->COD_USR,
            "Inicio de sesión de {$user->USR_USR}" . ($con2fa ? ' con segundo factor OTP' : '')
        );

        return redirect()->route('panel');
    }

    private function enmascarar(string $correo): string
    {
        [$u, $d] = array_pad(explode('@', $correo, 2), 2, '');
        return mb_substr($u, 0, 2) . str_repeat('*', max(1, mb_strlen($u) - 2)) . '@' . $d;
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