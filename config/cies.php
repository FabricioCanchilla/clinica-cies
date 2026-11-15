<?php

return [
    // El informe dice "un porcentaje de la consulta" sin fijarlo: 30 % es un supuesto, cámbialo con CIES_ANTICIPO_PORCENTAJE
    'anticipo_porcentaje'   => (int) env('CIES_ANTICIPO_PORCENTAJE', 30),
    'minutos_bloqueo'       => 15,   // RF008 / RNF007
    'duracion_cita_min'     => 30,
    'dias_max_anticipacion' => 30,
];