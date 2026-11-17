<?php

return [
    'anticipo_porcentaje'   => 50,
    'minutos_bloqueo'       => 15,   // RF008 / RNF007
    'duracion_cita_min'     => 30,
    'dias_max_anticipacion' => 30,

    // RF039 / RN29: un descuento mayor a este porcentaje exige usuario y clave de un administrador
    'descuento_sin_autorizacion' => 20,
    // Método que cuenta como efectivo para el arqueo del cierre de caja
    'metodo_efectivo'            => 'MET0001',
];
