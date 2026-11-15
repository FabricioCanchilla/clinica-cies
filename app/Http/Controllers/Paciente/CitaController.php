<?php
namespace App\Http\Controllers\Paciente;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CitaController extends Controller
{
    public function create(): View
    {
        $servicios = DB::table('servicio')
            ->where('COD_SRV', 'SRV00001')
            ->get();

        $especialidades = DB::table('especialidad')
            ->get();

        $medicos = DB::table('empleado')
            ->where('COD_CAR', 'CAR0003')
            ->select('COD_EMP', 'NOM_EMP', 'APE_EMP', 'COD_ESP')
            ->get();

        $horarios = DB::table('horario_medico')
            ->get();

        return view('paciente.citas.crear', compact(
            'servicios',
            'especialidades',
            'medicos',
            'horarios'
        ));
    }
}
