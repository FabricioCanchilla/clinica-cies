<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lógica del panel de Laboratorio (RF025–RF029).
 *
 * Flujo de una orden:  Pendiente -> Muestra tomada -> En proceso -> (resultado) -> Listo
 * Flujo del resultado: Cargado -> Validado -> Publicado   (al publicar, la orden pasa a Listo)
 * Todo cambio queda en AUDITORIA con el dominio DOM03 (clínico).
 */
class LaboratorioService
{
    private const DOMINIO = 'DOM03';

    private const SIGUIENTE = [
        'Pendiente'      => 'Muestra tomada',
        'Muestra tomada' => 'En proceso',
    ];

    public const ESTADOS = ['Pendiente', 'Muestra tomada', 'En proceso', 'Listo', 'Cancelado'];

    /** Cantidad de órdenes por estado (todos los estados, aunque sea 0). */
    public function contadores(): array
    {
        $cuenta = DB::table('ORDEN_EXAMEN')
            ->select('EST_ORD', DB::raw('COUNT(*) AS total'))
            ->groupBy('EST_ORD')
            ->pluck('total', 'EST_ORD');

        $res = [];
        foreach (self::ESTADOS as $estado) {
            $res[$estado] = (int) ($cuenta[$estado] ?? 0);
        }
        return $res;
    }

