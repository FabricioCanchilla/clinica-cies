<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * RF005 / RN04 / RNF002: recuperación de usuario y contraseña.
 * - Enlace temporal enviado al correo registrado, válido por 30 minutos (URL firmada).
 * - De un solo uso: el enlace lleva una huella de la contraseña actual; al cambiarla, deja de servir.
 * - La respuesta en pantalla es la misma exista o no la cuenta.
 * No usa tablas adicionales: el esquema documentado no tiene una tabla de tokens.
 */
class RecuperacionController extends Controller
{
    private const MINUTOS = 30;
    private const MENSAJE = 'Si el correo está registrado, te enviamos un enlace para recuperar tu usuario y contraseña. Vence en 30 minutos.';

    public function form()
    {
        return view('auth.forgot-password');
    }

    public function enviar(Request $request)
    {
        $request->validate(['correo' => 'required|email|max:60'], [], ['correo' => 'correo electrónico']);
        $correo = trim($request->correo);

        $cuentas = Usuario::query()
            ->leftJoin('EMPLEADO as e', 'e.COD_EMP', '=', 'USUARIO.COD_EMP')
            ->leftJoin('PACIENTE as p', 'p.COD_PAC', '=', 'USUARIO.COD_PAC')
            ->where(fn ($q) => $q->where('e.COR_EMP', $correo)->orWhere('p.COR_PAC', $correo))
            ->select('USUARIO.*')
            ->get();

        $activas = $cuentas->where('EST_USR', 'Activo');

        if ($activas->isNotEmpty()) {
            $enlaces = $activas->map(fn ($u) => [
                'usuario' => $u->USR_USR,
                'url'     => URL::temporarySignedRoute('password.reset', now()->addMinutes(self::MINUTOS),
                    ['codUsr' => $u->COD_USR, 'h' => self::huella($u)]),
            ])->values()->all();

            try {
                Mail::send('emails.recuperacion', ['enlaces' => $enlaces, 'minutos' => self::MINUTOS], function ($m) use ($correo) {
                    $m->to($correo)->subject('Clínica CIES: recuperación de usuario y contraseña');
                });
            } catch (Throwable $e) {
                Log::channel('cies')->error('No se pudo enviar el correo de recuperación: ' . $e->getMessage());
            }
        }

        foreach ($cuentas as $u) {
            Auditoria::registrar('Recuperar clave', 'Media', 'DOM02', 'USUARIO', $u->COD_USR,
                $u->EST_USR === 'Activo'
                    ? "Enlace de recuperación enviado al correo registrado de {$u->USR_USR}"
                    : "Solicitud de recuperación para la cuenta " . strtolower($u->EST_USR) . " {$u->USR_USR}: no se envió enlace (RN02)",
                $u);
        }
        if ($cuentas->isEmpty()) {
            Log::info('Recuperación solicitada para un correo no registrado', ['ip' => $request->ip()]);
        }

        // RN04: misma respuesta exista o no la cuenta
        return back()->with('ok', self::MENSAJE);
    }

    public function formReset(Request $request, string $codUsr)
    {
        $u = $this->cuentaDelEnlace($request, $codUsr);
        if (!$u) {
            return redirect()->route('password.request')
                ->withErrors(['correo' => 'El enlace ya se usó o no es válido. Solicita uno nuevo.']);
        }

        return view('auth.reset-password', ['usuario' => $u->USR_USR, 'accion' => $request->fullUrl()]);
    }

    public function reset(Request $request, string $codUsr)
    {
        $u = $this->cuentaDelEnlace($request, $codUsr);
        if (!$u) {
            return redirect()->route('password.request')
                ->withErrors(['correo' => 'El enlace ya se usó o no es válido. Solicita uno nuevo.']);
        }

        $request->validate([
            'clave' => 'required|string|min:8|max:72|confirmed',
        ], [], ['clave' => 'nueva contraseña']);

        DB::table('USUARIO')->where('COD_USR', $u->COD_USR)->update([
            'CON_USR'    => Hash::make($request->clave),   // RN03: bcrypt
            'INTFAL_USR' => 0,
            'CAMCON_USR' => 0,
        ]);

        Auditoria::registrar('Restablecer clave', 'Alta', 'DOM02', 'USUARIO', $u->COD_USR,
            "Contraseña de {$u->USR_USR} restablecida con el enlace enviado al correo", $u);

        return redirect()->route('login')->with('ok', 'Tu contraseña se actualizó. Ya puedes ingresar.');
    }

    /** La firma (y su vencimiento) la valida el middleware 'signed'; aquí se valida la huella de un solo uso. */
    private function cuentaDelEnlace(Request $request, string $codUsr): ?Usuario
    {
        $u = Usuario::where('COD_USR', $codUsr)->first();
        if (!$u || $u->EST_USR !== 'Activo' || !hash_equals(self::huella($u), (string) $request->query('h'))) {
            return null;
        }
        return $u;
    }

    private static function huella(Usuario $u): string
    {
        return substr(hash_hmac('sha256', $u->COD_USR . '|' . $u->CON_USR, (string) config('app.key')), 0, 32);
    }
}
