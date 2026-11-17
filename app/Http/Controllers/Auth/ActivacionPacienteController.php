<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ActivacionPacienteController extends Controller
{
    public function enviar(string $paciente): RedirectResponse
    {
        $p = DB::table('PACIENTE')->where('COD_PAC', $paciente)->first();
        abort_unless($p, 404);
        if (DB::table('USUARIO')->where('COD_PAC', $paciente)->exists()) {
            return back()->withErrors(['activacion' => 'Esta ficha ya tiene una cuenta. Si necesita acceso, usa recuperación de contraseña o consulta a administración.']);
        }
        if (DB::table('USUARIO')->where('USR_USR', $p->CI_PAC)->exists()) {
            return back()->withErrors(['activacion' => 'No se puede activar: el CI ya está asociado a otro usuario. Contacta a administración.']);
        }
        if (!filter_var($p->COR_PAC, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['activacion' => 'La ficha no tiene un correo válido. Corrígelo antes de solicitar la activación.']);
        }
        if (!app()->environment('testing') && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['activacion' => 'El correo está configurado solo para pruebas y no puede entregar el enlace. Configura un servicio de correo antes de continuar.']);
        }
        $enlace = URL::temporarySignedRoute('paciente.activacion.form', now()->addMinutes(30), [
            'paciente' => $paciente, 'h' => $this->huella($p),
        ]);
        try {
            Mail::send('emails.activacion-paciente', ['enlace' => $enlace, 'nombre' => $p->NOM_PAC], function ($mensaje) use ($p) {
                $mensaje->to($p->COR_PAC)->subject('Clínica CIES: activa tu cuenta de paciente');
            });
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el enlace de activación de paciente.', ['error' => $e->getMessage()]);
            return back()->withErrors(['activacion' => 'No se pudo enviar el enlace. Comprueba el servicio de correo e intenta nuevamente.']);
        }
        Auditoria::registrar('Enviar activacion', 'Media', 'DOM01', 'PACIENTE', $paciente, 'Enlace de activación enviado al correo registrado', codPac: $paciente);
        return back()->with('ok', 'Enlace de activación enviado al correo registrado. El paciente tiene 30 minutos para elegir su contraseña.');
    }

    public function form(Request $request, string $paciente): View|RedirectResponse
    {
        $p = $this->fichaValida($request, $paciente);
        if (!$p) {
            return redirect()->route('login')->withErrors(['usuario' => 'El enlace ya se usó o no es válido. Solicita uno nuevo en recepción.']);
        }
        return view('auth.activar-paciente', ['accion' => $request->fullUrl()]);
    }

    public function activar(Request $request, string $paciente): RedirectResponse
    {
        $d = $request->validate([
            'clave' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'privacidad' => ['accepted'], 'terminos' => ['accepted'],
        ]);
        $activada = DB::transaction(function () use ($request, $paciente, $d) {
            $p = DB::table('PACIENTE')->where('COD_PAC', $paciente)->lockForUpdate()->first();
            if (!$p || !$this->coincide($request, $p) || DB::table('USUARIO')->where('COD_PAC', $paciente)->exists()
                || DB::table('USUARIO')->where('USR_USR', $p->CI_PAC)->exists()) {
                return false;
            }
            do {
                $codigo = 'USR'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            } while (DB::table('USUARIO')->where('COD_USR', $codigo)->exists());
            DB::table('USUARIO')->insert([
                'COD_USR' => $codigo, 'USR_USR' => $p->CI_PAC, 'CON_USR' => Hash::make($d['clave']),
                'EST_USR' => 'Activo', 'INTFAL_USR' => 0, 'CAMCON_USR' => 0, 'COD_ROL' => 'ROL006',
                'COD_EMP' => null, 'COD_PAC' => $paciente,
            ]);
            $actor = DB::table('USUARIO')->where('COD_USR', $codigo)->first();
            Auditoria::registrar('Activar cuenta', 'Media', 'DOM01', 'USUARIO', $codigo, 'Cuenta de paciente activada con enlace de correo y aceptación de privacidad y términos', $actor, $paciente);
            return true;
        });
        if (!$activada) {
            return redirect()->route('login')->withErrors(['usuario' => 'El enlace ya se usó o no es válido. Solicita ayuda en recepción.']);
        }
        return redirect()->route('login')->with('ok', 'Cuenta activada. Inicia sesión con tu CI y la contraseña que elegiste.');
    }

    private function fichaValida(Request $request, string $codigo): ?object
    {
        $p = DB::table('PACIENTE')->where('COD_PAC', $codigo)->first();
        if (!$p || !$this->coincide($request, $p) || DB::table('USUARIO')->where('COD_PAC', $codigo)->exists()
            || DB::table('USUARIO')->where('USR_USR', $p->CI_PAC)->exists()) {
            return null;
        }
        return $p;
    }

    private function coincide(Request $request, object $paciente): bool
    {
        return hash_equals($this->huella($paciente), (string) $request->query('h', ''));
    }

    private function huella(object $paciente): string
    {
        return hash_hmac('sha256', $paciente->COD_PAC.'|'.$paciente->CI_PAC.'|'.mb_strtolower(trim($paciente->COR_PAC)), (string) config('app.key'));
    }
}
