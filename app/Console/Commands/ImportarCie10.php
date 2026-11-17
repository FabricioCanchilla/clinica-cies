<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportarCie10 extends Command
{
    protected $signature = 'medico:importar-cie10 {archivo : CSV UTF-8 con encabezados codigo,descripcion}';

    protected $description = 'Importa el catálogo CIE-10 institucional desde CSV, conservando diagnósticos ya emitidos';

    public function handle(): int
    {
        $ruta = $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error('Archivo no encontrado o no legible.');

            return self::FAILURE;
        }
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            $this->error('Archivo no legible.');

            return self::FAILURE;
        }
        $encabezado = fgetcsv($f, 0, ',', '"', '');
        if ($encabezado) {
            $encabezado[0] = ltrim($encabezado[0], "\xEF\xBB\xBF");
        }
        if ($encabezado !== ['codigo', 'descripcion']) {
            fclose($f);
            $this->error('El encabezado debe ser codigo,descripcion.');

            return self::FAILURE;
        }
        try {
            $cantidad = DB::transaction(function () use ($f) {
                $linea = 1;
                $cantidad = 0;
                while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
                    $linea++;
                    if ($r === [null]) {
                        continue;
                    }
                    $codigo = strtoupper(trim($r[0] ?? ''));
                    $descripcion = trim($r[1] ?? '');
                    if (count($r) !== 2 || ! preg_match('/^[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?$/D', $codigo)
                        || $descripcion === '' || mb_strlen($descripcion) > 300 || ! mb_check_encoding($descripcion, 'UTF-8')) {
                        throw new RuntimeException('Fila '.$linea.' inválida. No se importó ningún registro.');
                    }
                    DB::table('CATALOGO_CIE10')->updateOrInsert(['CODIGO' => $codigo], ['DESCRIPCION' => $descripcion]);
                    $cantidad++;
                }

                return $cantidad;
            });
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            fclose($f);
        }
        $this->info($cantidad.' diagnósticos importados.');

        return self::SUCCESS;
    }
}
