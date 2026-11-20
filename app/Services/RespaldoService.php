<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RN48 / RF044 / RNF013: copias de seguridad de la base (información clínica y financiera).
 *  - crear(): vuelca estructura y datos de todas las tablas a un .sql.gz en el almacenamiento privado,
 *    con su SHA-256 y el conteo de filas por tabla.
 *  - probar(): prueba de restauración verificable: comprueba el hash, restaura la copia en la base
 *    «clinica_cies_restauracion» y compara tabla por tabla las filas restauradas con las respaldadas.
 *  - depurar(): elimina las copias con más días que la retención (30 por defecto).
 * Programación: copia diaria a las 02:00, depuración diaria y prueba mensual (routes/console.php).
 */
class RespaldoService
{
    private const SEPARADOR = "\n-- ;;\n";
    private const DIR = 'respaldos';
    public const BD_PRUEBA = 'clinica_cies_restauracion';

    public function lista()
    {
        return DB::table('RESPALDO as r')->leftJoin('USUARIO as u', 'u.COD_USR', '=', 'r.COD_USR')
            ->orderByDesc('r.id')->limit(60)->get(['r.*', 'u.USR_USR']);
    }

    public function crear(string $tipo = 'Manual', ?object $actor = null): object
    {
        @set_time_limit(600);
        $pdo = DB::connection()->getPdo();
        $tablas = collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(fn ($t) => array_values((array) $t)[0])->sort()->values();

        $nombre = 'cies-' . now()->format('Ymd-His') . '.sql.gz';
        $rutaRel = self::DIR . '/' . $nombre;
        Storage::disk('local')->makeDirectory(self::DIR);
        $abs = Storage::disk('local')->path($rutaRel);
        $gz = gzopen($abs, 'wb6');
        if (!$gz) {
            throw new DomainException('No se pudo crear el archivo de respaldo.');
        }

        $conteo = [];
        gzwrite($gz, "-- Clínica CIES · respaldo {$tipo} · " . now()->toDateTimeString() . "\n");
        gzwrite($gz, 'SET FOREIGN_KEY_CHECKS=0' . self::SEPARADOR);
        foreach ($tablas as $t) {
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$t}`"))[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$t}`" . self::SEPARADOR . $create . self::SEPARADOR);
            $filas = 0;
            $lote = [];
            foreach (DB::table($t)->cursor() as $fila) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $fila);
                $lote[] = '(' . implode(',', $vals) . ')';
                $filas++;
                if (count($lote) === 200) {
                    gzwrite($gz, "INSERT INTO `{$t}` VALUES " . implode(",\n", $lote) . self::SEPARADOR);
                    $lote = [];
                }
            }
            if ($lote) {
                gzwrite($gz, "INSERT INTO `{$t}` VALUES " . implode(",\n", $lote) . self::SEPARADOR);
            }
            $conteo[$t] = $filas;
        }
        gzwrite($gz, 'SET FOREIGN_KEY_CHECKS=1' . self::SEPARADOR);
        gzwrite($gz, '-- CONTEO ' . json_encode($conteo) . "\n");
        gzclose($gz);

        $cod = sprintf('RES%07d', (int) DB::table('RESPALDO')->max('id') + 1);
        DB::table('RESPALDO')->insert([
            'COD_RES' => $cod, 'FEC_RES' => now(), 'TIP_RES' => $tipo, 'ARC_RES' => $rutaRel,
            'TAM_RES' => filesize($abs), 'HAS_RES' => hash_file('sha256', $abs),
            'TAB_RES' => count($conteo), 'FIL_RES' => array_sum($conteo), 'EST_RES' => 'Vigente',
            'FECPRU_RES' => null, 'RESPRU_RES' => null, 'COD_USR' => $actor->COD_USR ?? null,
        ]);
        if ($actor) {
            Auditoria::registrar('Crear respaldo', 'Alta', 'DOM02', 'RESPALDO', $cod,
                "Respaldo {$tipo}: " . count($conteo) . ' tablas, ' . array_sum($conteo) . ' filas', $actor);
        }
        return DB::table('RESPALDO')->where('COD_RES', $cod)->first();
    }

    /** Prueba de restauración: hash + restauración real en la base de pruebas + comparación de filas. */
    public function probar(string $codRes, ?object $actor = null): array
    {
        @set_time_limit(900);
        $r = DB::table('RESPALDO')->where('COD_RES', $codRes)->first();
        if (!$r || $r->EST_RES !== 'Vigente') {
            throw new DomainException('El respaldo no existe o ya fue eliminado.');
        }
        $abs = Storage::disk('local')->path($r->ARC_RES);
        $resultado = ['ok' => false, 'detalle' => ''];

        try {
            if (!is_file($abs)) {
                throw new DomainException('El archivo del respaldo no está en el almacenamiento.');
            }
            if (!hash_equals($r->HAS_RES, hash_file('sha256', $abs))) {
                throw new DomainException('El SHA-256 no coincide: el archivo fue alterado o está dañado.');
            }
            $sql = gzdecode(file_get_contents($abs));
            if ($sql === false) {
                throw new DomainException('El archivo no se pudo descomprimir.');
            }
            if (!preg_match('/^-- CONTEO (.+)$/m', $sql, $m)) {
                throw new DomainException('El respaldo no trae el conteo de filas.');
            }
            $esperado = json_decode($m[1], true);

            config(['database.connections.restauracion' => array_merge(config('database.connections.mysql'), ['database' => self::BD_PRUEBA])]);
            DB::purge('restauracion');
            $con = DB::connection('restauracion');
            foreach (explode(self::SEPARADOR, $sql) as $sentencia) {
                $sentencia = trim(preg_replace('/^--.*$/m', '', $sentencia));
                if ($sentencia !== '') {
                    $con->unprepared($sentencia);
                }
            }
            $dif = [];
            foreach ($esperado as $tabla => $filas) {
                $hay = (int) $con->table($tabla)->count();
                if ($hay !== (int) $filas) {
                    $dif[] = "{$tabla}: {$hay}/{$filas}";
                }
            }
            DB::disconnect('restauracion');
            if ($dif) {
                throw new DomainException('Filas distintas tras restaurar: ' . implode(', ', array_slice($dif, 0, 5)));
            }
            $resultado = ['ok' => true, 'detalle' => 'Restauración correcta: ' . count($esperado) . ' tablas y '
                . array_sum($esperado) . ' filas verificadas en ' . self::BD_PRUEBA . ' (SHA-256 válido).'];
        } catch (\Throwable $e) {
            $resultado = ['ok' => false, 'detalle' => 'Falló: ' . mb_substr($e->getMessage(), 0, 230)];
        }

        DB::table('RESPALDO')->where('COD_RES', $codRes)->update([
            'FECPRU_RES' => now(), 'RESPRU_RES' => mb_substr($resultado['detalle'], 0, 255),
        ]);
        if ($actor) {
            Auditoria::registrar('Probar restauración', $resultado['ok'] ? 'Media' : 'Crítica', 'DOM02', 'RESPALDO', $codRes,
                $resultado['detalle'], $actor);
        }
        return $resultado;
    }

    /** Conserva las copias de los últimos N días (RN48). */
    public function depurar(): int
    {
        $limite = now()->subDays((int) config('cies.respaldo_retencion_dias', 30));
        $viejos = DB::table('RESPALDO')->where('EST_RES', 'Vigente')->where('FEC_RES', '<', $limite)->get();
        foreach ($viejos as $r) {
            Storage::disk('local')->delete($r->ARC_RES);
            DB::table('RESPALDO')->where('COD_RES', $r->COD_RES)->update(['EST_RES' => 'Eliminado']);
        }
        return $viejos->count();
    }

    public function ruta(string $codRes): ?string
    {
        $r = DB::table('RESPALDO')->where('COD_RES', $codRes)->where('EST_RES', 'Vigente')->first();
        return $r && Storage::disk('local')->exists($r->ARC_RES) ? Storage::disk('local')->path($r->ARC_RES) : null;
    }
}
