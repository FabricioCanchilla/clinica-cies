<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Simulamos las citas que vendrían de la base de datos
        // En un futuro, esto será: Cita::where('COD_MED', auth()->id())->get();
        $citas = [
            ['id' => 1, 'dia' => 2, 'hora' => '09:00', 'paciente' => 'Juan Pérez', 'motivo' => 'Control', 'estado' => 'Confirmada'],
            ['id' => 2, 'dia' => 2, 'hora' => '10:00', 'paciente' => 'María Gómez', 'motivo' => 'Resultados', 'estado' => 'En espera'],
            ['id' => 3, 'dia' => 2, 'hora' => '11:30', 'paciente' => 'Carlos Ruiz', 'motivo' => 'Primera vez', 'estado' => 'Confirmada'],
            ['id' => 4, 'dia' => 2, 'hora' => '14:00', 'paciente' => 'Ana López', 'motivo' => 'Control', 'estado' => 'Cancelada'],
            ['id' => 5, 'dia' => 2, 'hora' => '15:30', 'paciente' => 'Luis Torres', 'motivo' => 'Dolor', 'estado' => 'Confirmada'],
            ['id' => 6, 'dia' => 3, 'hora' => '08:30', 'paciente' => 'Elena Díaz', 'motivo' => 'Revisión', 'estado' => 'En espera'],
            ['id' => 7, 'dia' => 6, 'hora' => '09:00', 'paciente' => 'Pedro Silva', 'motivo' => 'Control', 'estado' => 'Confirmada'],
            ['id' => 8, 'dia' => 8, 'hora' => '16:00', 'paciente' => 'Lucía Mendez', 'motivo' => 'Dolor', 'estado' => 'Confirmada'],
        ];

        return view('medico.dashboard', compact('citas'));
    }
}