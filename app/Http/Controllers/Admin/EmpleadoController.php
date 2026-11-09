<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmpleadoController extends Controller
{
    private const TITULOS = ['Dr', 'Dr.', 'Dra', 'Lic', 'Ing', 'Sr.', 'Sra'];
    private const TURNOS  = ['Mañana', 'Tarde', 'Noche'];

    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $cargo  = $request->query('cargo', '');

        $empleados = DB::table('EMPLEADO')
            ->join('CARGO', 'EMPLEADO.COD_CAR', '=', 'CARGO.COD_CAR')
            ->leftJoin('ESPECIALIDAD', 'EMPLEADO.COD_ESP', '=', 'ESPECIALIDAD.COD_ESP')
            ->leftJoin('USUARIO', 'EMPLEADO.COD_EMP', '=', 'USUARIO.COD_EMP')
            ->select(
                'EMPLEADO.COD_EMP', 'EMPLEADO.CI_EMP', 'EMPLEADO.NOM_EMP', 'EMPLEADO.APE_EMP',
                'EMPLEADO.TURNO_EMP', 'EMPLEADO.MAT_EMP',
                'CARGO.DES_CAR', 'ESPECIALIDAD.DES_ESP', 'USUARIO.USR_USR'
            )
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('EMPLEADO.NOM_EMP', 'like', "%{$buscar}%")
                      ->orWhere('EMPLEADO.APE_EMP', 'like', "%{$buscar}%")
                      ->orWhere('EMPLEADO.CI_EMP', 'like', "%{$buscar}%");
                });
            })
            ->when($cargo !== '', fn ($q) => $q->where('EMPLEADO.COD_CAR', $cargo))
            ->orderBy('EMPLEADO.APE_EMP')
            ->get();

        $cargos = DB::table('CARGO')->orderBy('DES_CAR')->get();

        return view('admin.empleados.index', compact('empleados', 'cargos', 'buscar', 'cargo'));
    }

    public function create()
    {
        return view('admin.empleados.crear', $this->catalogos());
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $codEmp = null;

        DB::transaction(function () use ($d, &$codEmp) {
            $siguiente = (int) DB::table('EMPLEADO')
                ->max(DB::raw('CAST(SUBSTRING(COD_EMP, 4) AS UNSIGNED)')) + 1;
            $codEmp = sprintf('EMP%06d', $siguiente);

            DB::table('EMPLEADO')->insert(['COD_EMP' => $codEmp] + $this->fila($d));

            Auditoria::registrar(
                'Crear', 'Media', 'DOM01', 'EMPLEADO', $codEmp,
                mb_substr("Alta del empleado {$d['nombre']} {$d['apellido']}", 0, 255)
            );
        });

        return redirect()->route('admin.empleados')
            ->with('ok', "Empleado {$d['nombre']} {$d['apellido']} registrado ({$codEmp}). Ya puedes crearle un usuario.");
    }

    public function edit(string $codEmp)
    {
        $empleado = DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->first();
        abort_if(!$empleado, 404);

        return view('admin.empleados.editar', ['empleado' => $empleado] + $this->catalogos());
    }

    public function update(Request $request, string $codEmp)
    {
        $actual = DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->first();
        abort_if(!$actual, 404);

        $d = $this->validar($request, $codEmp);
        $nuevo = $this->fila($d);

        // Qué campos cambiaron (para el detalle de auditoría)
        $cambios = [];
        foreach ($nuevo as $campo => $valor) {
            if ((string) $actual->$campo !== (string) $valor) {
                $cambios[] = $campo;
            }
        }

        if (!$cambios) {
            return redirect()->route('admin.empleados')->with('ok', 'No hubo cambios que guardar.');
        }

        DB::transaction(function () use ($codEmp, $nuevo, $cambios) {
            DB::table('EMPLEADO')->where('COD_EMP', $codEmp)->update($nuevo);

            Auditoria::registrar(
                'Actualizar', 'Media', 'DOM01', 'EMPLEADO', $codEmp,
                mb_substr("Edición de {$codEmp}. Campos: " . implode(', ', $cambios), 0, 255)
            );
        });

        return redirect()->route('admin.empleados')->with('ok', 'Empleado actualizado correctamente.');
    }

    // ---------- helpers ----------

    private function catalogos(): array
    {
        return [
            'cargos'         => DB::table('CARGO')->orderBy('DES_CAR')->get(),
            'especialidades' => DB::table('ESPECIALIDAD')->orderBy('DES_ESP')->get(),
            // códigos de cargo que son "Médico" (el formulario los usa para mostrar matrícula/especialidad)
            'cargosMedico'   => DB::table('CARGO')
                ->whereRaw('LOWER(DES_CAR) IN (?, ?)', ['médico', 'medico'])
                ->pluck('COD_CAR')->all(),
            'titulos'        => self::TITULOS,
            'turnos'         => self::TURNOS,
        ];
    }

    private function esMedico(string $codCar): bool
    {
        $des = DB::table('CARGO')->where('COD_CAR', $codCar)->value('DES_CAR');

        return $des !== null && in_array(mb_strtolower(trim($des)), ['médico', 'medico'], true);
    }

    private function validar(Request $request, ?string $codEmp = null): array
    {
        $d = $request->validate([
            'ci'           => ['required', 'string', 'max:10', 'regex:/^[0-9A-Za-z-]+$/',
                               Rule::unique('EMPLEADO', 'CI_EMP')->ignore($codEmp, 'COD_EMP')],
            'nombre'       => ['required', 'string', 'max:40'],
            'apellido'     => ['required', 'string', 'max:40'],
            'titulo'       => ['required', Rule::in(self::TITULOS)],
            'sexo'         => ['required', 'in:M,F'],
            'nacimiento'   => ['required', 'date', 'before_or_equal:-18 years'],
            'telefono'     => ['required', 'digits:8'],
            'correo'       => ['required', 'email', 'max:60'],
            'direccion'    => ['required', 'string', 'max:100'],
            'ingreso'      => ['required', 'date', 'before_or_equal:today'],
            'turno'        => ['required', Rule::in(self::TURNOS)],
            'cargo'        => ['required', 'exists:CARGO,COD_CAR'],
            'matricula'    => ['nullable', 'string', 'max:12'],
            'especialidad' => ['nullable', 'exists:ESPECIALIDAD,COD_ESP'],
        ], [
            'ci.required'          => 'Escribe la cédula de identidad.',
            'ci.regex'             => 'La cédula solo puede llevar letras, números y guion.',
            'ci.unique'            => 'Ya existe un empleado con esa cédula.',
            'nombre.required'      => 'Escribe el nombre.',
            'apellido.required'    => 'Escribe el apellido.',
            'titulo.required'      => 'Selecciona el título.',
            'sexo.required'        => 'Selecciona el sexo.',
            'nacimiento.required'  => 'Indica la fecha de nacimiento.',
            'nacimiento.before_or_equal' => 'El empleado debe ser mayor de 18 años.',
            'telefono.digits'      => 'El teléfono debe tener 8 dígitos.',
            'correo.email'         => 'Escribe un correo válido.',
            'direccion.required'   => 'Escribe la dirección.',
            'ingreso.required'     => 'Indica la fecha de ingreso.',
            'ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'turno.required'       => 'Selecciona el turno.',
            'cargo.required'       => 'Selecciona el cargo.',
            'cargo.exists'         => 'El cargo seleccionado no existe.',
            'especialidad.exists'  => 'La especialidad seleccionada no existe.',
        ]);

        // Regla de negocio: matrícula y especialidad solo (y obligatorias) si es Médico
        $medico = $this->esMedico($d['cargo']);

        if ($medico) {
            $faltan = [];
            if (empty($d['matricula'])) {
                $faltan['matricula'] = 'Los médicos deben tener matrícula.';
            }
            if (empty($d['especialidad'])) {
                $faltan['especialidad'] = 'Los médicos deben tener especialidad.';
            }
            if ($faltan) {
                throw ValidationException::withMessages($faltan);
            }
        }

        $d['es_medico'] = $medico;

        return $d;
    }

    /** Arma la fila con los nombres de columna reales de EMPLEADO. */
    private function fila(array $d): array
    {
        return [
            'CI_EMP'     => $d['ci'],
            'NOM_EMP'    => trim($d['nombre']),
            'APE_EMP'    => trim($d['apellido']),
            'TIT_EMP'    => $d['titulo'],
            'SEX_EMP'    => $d['sexo'],
            'FECNAC_EMP' => $d['nacimiento'],
            'TEL_EMP'    => $d['telefono'],
            'COR_EMP'    => $d['correo'],
            'DIR_EMP'    => trim($d['direccion']),
            'MAT_EMP'    => $d['es_medico'] ? $d['matricula'] : null,
            'FECING_EMP' => $d['ingreso'],
            'TURNO_EMP'  => $d['turno'],
            'COD_CAR'    => $d['cargo'],
            'COD_ESP'    => $d['es_medico'] ? $d['especialidad'] : null,
        ];
    }
}