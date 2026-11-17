<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TarifasEspecialidadPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // Valores de demostración, no tarifas oficiales de CIES.
        // Referencia: https://medicard.com.bo/wp-content/uploads/2023/12/LIRIOS2.pdf
        $tarifas = [
            ['COD_SRV' => 'SRV90001', 'NOM_SRV' => 'Consulta ginecología', 'PRE_SRV' => 200, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV90002', 'NOM_SRV' => 'Consulta pediatría', 'PRE_SRV' => 200, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV90003', 'NOM_SRV' => 'Consulta psicología', 'PRE_SRV' => 200, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV90004', 'NOM_SRV' => 'Consulta odontología', 'PRE_SRV' => 200, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV90005', 'NOM_SRV' => 'Consulta urología', 'PRE_SRV' => 200, 'COD_CLA' => 'CLA0001'],
        ];
        DB::transaction(function () use ($tarifas) {
            foreach ($tarifas as $tarifa) {
                $existente = DB::table('SERVICIO')->where('COD_SRV', $tarifa['COD_SRV'])->first();
                if ($existente && $existente->NOM_SRV !== $tarifa['NOM_SRV']) {
                    throw new RuntimeException('El código '.$tarifa['COD_SRV'].' ya está asignado a otro servicio.');
                }
                if (!$existente) {
                    DB::table('SERVICIO')->insert($tarifa);
                }
            }
        });
    }
}
