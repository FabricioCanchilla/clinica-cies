<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Auditoria;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

            DB::transaction(function () use ($codUsr, $usuario) {
                DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
                    'EST_USR' => 'Bloqueado',
                ]);
                Auditoria::registrar(
                    'Actualizar', 'Alta', 'DOM01', 'USUARIO', $codUsr,
                    "Bloqueo manual de la cuenta {$usuario->USR_USR}"
                );
            });

            return back()->with('ok', "Usuario {$usuario->USR_USR} bloqueado.");
        }

        // desbloquear
        if ($usuario->EST_USR !== 'Bloqueado') {
            return back()->withErrors(['usuario' => 'Solo se puede desbloquear una cuenta bloqueada.']);
        }

        DB::transaction(function () use ($codUsr, $usuario) {
            DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
                'EST_USR'    => 'Activo',
                'INTFAL_USR' => 0,
            ]);
            Auditoria::registrar(
                'Actualizar', 'Media', 'DOM01', 'USUARIO', $codUsr,
                "Desbloqueo de la cuenta {$usuario->USR_USR}"
            );
        });

        return back()->with('ok', "Usuario {$usuario->USR_USR} desbloqueado.");
    }
        public function create()
    {
        // Solo empleados que todavía no tienen usuario
        $empleados = DB::table('EMPLEADO')
            ->leftJoin('USUARIO', 'EMPLEADO.COD_EMP', '=', 'USUARIO.COD_EMP')
            ->whereNull('USUARIO.COD_EMP')
            ->select('EMPLEADO.COD_EMP', 'EMPLEADO.NOM_EMP', 'EMPLEADO.APE_EMP', 'EMPLEADO.CI_EMP')
            ->orderBy('EMPLEADO.APE_EMP')
            ->get();

        // Paciente (ROL006) no se crea aquí: los pacientes se registran solos
        $roles = DB::table('ROL')
            ->where('COD_ROL', '<>', 'ROL006')
            ->orderBy('COD_ROL')
            ->get();

        return view('admin.usuarios.crear', compact('empleados', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'usuario'  => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:USUARIO,USR_USR'],
            'empleado' => 'required|string|exists:EMPLEADO,COD_EMP',
            'rol'      => 'required|string|exists:ROL,COD_ROL',
            'clave'    => 'required|string|min:8|max:72|confirmed',
        ], [
            'usuario.required'  => 'Escribe el nombre de usuario.',
            'usuario.min'       => 'El usuario debe tener al menos 4 caracteres.',
            'usuario.max'       => 'El usuario puede tener como máximo 20 caracteres.',
            'usuario.regex'     => 'El usuario solo puede llevar letras, números, punto, guion y guion bajo.',
            'usuario.unique'    => 'Ese nombre de usuario ya existe.',
            'empleado.required' => 'Selecciona un empleado.',
            'empleado.exists'   => 'El empleado seleccionado no existe.',
            'rol.required'      => 'Selecciona un rol.',
            'rol.exists'        => 'El rol seleccionado no existe.',
            'clave.required'    => 'Escribe la contraseña temporal.',
            'clave.min'         => 'La contraseña temporal debe tener al menos 8 caracteres.',
            'clave.confirmed'   => 'La confirmación no coincide con la contraseña.',
        ]);

        // Reglas que no se pueden dejar solo en el formulario
        if ($request->rol === 'ROL006') {
            return back()->withErrors(['rol' => 'Los pacientes se registran solos; no se crean desde aquí.'])->withInput();
        }

        if (DB::table('USUARIO')->where('COD_EMP', $request->empleado)->exists()) {
            return back()->withErrors(['empleado' => 'Ese empleado ya tiene un usuario.'])->withInput();
        }

        $rol = DB::table('ROL')->where('COD_ROL', $request->rol)->first();

        DB::transaction(function () use ($request, $rol) {
            $siguiente = (int) DB::table('USUARIO')
                ->max(DB::raw('CAST(SUBSTRING(COD_USR, 4) AS UNSIGNED)')) + 1;
            $codUsr = sprintf('USR%05d', $siguiente);

            DB::table('USUARIO')->insert([
                'COD_USR'    => $codUsr,
                'USR_USR'    => $request->usuario,
                'CON_USR'    => Hash::make($request->clave),
                'EST_USR'    => 'Activo',
                'INTFAL_USR' => 0,
                'CAMCON_USR' => 1, // debe cambiar la contraseña en su primer ingreso
                'COD_ROL'    => $request->rol,
                'COD_EMP'    => $request->empleado,
                'COD_PAC'    => null,
            ]);

            Auditoria::registrar(
                'Crear', 'Alta', 'DOM01', 'USUARIO', $codUsr,
                "Alta del usuario {$request->usuario} con rol {$rol->DES_ROL}"
            );
        });

        return redirect()->route('admin.usuarios')->with('ok', "Usuario {$request->usuario} creado. Deberá cambiar su contraseña al ingresar.");
    }
    public function edit(string $codUsr)
{
    $usuario = DB::table('USUARIO')->where('COD_USR', $codUsr)->first();
    abort_if(!$usuario, 404);

    $roles = DB::table('ROL')
        ->where('COD_ROL', '!=', 'ROL006')
        ->orderBy('COD_ROL')
        ->get();

    $esPropio   = $usuario->COD_USR === auth()->user()->COD_USR;
    $esPaciente = $usuario->COD_ROL === 'ROL006';

    return view('admin.usuarios.editar', compact('usuario', 'roles', 'esPropio', 'esPaciente'));
}

