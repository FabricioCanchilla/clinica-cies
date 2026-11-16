<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * RF003 / RN07: registro de paciente nuevo desde el Dashboard público.
 * El CI identifica al paciente (no se crea otra ficha si ya existe), se exige aceptar
 * el aviso de privacidad y los términos de uso, y se crean la ficha PACIENTE y su
 * cuenta USUARIO con rol Paciente (ROL006). El nombre de usuario es el CI.
 */
class RegistroController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ci'          => ['required', 'regex:/^[0-9]{5,10}$/'],
            'nombres'     => ['required', 'string', 'max:40'],
            'apellidos'   => ['required', 'string', 'max:40'],
            'sexo'        => ['required', 'in:M,F'],
            'fecnac'      => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'estciv'      => ['required', 'in:Soltero,Casado,Divorciado,Viudo,Concubino'],
            'telefono'    => ['required', 'regex:/^[0-9]{7,8}$/'],
            'correo'      => ['required', 'email', 'max:60'],
            'direccion'   => ['required', 'string', 'max:100'],
            'clave'       => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'privacidad'  => ['accepted'],
            'terminos'    => ['accepted'],
        ], [
            'ci.required'        => 'Escribe tu número de CI.',
            'ci.regex'           => 'El CI debe tener entre 5 y 10 dígitos.',
            'nombres.required'   => 'Escribe tus nombres.',
            'apellidos.required' => 'Escribe tus apellidos.',
            'sexo.required'      => 'Selecciona el sexo.',
            'fecnac.required'    => 'Indica tu fecha de nacimiento.',
            'fecnac.before'      => 'La fecha de nacimiento debe ser anterior a hoy.',
            'estciv.required'    => 'Selecciona el estado civil.',
            'telefono.regex'     => 'El teléfono debe tener 7 u 8 dígitos.',
            'correo.email'       => 'El correo no es válido.',
            'direccion.required' => 'Escribe tu dirección.',
            'clave.min'          => 'La contraseña debe tener al menos 8 caracteres.',
            'clave.confirmed'    => 'La confirmación no coincide con la contraseña.',
            'privacidad.accepted'=> 'Debes aceptar el aviso de privacidad.',
            'terminos.accepted'  => 'Debes aceptar los términos de uso.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'registro')->withInput($request->except('clave', 'clave_confirmation'));
        }

        $ci = $request->ci;

        // RN07: antes de crear la ficha se busca por CI. Mensaje neutro para no confirmar datos de terceros.
        $existe = DB::table('PACIENTE')->where('CI_PAC', $ci)->exists()
            || DB::table('USUARIO')->where('USR_USR', $ci)->exists();
        if ($existe) {
            return back()->withErrors([
                'ci' => 'Ya existe una ficha con ese CI. Si es tuya, ingresa con tu CI o recupera tu contraseña.',
            ], 'registro')->withInput($request->except('clave', 'clave_confirmation'));
        }

        $codUsr = DB::transaction(function () use ($request, $ci) {
            $sigPac = (int) DB::table('PACIENTE')
                ->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(COD_PAC, 4) AS UNSIGNED)')) + 1;
            $codPac = sprintf('PAC%07d', $sigPac);

            $sigUsr = (int) DB::table('USUARIO')
                ->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(COD_USR, 4) AS UNSIGNED)')) + 1;
            $codUsr = sprintf('USR%05d', $sigUsr);

            DB::table('PACIENTE')->insert([
                'COD_PAC'    => $codPac,
                'CI_PAC'     => $ci,
                'NOM_PAC'    => trim($request->nombres),
                'APE_PAC'    => trim($request->apellidos),
                'SEX_PAC'    => $request->sexo,
                'FECNAC_PAC' => $request->fecnac,
                'ESTCIV_PAC' => $request->estciv,
                'TEL_PAC'    => $request->telefono,
                'COR_PAC'    => mb_strtolower(trim($request->correo)),
                'DIR_PAC'    => trim($request->direccion),
            ]);

            DB::table('USUARIO')->insert([
                'COD_USR'    => $codUsr,
                'USR_USR'    => $ci,
                'CON_USR'    => Hash::make($request->clave),
                'EST_USR'    => 'Activo',
                'INTFAL_USR' => 0,
                'CAMCON_USR' => 0, // la clave la eligió el propio paciente
                'COD_ROL'    => 'ROL006',
                'COD_EMP'    => null,
                'COD_PAC'    => $codPac,
            ]);

            $usuario = Usuario::where('COD_USR', $codUsr)->first();

            Auditoria::registrar(
                'Registro paciente', 'Baja', 'DOM01', 'PACIENTE', $codPac,
                "Autorregistro del paciente {$codPac} (CI {$ci}) con aceptación de privacidad y términos",
                $usuario, $codPac
            );

            return $codUsr;
        });

        $usuario = Usuario::where('COD_USR', $codUsr)->first();
        $usuario->FECULT_USR = now();
        $usuario->save();

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('panel')->with('ok', 'Cuenta creada. Tu usuario es tu número de CI.');
    }
}
