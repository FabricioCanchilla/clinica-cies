<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PacienteController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));

        $pacientes = DB::table('PACIENTE')
            ->select(
                'COD_PAC',
                'CI_PAC',
                'NOM_PAC',
                'APE_PAC',
                'SEX_PAC',
                'FECNAC_PAC',
                'ESTCIV_PAC',
                'TEL_PAC',
                'COR_PAC',
                'DIR_PAC'
            )
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('CI_PAC', 'like', "%{$buscar}%")
                      ->orWhere('NOM_PAC', 'like', "%{$buscar}%")
                      ->orWhere('APE_PAC', 'like', "%{$buscar}%")
                      ->orWhere('COD_PAC', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('APE_PAC')
            ->orderBy('NOM_PAC')
            ->simplePaginate(15)
            ->withQueryString();

        return view('admin.pacientes.index', compact(
            'pacientes',
            'buscar'
        ));
    }
}     