public function update(Request $request, string $codUsr)
{
    $usuario = DB::table('USUARIO')->where('COD_USR', $codUsr)->first();
    abort_if(!$usuario, 404);

    $esPropio   = $usuario->COD_USR === auth()->user()->COD_USR;
    $esPaciente = $usuario->COD_ROL === 'ROL006';
    $puedeCambiarRol = !$esPropio && !$esPaciente;

    $reglas = [
        'usuario' => [
            'required', 'string', 'min:4', 'max:20',
            'regex:/^[A-Za-z0-9._-]+$/',
            Rule::unique('USUARIO', 'USR_USR')->ignore($codUsr, 'COD_USR'),
        ],
    ];

    if ($puedeCambiarRol) {
        $reglas['rol'] = [
            'required',
            Rule::exists('ROL', 'COD_ROL'),
            Rule::notIn(['ROL006']),
        ];
    }

    $datos = $request->validate($reglas, [
        'usuario.required' => 'El nombre de usuario es obligatorio.',
        'usuario.min'      => 'El usuario debe tener al menos 4 caracteres.',
        'usuario.max'      => 'El usuario no puede pasar de 20 caracteres.',
        'usuario.regex'    => 'Solo letras, números, punto, guion y guion bajo.',
        'usuario.unique'   => 'Ese nombre de usuario ya está en uso.',
        'rol.required'     => 'Selecciona un rol.',
        'rol.exists'       => 'El rol seleccionado no existe.',
        'rol.not_in'       => 'No se puede asignar ese rol a personal.',
    ]);

    $nuevoUsuario = $datos['usuario'];
    $nuevoRol     = $puedeCambiarRol ? $datos['rol'] : $usuario->COD_ROL;

    $cambioUsuario = $nuevoUsuario !== $usuario->USR_USR;
    $cambioRol     = $nuevoRol !== $usuario->COD_ROL;

    if (!$cambioUsuario && !$cambioRol) {
        return redirect()->route('admin.usuarios')->with('ok', 'No hubo cambios que guardar.');
    }

    DB::transaction(function () use ($codUsr, $usuario, $nuevoUsuario, $nuevoRol, $cambioUsuario, $cambioRol) {
        DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
            'USR_USR' => $nuevoUsuario,
            'COD_ROL' => $nuevoRol,
        ]);

        $partes = [];
        if ($cambioUsuario) {
            $partes[] = "Usuario: {$usuario->USR_USR} -> {$nuevoUsuario}";
        }
        if ($cambioRol) {
            $partes[] = "Rol: {$usuario->COD_ROL} -> {$nuevoRol}";
        }

        $tocaAdmin = $cambioRol && ($usuario->COD_ROL === 'ROL001' || $nuevoRol === 'ROL001');
        $criticidad = $tocaAdmin ? 'Alta' : 'Media';

        Auditoria::registrar(
            'Actualizar',
            $criticidad,
            'DOM01',
            'USUARIO',
            $codUsr,
            mb_substr(implode('; ', $partes), 0, 255)
        );
    });

    return redirect()->route('admin.usuarios')->with('ok', 'Usuario actualizado correctamente.');
}
    public function resetClave(string $codUsr)
    {
        $usuario = DB::table('USUARIO')->where('COD_USR', $codUsr)->first();
        abort_if(!$usuario, 404);

        // Un admin no puede restablecer su propia clave desde aquí
        if ($usuario->COD_USR === Auth::user()->COD_USR) {
            return back()->withErrors(['usuario' => 'No puedes restablecer tu propia contraseña desde aquí.']);
        }

        if ($usuario->EST_USR === 'Inactivo') {
            return back()->withErrors(['usuario' => 'No se puede restablecer la clave de una cuenta inactiva.']);
        }

        // Clave temporal de 10 caracteres, sin símbolos que se confundan (0/O, 1/l/I)
        $alfabeto = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $clave = '';
        for ($i = 0; $i < 10; $i++) {
            $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        $estabaBloqueado = $usuario->EST_USR === 'Bloqueado';

        DB::transaction(function () use ($codUsr, $usuario, $clave, $estabaBloqueado) {
            DB::table('USUARIO')->where('COD_USR', $codUsr)->update([
                'CON_USR'    => Hash::make($clave),
                'CAMCON_USR' => 1,
                'EST_USR'    => 'Activo',
                'INTFAL_USR' => 0,
            ]);

            Auditoria::registrar(
                'Actualizar', 'Alta', 'DOM01', 'USUARIO', $codUsr,
                "Restablecimiento de contraseña de {$usuario->USR_USR}" . ($estabaBloqueado ? ' (cuenta desbloqueada)' : '')
            );
        });

        return back()->with('ok', "Clave temporal de {$usuario->USR_USR}: {$clave} — anótala, no se vuelve a mostrar. Deberá cambiarla al ingresar.");
    }
}