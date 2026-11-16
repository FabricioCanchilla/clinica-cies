<?php

namespace App\Http\Controllers\Comun;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/** Bandeja de notificaciones del usuario (RN46: alertas críticas para admin y auditor; RF029; RF015). */
class NotificacionController extends Controller
{
    public function index()
    {
        $codUsr = auth()->user()->COD_USR;

        $notificaciones = DB::table('NOTIFICACION')
            ->where('COD_USR', $codUsr)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // Al abrir la bandeja, las enviadas pasan a «Leída»
        DB::table('NOTIFICACION')->where('COD_USR', $codUsr)->where('EST_NOT', 'Enviada')
            ->update(['EST_NOT' => 'Leída']);

        return view('notificaciones', ['notificaciones' => $notificaciones]);
    }
}
