<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Lógica del panel de Farmacia (RF040, RF042, RNF017).
 *
 * - Despacho de recetas: descuenta del lote que vence primero (PEPS) y solo de lotes vigentes.
 * - Unidades requeridas por ítem = ceil(24 / FRE_DRE) * DUR_DRE  (FRE_DRE está en horas, DUR_DRE en días).
 * - Despacho parcial: si no alcanza el stock se entrega lo que hay; la receta queda "Activa"
 *   hasta que todos los ítems estén completos y entonces pasa a "Completada".
 * - Despachos en DOM03 (clínico); entradas y bajas de lote en DOM04 (inventario/financiero).
 * - Los despachos registran en AUDITORIA el paciente afectado (COD_PAC); las entradas y bajas
 *   de lote no, porque no involucran a ningún paciente.
 */
class FarmaciaService
{
    private const DOM_CLINICO    = 'DOM03';
    private const DOM_INVENTARIO = 'DOM04';

    public const DIAS_ALERTA    = 30;
    public const ESTADOS_RECETA = ['Activa', 'Completada', 'Anulada'];
    public const ESTADOS_LOTE   = ['Vigente', 'Por vencer', 'Vencido', 'Agotado'];
    public const MOTIVOS_BAJA   = ['Merma', 'Rotura', 'Vencido'];

    public static function requeridas(int $frecuenciaHoras, int $duracionDias): int
    {
        return ((int) ceil(24 / max(1, $frecuenciaHoras))) * $duracionDias;
    }

    // ------------------------------------------------------------------
    // RECETAS (RF040)
    // ------------------------------------------------------------------

    public function contadoresRecetas(): array
    {
        $cuenta = DB::table('RECETA')
            ->select('EST_REC', DB::raw('COUNT(*) AS total'))
            ->groupBy('EST_REC')
            ->pluck('total', 'EST_REC');

        $res = [];
        foreach (self::ESTADOS_RECETA as $estado) {
            $res[$estado] = (int) ($cuenta[$estado] ?? 0);
        }
        return $res;
    }