    /** Listado de órdenes: primero las activas, y entre ellas las urgentes. */
    public function ordenes(string $buscar = '', string $estado = '', string $prioridad = '')
    {
        return DB::table('ORDEN_EXAMEN as o')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->leftJoin('RESULTADO_EXAMEN as r', 'r.COD_ORD', '=', 'o.COD_ORD')
            ->select(
                'o.COD_ORD', 'o.FEC_ORD', 'o.IND_ORD', 'o.EST_ORD', 'o.PRI_ORD',
                's.NOM_SRV', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC',
                'r.COD_RES', 'r.DES_RES', 'r.EST_RES', 'r.CRI_RES'
            )
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $like = '%' . $buscar . '%';
                    $w->where('o.COD_ORD', 'like', $like)
                      ->orWhere('p.CI_PAC', 'like', $like)
                      ->orWhere('p.NOM_PAC', 'like', $like)
                      ->orWhere('p.APE_PAC', 'like', $like)
                      ->orWhere('s.NOM_SRV', 'like', $like);
                });
            })
            ->when($estado !== '', fn ($q) => $q->where('o.EST_ORD', $estado))
            ->when($prioridad !== '', fn ($q) => $q->where('o.PRI_ORD', $prioridad))
            ->orderByRaw("o.EST_ORD IN ('Listo','Cancelado')")
            ->orderByRaw("o.PRI_ORD = 'Urgente' DESC")
            ->orderBy('o.FEC_ORD')
            ->orderBy('o.COD_ORD')
            ->get();
    }

    /** Una orden con los datos del paciente y del examen (para el formulario de resultado). */
    public function orden(string $codOrd): ?object
    {
        return DB::table('ORDEN_EXAMEN as o')
            ->join('SERVICIO as s', 's.COD_SRV', '=', 'o.COD_SRV')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'o.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->where('o.COD_ORD', $codOrd)
            ->select(
                'o.COD_ORD', 'o.FEC_ORD', 'o.IND_ORD', 'o.EST_ORD', 'o.PRI_ORD', 'o.COD_CNS',
                's.NOM_SRV', 'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC'
            )
            ->first();
    }

    public function resultadoDe(string $codOrd): ?object
    {
        return DB::table('RESULTADO_EXAMEN')->where('COD_ORD', $codOrd)->first();
    }

    /** Pendiente -> Muestra tomada -> En proceso. Devuelve el estado nuevo. */
    public function avanzar(string $codOrd, object $actor): string
    {
        return DB::transaction(function () use ($codOrd, $actor) {
            $orden = $this->bloquearOrden($codOrd);

            $nuevo = self::SIGUIENTE[$orden->EST_ORD] ?? null;
            if ($nuevo === null) {
                throw new DomainException("La orden {$codOrd} está «{$orden->EST_ORD}» y no se puede avanzar desde aquí.");
            }

            DB::table('ORDEN_EXAMEN')->where('COD_ORD', $codOrd)->update(['EST_ORD' => $nuevo]);

            Auditoria::registrar(
                'Actualizar', 'Media', self::DOMINIO, 'ORDEN_EXAMEN', $codOrd,
                "Estado: {$orden->EST_ORD} -> {$nuevo}", $actor
            );

            return $nuevo;
        });
    }

    /** Solo se cancela una orden que todavía no tiene muestra. */
    public function cancelar(string $codOrd, object $actor): void
    {
        DB::transaction(function () use ($codOrd, $actor) {
            $orden = $this->bloquearOrden($codOrd);

            if ($orden->EST_ORD !== 'Pendiente') {
                throw new DomainException("Solo se pueden cancelar órdenes pendientes; la {$codOrd} está «{$orden->EST_ORD}».");
            }

            DB::table('ORDEN_EXAMEN')->where('COD_ORD', $codOrd)->update(['EST_ORD' => 'Cancelado']);

            Auditoria::registrar(
                'Actualizar', 'Media', self::DOMINIO, 'ORDEN_EXAMEN', $codOrd,
                'Estado: Pendiente -> Cancelado', $actor
            );
        });
    }

    /** Carga el resultado (queda "Cargado", todavía no visible para el médico). */
    public function guardarResultado(string $codOrd, string $descripcion, bool $critico, object $actor): string
    {
        if (empty($actor->COD_EMP)) {
            throw new DomainException('Tu usuario no está vinculado a un empleado, así que no puede cargar resultados.');
        }

        return DB::transaction(function () use ($codOrd, $descripcion, $critico, $actor) {
            $orden = $this->bloquearOrden($codOrd);

            if ($orden->EST_ORD !== 'En proceso') {
                throw new DomainException("Solo se carga resultado a órdenes «En proceso»; la {$codOrd} está «{$orden->EST_ORD}».");
            }
            if ($this->resultadoDe($codOrd)) {
                throw new DomainException("La orden {$codOrd} ya tiene un resultado cargado.");
            }

            $siguiente = (int) DB::table('RESULTADO_EXAMEN')->max('id') + 1;
            $codRes = sprintf('RES%07d', $siguiente);

            DB::table('RESULTADO_EXAMEN')->insert([
                'COD_RES' => $codRes,
                'FEC_RES' => now(),
                'DES_RES' => $descripcion,
                'ARC_RES' => null,
                'COD_ORD' => $codOrd,
                'EST_RES' => 'Cargado',
                'CRI_RES' => $critico ? 1 : 0,
                'COD_EMP' => $actor->COD_EMP,
            ]);

            Auditoria::registrar(
                'Crear', $critico ? 'Crítica' : 'Alta', self::DOMINIO, 'RESULTADO_EXAMEN', $codRes,
                "Resultado cargado para la orden {$codOrd}" . ($critico ? ' (valor crítico)' : ''), $actor
            );

            return $codRes;
        });
    }

    /** Cargado -> Validado. */
    public function validar(string $codOrd, object $actor): void
    {
        DB::transaction(function () use ($codOrd, $actor) {
            $this->bloquearOrden($codOrd);
            $res = $this->resultadoBloqueado($codOrd);

            if ($res->EST_RES !== 'Cargado') {
                throw new DomainException("El resultado de la orden {$codOrd} está «{$res->EST_RES}» y no se puede validar.");
            }

            DB::table('RESULTADO_EXAMEN')->where('COD_RES', $res->COD_RES)->update(['EST_RES' => 'Validado']);

            Auditoria::registrar(
                'Actualizar', 'Alta', self::DOMINIO, 'RESULTADO_EXAMEN', $res->COD_RES,
                "Resultado validado (orden {$codOrd})", $actor
            );
        });
    }

    /**
     * Validado -> Publicado y la orden pasa a "Listo".
     * Si el resultado es crítico, se avisa al médico que pidió el examen.
     * Devuelve true si se generó la notificación al médico.
     */
    public function publicar(string $codOrd, object $actor): bool
    {
        return DB::transaction(function () use ($codOrd, $actor) {
            $orden = $this->bloquearOrden($codOrd);
            $res = $this->resultadoBloqueado($codOrd);

            if ($res->EST_RES !== 'Validado') {
                throw new DomainException('El resultado debe estar validado antes de publicarse.');
            }

            DB::table('RESULTADO_EXAMEN')->where('COD_RES', $res->COD_RES)->update(['EST_RES' => 'Publicado']);
            DB::table('ORDEN_EXAMEN')->where('COD_ORD', $codOrd)->update(['EST_ORD' => 'Listo']);

            $critico = (int) $res->CRI_RES === 1;
            $notificado = $critico && $this->notificarMedico($orden);

            Auditoria::registrar(
                'Actualizar', $critico ? 'Crítica' : 'Alta', self::DOMINIO, 'RESULTADO_EXAMEN', $res->COD_RES,
                "Resultado publicado (orden {$codOrd})" . ($critico ? ($notificado ? ', médico notificado' : ', médico sin usuario para notificar') : ''),
                $actor
            );

            return $notificado;
        });
    }

    // ------------------------------------------------------------------

    private function bloquearOrden(string $codOrd): object
    {
        $orden = DB::table('ORDEN_EXAMEN')->where('COD_ORD', $codOrd)->lockForUpdate()->first();
        if (!$orden) {
            throw new DomainException("La orden {$codOrd} no existe.");
        }
        return $orden;
    }

    private function resultadoBloqueado(string $codOrd): object
    {
        $res = DB::table('RESULTADO_EXAMEN')->where('COD_ORD', $codOrd)->lockForUpdate()->first();
        if (!$res) {
            throw new DomainException("La orden {$codOrd} todavía no tiene resultado cargado.");
        }
        return $res;
    }

    private function notificarMedico(object $orden): bool
    {
        $codEmpMedico = DB::table('CONSULTA')->where('COD_CNS', $orden->COD_CNS)->value('COD_EMP');
        $codUsrMedico = $codEmpMedico
            ? DB::table('USUARIO')->where('COD_EMP', $codEmpMedico)->value('COD_USR')
            : null;

        if (!$codUsrMedico) {
            return false;
        }

        $dato = $this->orden($orden->COD_ORD);
        $mensaje = "Resultado crítico: {$dato->NOM_SRV} de {$dato->NOM_PAC} {$dato->APE_PAC} (orden {$orden->COD_ORD}).";

        $siguiente = (int) DB::table('NOTIFICACION')->max('id') + 1;

        DB::table('NOTIFICACION')->insert([
            'COD_NOT' => sprintf('NOT%07d', $siguiente),
            'FEC_NOT' => now(),
            'TIP_NOT' => 'Resultado crítico',
            'MSG_NOT' => mb_substr($mensaje, 0, 255),
            'CAN_NOT' => 'Sistema',
            'EST_NOT' => 'Pendiente',
            'REF_NOT' => $orden->COD_ORD,
            'COD_USR' => $codUsrMedico,
        ]);

        return true;
    }
}