<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba para los paneles de Laboratorio y Farmacia:
 * una consulta del paciente de prueba con 3 órdenes de examen, una receta
 * activa y lotes de medicamentos (uno próximo a vencer, para probar PEPS).
 */
class DatosClinicosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogos
        DB::table('CLASIFICACION_SERVICIO')->insertOrIgnore([
            ['COD_CLA' => 'CLA0001', 'DES_CLA' => 'Consulta'],
            ['COD_CLA' => 'CLA0002', 'DES_CLA' => 'Laboratorio'],
        ]);

        DB::table('SERVICIO')->insertOrIgnore([
            ['COD_SRV' => 'SRV00001', 'NOM_SRV' => 'Consulta general',   'PRE_SRV' => 80.00, 'COD_CLA' => 'CLA0001'],
            ['COD_SRV' => 'SRV00002', 'NOM_SRV' => 'Hemograma completo', 'PRE_SRV' => 60.00, 'COD_CLA' => 'CLA0002'],
            ['COD_SRV' => 'SRV00003', 'NOM_SRV' => 'Glucosa en ayunas',  'PRE_SRV' => 25.00, 'COD_CLA' => 'CLA0002'],
            ['COD_SRV' => 'SRV00004', 'NOM_SRV' => 'Perfil lipidico',    'PRE_SRV' => 90.00, 'COD_CLA' => 'CLA0002'],
        ]);

        DB::table('CONSULTORIO')->insertOrIgnore([
            'COD_CON' => 'CON001', 'NOM_CON' => 'Consultorio 1', 'UBI_CON' => 'Planta baja',
        ]);

        DB::table('VIA_ADMIN')->insertOrIgnore([
            ['COD_VIA' => 'VIA001', 'DES_VIA' => 'Oral'],
            ['COD_VIA' => 'VIA002', 'DES_VIA' => 'Intravenosa'],
        ]);

        DB::table('MEDICAMENTO')->insertOrIgnore([
            ['COD_MED' => 'MED00001', 'DES_MED' => 'Paracetamol 500 mg'],
            ['COD_MED' => 'MED00002', 'DES_MED' => 'Amoxicilina 500 mg'],
            ['COD_MED' => 'MED00003', 'DES_MED' => 'Ibuprofeno 400 mg'],
        ]);

        // Historia, cita y consulta del paciente de prueba (PAC0000001) con el médico de prueba (EMP000003)
        DB::table('HISTORIA_CLINICA')->insertOrIgnore([
            'COD_HIS' => 'HIS000001', 'NRO_HIS' => 'HC-000001', 'FECAPE_HIS' => '2026-11-01',
            'EST_HIS' => 'Activa', 'NOTADM_HIS' => null, 'COD_PAC' => 'PAC0000001',
        ]);

        DB::table('CITA')->insertOrIgnore([
            'COD_CIT' => 'CIT0000001', 'FEC_CIT' => '2026-11-09', 'HOR_CIT' => '09:00:00',
            'DUR_CIT' => 30, 'EST_CIT' => 'Atendida', 'MOT_CIT' => 'Control general',
            'FECEXP_CIT' => null, 'COD_PAC' => 'PAC0000001', 'COD_EMP' => 'EMP000003',
            'COD_CON' => 'CON001', 'COD_SRV' => 'SRV00001',
        ]);

        DB::table('CONSULTA')->insertOrIgnore([
            'COD_CNS' => 'CNS0000001', 'FEC_CNS' => '2026-11-09 09:00:00',
            'DIA_CNS' => 'Control general', 'PLA_CNS' => 'Examenes de laboratorio y tratamiento sintomatico',
            'COD_CIT' => 'CIT0000001', 'COD_HIS' => 'HIS000001', 'COD_EMP' => 'EMP000003',
        ]);

        // Órdenes de examen en distintos estados
        DB::table('ORDEN_EXAMEN')->insertOrIgnore([
            ['COD_ORD' => 'ORD0000001', 'FEC_ORD' => '2026-11-09', 'IND_ORD' => 'Hemograma completo, descartar anemia',
             'EST_ORD' => 'Pendiente',      'PRI_ORD' => 'Urgente', 'COD_CNS' => 'CNS0000001', 'COD_SRV' => 'SRV00002'],
            ['COD_ORD' => 'ORD0000002', 'FEC_ORD' => '2026-11-09', 'IND_ORD' => 'Glucosa en ayunas',
             'EST_ORD' => 'Muestra tomada', 'PRI_ORD' => 'Normal',  'COD_CNS' => 'CNS0000001', 'COD_SRV' => 'SRV00003'],
            ['COD_ORD' => 'ORD0000003', 'FEC_ORD' => '2026-11-09', 'IND_ORD' => 'Perfil lipidico de control',
             'EST_ORD' => 'En proceso',     'PRI_ORD' => 'Normal',  'COD_CNS' => 'CNS0000001', 'COD_SRV' => 'SRV00004'],
        ]);

               // Receta activa con dos medicamentos (unidades requeridas = ceil(24 / FRE_DRE) * DUR_DRE; FRE_DRE en horas)
        DB::table('RECETA')->insertOrIgnore([
            'COD_REC' => 'REC0000001', 'FEC_REC' => '2026-11-09', 'EST_REC' => 'Activa',
            'OBS_REC' => 'Tomar con alimentos', 'COD_CNS' => 'CNS0000001',
        ]);

        DB::table('DETALLE_RECETA')->insertOrIgnore([
            ['COD_DRE' => 'DRE0000001', 'DOS_DRE' => '1 tableta', 'FRE_DRE' => 3, 'DUR_DRE' => 5, 'CANENT_DRE' => 0,
             'COD_REC' => 'REC0000001', 'COD_MED' => 'MED00001', 'COD_VIA' => 'VIA001'],
            ['COD_DRE' => 'DRE0000002', 'DOS_DRE' => '1 capsula', 'FRE_DRE' => 3, 'DUR_DRE' => 7, 'CANENT_DRE' => 0,
             'COD_REC' => 'REC0000001', 'COD_MED' => 'MED00002', 'COD_VIA' => 'VIA001'],
        ]);

        // Stock por lote: LOT0000002 vence antes que LOT0000001 (PEPS) y está próximo a vencer
        DB::table('LOTE_MEDICAMENTO')->insertOrIgnore([
            ['COD_LOT' => 'LOT0000001', 'NRO_LOT' => 'L-2026-A', 'FECING_LOT' => '2026-01-15', 'FECVEN_LOT' => '2027-06-30',
             'CANING_LOT' => 100, 'CANDIS_LOT' => 100, 'COD_MED' => 'MED00001'],
            ['COD_LOT' => 'LOT0000002', 'NRO_LOT' => 'L-2025-Z', 'FECING_LOT' => '2025-06-01', 'FECVEN_LOT' => '2026-11-30',
             'CANING_LOT' => 60,  'CANDIS_LOT' => 40,  'COD_MED' => 'MED00001'],
            ['COD_LOT' => 'LOT0000003', 'NRO_LOT' => 'L-2026-B', 'FECING_LOT' => '2026-03-10', 'FECVEN_LOT' => '2027-03-31',
             'CANING_LOT' => 50,  'CANDIS_LOT' => 50,  'COD_MED' => 'MED00002'],
            ['COD_LOT' => 'LOT0000004', 'NRO_LOT' => 'L-2025-V', 'FECING_LOT' => '2025-10-01', 'FECVEN_LOT' => '2026-10-31',
             'CANING_LOT' => 30,  'CANDIS_LOT' => 20,  'COD_MED' => 'MED00003'],
        ]);
    }
}