    /** Listado de recetas con su avance (entregadas / requeridas). Primero las activas. */
    public function recetas(string $buscar = '', string $estado = '')
    {
        return DB::table('RECETA as r')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->leftJoin('DETALLE_RECETA as d', 'd.COD_REC', '=', 'r.COD_REC')
            ->select('r.COD_REC', 'r.FEC_REC', 'r.EST_REC',
                'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'e.NOM_EMP', 'e.APE_EMP')
            ->selectRaw('COALESCE(SUM(CEIL(24 / d.FRE_DRE) * d.DUR_DRE), 0) AS requeridas')
            ->selectRaw('COALESCE(SUM(d.CANENT_DRE), 0) AS entregadas')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $like = '%' . $buscar . '%';
                    $w->where('r.COD_REC', 'like', $like)
                      ->orWhere('p.CI_PAC', 'like', $like)
                      ->orWhere('p.NOM_PAC', 'like', $like)
                      ->orWhere('p.APE_PAC', 'like', $like);
                });
            })
            ->when($estado !== '', fn ($q) => $q->where('r.EST_REC', $estado))
            ->groupBy('r.COD_REC', 'r.FEC_REC', 'r.EST_REC',
                'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'e.NOM_EMP', 'e.APE_EMP')
            ->orderByRaw("r.EST_REC = 'Activa' DESC")
            ->orderBy('r.FEC_REC')
            ->orderBy('r.COD_REC')
            ->get();
    }

    public function receta(string $codRec): ?object
    {
        return DB::table('RECETA as r')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->join('PACIENTE as p', 'p.COD_PAC', '=', 'h.COD_PAC')
            ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
            ->where('r.COD_REC', $codRec)
            ->select('r.COD_REC', 'r.FEC_REC', 'r.EST_REC', 'r.OBS_REC',
                'p.NOM_PAC', 'p.APE_PAC', 'p.CI_PAC', 'e.NOM_EMP', 'e.APE_EMP')
            ->first();
    }

    /** Ítems de la receta con requeridas, pendientes y stock vigente del medicamento. */
    public function detalles(string $codRec)
    {
        $hoy = now()->toDateString();

        return DB::table('DETALLE_RECETA as d')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'd.COD_MED')
            ->join('VIA_ADMIN as v', 'v.COD_VIA', '=', 'd.COD_VIA')
            ->where('d.COD_REC', $codRec)
            ->select('d.COD_DRE', 'd.DOS_DRE', 'd.FRE_DRE', 'd.DUR_DRE', 'd.CANENT_DRE',
                'd.COD_MED', 'm.DES_MED', 'v.DES_VIA')
            ->selectRaw(
                '(SELECT COALESCE(SUM(l.CANDIS_LOT), 0) FROM LOTE_MEDICAMENTO l
                   WHERE l.COD_MED = d.COD_MED AND l.FECVEN_LOT >= ?) AS stock',
                [$hoy]
            )
            ->orderBy('d.COD_DRE')
            ->get()
            ->map(function ($f) {
                $f->requeridas = self::requeridas((int) $f->FRE_DRE, (int) $f->DUR_DRE);
                $f->pendientes = max(0, $f->requeridas - (int) $f->CANENT_DRE);
                $f->stock      = (int) $f->stock;
                return $f;
            });
    }

    /**
     * Despacha un ítem de la receta descontando por PEPS.
     * Devuelve: medicamento, solicitado, entregado, pendiente (del ítem), completa (receta), lotes.
     */
    public function despacharItem(string $codRec, string $codDre, int $cantidad, object $actor): array
    {
        if ($cantidad < 1) {
            throw new DomainException('La cantidad a despachar debe ser al menos 1.');
        }

        return DB::transaction(function () use ($codRec, $codDre, $cantidad, $actor) {
            $hoy = now()->toDateString();

            $receta = DB::table('RECETA')->where('COD_REC', $codRec)->lockForUpdate()->first();
            if (!$receta) {
                throw new DomainException("La receta {$codRec} no existe.");
            }
            if ($receta->EST_REC !== 'Activa') {
                throw new DomainException("La receta {$codRec} está «{$receta->EST_REC}» y no se puede despachar.");
            }

            $item = DB::table('DETALLE_RECETA')
                ->where('COD_DRE', $codDre)->where('COD_REC', $codRec)
                ->lockForUpdate()->first();
            if (!$item) {
                throw new DomainException("El ítem {$codDre} no pertenece a la receta {$codRec}.");
            }

            $nombre    = (string) DB::table('MEDICAMENTO')->where('COD_MED', $item->COD_MED)->value('DES_MED');
            $requeridas = self::requeridas((int) $item->FRE_DRE, (int) $item->DUR_DRE);
            $pendiente = $requeridas - (int) $item->CANENT_DRE;

            if ($pendiente <= 0) {
                throw new DomainException("{$nombre} ya está entregado por completo en la receta {$codRec}.");
            }
            if ($cantidad > $pendiente) {
                throw new DomainException("Solo quedan {$pendiente} unidades pendientes de {$nombre}.");
            }

            // PEPS: primero el lote que vence antes; nunca lotes vencidos
            $lotes = DB::table('LOTE_MEDICAMENTO')
                ->where('COD_MED', $item->COD_MED)
                ->where('CANDIS_LOT', '>', 0)
                ->where('FECVEN_LOT', '>=', $hoy)
                ->orderBy('FECVEN_LOT')->orderBy('FECING_LOT')->orderBy('id')
                ->lockForUpdate()->get();

            $disponible = (int) $lotes->sum('CANDIS_LOT');
            if ($disponible <= 0) {
                throw new DomainException("No hay stock vigente de {$nombre}.");
            }

            $entregar = min($cantidad, $disponible);
            $resto    = $entregar;
            $usados   = [];

            foreach ($lotes as $lote) {
                if ($resto <= 0) {
                    break;
                }
                $toma = min($resto, (int) $lote->CANDIS_LOT);
                DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $lote->COD_LOT)
                    ->update(['CANDIS_LOT' => (int) $lote->CANDIS_LOT - $toma]);
                $usados[] = "{$lote->COD_LOT} x{$toma}";
                $resto   -= $toma;
            }

            DB::table('DETALLE_RECETA')->where('COD_DRE', $codDre)
                ->update(['CANENT_DRE' => (int) $item->CANENT_DRE + $entregar]);

            $quedan = $pendiente - $entregar;

            // Paciente al que pertenece la receta (para la auditoría)
            $codPac = $this->pacienteDeReceta($codRec);

            Auditoria::registrar(
                'Actualizar', 'Media', self::DOM_CLINICO, 'DETALLE_RECETA', $codDre,
                "Receta {$codRec}: {$nombre} x{$entregar} (lotes: " . implode(', ', $usados) . "). Pendiente del ítem: {$quedan}",
                $actor,
                codPac: $codPac
            );

            // ¿Todos los ítems completos? entonces la receta pasa a Completada
            $completa = DB::table('DETALLE_RECETA')->where('COD_REC', $codRec)->get()
                ->every(fn ($d) => (int) $d->CANENT_DRE >= self::requeridas((int) $d->FRE_DRE, (int) $d->DUR_DRE));

            if ($completa) {
                DB::table('RECETA')->where('COD_REC', $codRec)->update(['EST_REC' => 'Completada']);
                Auditoria::registrar(
                    'Actualizar', 'Media', self::DOM_CLINICO, 'RECETA', $codRec,
                    'Estado: Activa -> Completada (todos los ítems entregados)',
                    $actor,
                    codPac: $codPac
                );
            }

            return [
                'medicamento' => $nombre,
                'solicitado'  => $cantidad,
                'entregado'   => $entregar,
                'pendiente'   => $quedan,
                'completa'    => $completa,
                'lotes'       => $usados,
            ];
        });
    }

    // ------------------------------------------------------------------
    // STOCK Y VENCIMIENTOS (RF042, RNF017)
    // ------------------------------------------------------------------

    public static function estadoLote(object $l, string $hoy, string $limite): string
    {
        if ((int) $l->CANDIS_LOT === 0) {
            return 'Agotado';
        }
        if ($l->FECVEN_LOT < $hoy) {
            return 'Vencido';
        }
        if ($l->FECVEN_LOT <= $limite) {
            return 'Por vencer';
        }
        return 'Vigente';
    }

    /** Lotes con stock agrupados por situación de vencimiento (para alertas y tarjetas). */
    public function contadoresLotes(): array
    {
        $hoy    = now()->toDateString();
        $limite = now()->addDays(self::DIAS_ALERTA)->toDateString();

        $f = DB::table('LOTE_MEDICAMENTO')
            ->where('CANDIS_LOT', '>', 0)
            ->selectRaw(
                'COALESCE(SUM(FECVEN_LOT > ?), 0) AS vigentes,
                 COALESCE(SUM(FECVEN_LOT >= ? AND FECVEN_LOT <= ?), 0) AS por_vencer,
                 COALESCE(SUM(FECVEN_LOT < ?), 0) AS vencidos',
                [$limite, $hoy, $limite, $hoy]
            )->first();

        return [
            'vigentes'   => (int) $f->vigentes,
            'por_vencer' => (int) $f->por_vencer,
            'vencidos'   => (int) $f->vencidos,
        ];
    }

    /** Existencias en tiempo real por medicamento (solo cuenta como disponible lo vigente). */
    public function existencias()
    {
        $hoy = now()->toDateString();

        return DB::table('MEDICAMENTO as m')
            ->leftJoin('LOTE_MEDICAMENTO as l', 'l.COD_MED', '=', 'm.COD_MED')
            ->select('m.COD_MED', 'm.DES_MED')
            ->selectRaw('SUM(CASE WHEN l.FECVEN_LOT >= ? THEN l.CANDIS_LOT ELSE 0 END) AS vigente', [$hoy])
            ->selectRaw('SUM(CASE WHEN l.FECVEN_LOT < ? THEN l.CANDIS_LOT ELSE 0 END) AS vencido', [$hoy])
            ->groupBy('m.COD_MED', 'm.DES_MED')
            ->orderBy('m.DES_MED')
            ->get();
    }

    /** Lotes en orden PEPS (los que vencen antes primero; los agotados al final). */
    public function lotes(string $buscar = '', string $estado = '')
    {
        $hoy    = now()->toDateString();
        $limite = now()->addDays(self::DIAS_ALERTA)->toDateString();

        $filas = DB::table('LOTE_MEDICAMENTO as l')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'l.COD_MED')
            ->select('l.COD_LOT', 'l.NRO_LOT', 'l.FECING_LOT', 'l.FECVEN_LOT',
                'l.CANING_LOT', 'l.CANDIS_LOT', 'l.COD_MED', 'm.DES_MED')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $like = '%' . $buscar . '%';
                    $w->where('l.COD_LOT', 'like', $like)
                      ->orWhere('l.NRO_LOT', 'like', $like)
                      ->orWhere('m.DES_MED', 'like', $like);
                });
            })
            ->orderByRaw('l.CANDIS_LOT = 0')
            ->orderBy('l.FECVEN_LOT')
            ->orderBy('l.COD_LOT')
            ->get()
            ->map(function ($l) use ($hoy, $limite) {
                $l->ESTADO = self::estadoLote($l, $hoy, $limite);
                return $l;
            });

        return $estado !== '' ? $filas->where('ESTADO', $estado)->values() : $filas;
    }

    public function lote(string $codLot): ?object
    {
        return DB::table('LOTE_MEDICAMENTO as l')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'l.COD_MED')
            ->where('l.COD_LOT', $codLot)
            ->select('l.*', 'm.DES_MED')
            ->first();
    }

    public function medicamentos()
    {
        return DB::table('MEDICAMENTO')->orderBy('DES_MED')->get();
    }

    /** Entrada de stock: cada entrada crea un lote nuevo (CANDIS = CANING). Sin paciente afectado. */
    public function registrarEntrada(string $codMed, string $nroLot, string $fecVen, int $cantidad, object $actor): string
    {
        return DB::transaction(function () use ($codMed, $nroLot, $fecVen, $cantidad, $actor) {
            $hoy = now()->toDateString();

            $nombre = DB::table('MEDICAMENTO')->where('COD_MED', $codMed)->value('DES_MED');
            if (!$nombre) {
                throw new DomainException('El medicamento elegido no existe.');
            }
            if ($fecVen <= $hoy) {
                throw new DomainException('No se puede ingresar un lote que ya está vencido o vence hoy.');
            }
            if ($cantidad < 1) {
                throw new DomainException('La cantidad del lote debe ser al menos 1.');
            }
            if (DB::table('LOTE_MEDICAMENTO')->where('COD_MED', $codMed)->where('NRO_LOT', $nroLot)->exists()) {
                throw new DomainException("Ya existe el lote «{$nroLot}» de {$nombre}.");
            }

            $siguiente = (int) DB::table('LOTE_MEDICAMENTO')->max('id') + 1;
            $codLot    = sprintf('LOT%07d', $siguiente);

            DB::table('LOTE_MEDICAMENTO')->insert([
                'COD_LOT'    => $codLot,
                'NRO_LOT'    => $nroLot,
                'FECING_LOT' => $hoy,
                'FECVEN_LOT' => $fecVen,
                'CANING_LOT' => $cantidad,
                'CANDIS_LOT' => $cantidad,
                'COD_MED'    => $codMed,
            ]);

            Auditoria::registrar(
                'Crear', 'Media', self::DOM_INVENTARIO, 'LOTE_MEDICAMENTO', $codLot,
                "Entrada de lote {$nroLot}: {$nombre} x{$cantidad}, vence {$fecVen}", $actor
            );

            return $codLot;
        });
    }

    /** Baja de stock (merma, rotura o vencido) con motivo obligatorio. Sin paciente afectado. */
    public function darBaja(string $codLot, int $cantidad, string $motivo, object $actor): void
    {
        DB::transaction(function () use ($codLot, $cantidad, $motivo, $actor) {
            if (!in_array($motivo, self::MOTIVOS_BAJA, true)) {
                throw new DomainException('Elige un motivo de baja válido.');
            }

            $lote = DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $codLot)->lockForUpdate()->first();
            if (!$lote) {
                throw new DomainException("El lote {$codLot} no existe.");
            }
            if ($cantidad < 1 || $cantidad > (int) $lote->CANDIS_LOT) {
                throw new DomainException("La cantidad debe estar entre 1 y {$lote->CANDIS_LOT} (stock del lote).");
            }

            $nombre = (string) DB::table('MEDICAMENTO')->where('COD_MED', $lote->COD_MED)->value('DES_MED');
            $nuevo  = (int) $lote->CANDIS_LOT - $cantidad;

            DB::table('LOTE_MEDICAMENTO')->where('COD_LOT', $codLot)->update(['CANDIS_LOT' => $nuevo]);

            Auditoria::registrar(
                'Actualizar', 'Alta', self::DOM_INVENTARIO, 'LOTE_MEDICAMENTO', $codLot,
                "BAJA de {$cantidad} uds del lote {$lote->NRO_LOT} ({$nombre}). Motivo: {$motivo}. Stock: {$lote->CANDIS_LOT} -> {$nuevo}",
                $actor
            );
        });
    }

    // ------------------------------------------------------------------

    /** Paciente dueño de la receta: RECETA -> CONSULTA -> HISTORIA_CLINICA. */
    private function pacienteDeReceta(string $codRec): ?string
    {
        $codPac = DB::table('RECETA as r')
            ->join('CONSULTA as c', 'c.COD_CNS', '=', 'r.COD_CNS')
            ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('r.COD_REC', $codRec)
            ->value('h.COD_PAC');

        return $codPac ?: null;
    }
}