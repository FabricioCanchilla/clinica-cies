<?php

return [
    // El informe dice "un porcentaje de la consulta" sin fijarlo: 30 % es un supuesto, cámbialo con CIES_ANTICIPO_PORCENTAJE
    'anticipo_porcentaje'   => (int) env('CIES_ANTICIPO_PORCENTAJE', 30),
    'minutos_bloqueo'       => 15,   // RF008 / RNF007
    'duracion_cita_min'     => 30,
    'dias_max_anticipacion' => 30,

    // RF039 / RN29: un descuento mayor a este porcentaje exige usuario y clave de un administrador
    'descuento_sin_autorizacion' => 20,
    // Método que cuenta como efectivo para el arqueo del cierre de caja
    'metodo_efectivo'            => 'MET0001',

    // AJ08: minutos mínimos de acción del desinfectante y ventilación en la desinfección terminal de una cama
    'limpieza_terminal_min'      => (int) env('CIES_LIMPIEZA_TERMINAL_MIN', 45),

    // RN48: días que se conservan las copias de seguridad
    'respaldo_retencion_dias'    => 30,
    // RN37: ventana (minutos antes y después de la hora programada) en que la hora de la dosis es correcta
    'ventana_dosis_min'          => 30,
    // RNF008: segundos entre actualizaciones automáticas de las pantallas en tiempo real
    'refresco_segundos'          => 5,
];