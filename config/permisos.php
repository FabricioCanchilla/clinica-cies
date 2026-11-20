<?php

/*
 * RF043 / RNF001: permisos por módulo. Cada permiso cubre un grupo de rutas (por prefijo del nombre).
 * El rol entra solo a su panel (middleware «rol»); además, dentro del panel, cada acción exige que su rol
 * tenga el permiso del módulo en ROL_PERMISO (middleware VerificarPermiso). El administrador ajusta la
 * matriz en Configuración → Roles y permisos. Las portadas (*.dashboard) no exigen permiso.
 */
return [
    'modulos' => [
        'PER0001' => ['nombre' => 'Usuarios, empleados y pacientes', 'rutas' => ['admin.usuarios', 'admin.empleados', 'admin.pacientes']],
        'PER0002' => ['nombre' => 'Supervisión de citas, caja e internación', 'rutas' => ['admin.citas', 'admin.caja', 'admin.internacion']],
        'PER0003' => ['nombre' => 'Reportes de gestión e indicadores', 'rutas' => ['admin.reportes', 'reportes.']],
        'PER0004' => ['nombre' => 'Auditoría y exportación', 'rutas' => ['admin.auditoria']],
        'PER0005' => ['nombre' => 'Configuración general y respaldos', 'rutas' => ['admin.configuracion']],
        'PER0006' => ['nombre' => 'Planificación de camas (IO II)', 'rutas' => ['admin.recomendador']],
        'PER0007' => ['nombre' => 'Agenda y atención médica', 'rutas' => ['medico.']],
        'PER0008' => ['nombre' => 'Historia clínica completa', 'rutas' => ['medico.historia']],
        'PER0009' => ['nombre' => 'Laboratorio e imagenología', 'rutas' => ['laboratorio.']],
        'PER0010' => ['nombre' => 'Farmacia: recetas y stock', 'rutas' => ['farmacia.']],
        'PER0016' => ['nombre' => 'Farmacia: venta en mostrador', 'rutas' => ['farmacia.ventas']],
        'PER0011' => ['nombre' => 'Enfermería e internación', 'rutas' => ['enfermeria.']],
        'PER0012' => ['nombre' => 'Caja y comprobantes', 'rutas' => ['caja.']],
        'PER0013' => ['nombre' => 'Recepción y check-in', 'rutas' => ['recepcion.']],
        'PER0014' => ['nombre' => 'Portal del paciente', 'rutas' => ['paciente.']],
        'PER0015' => ['nombre' => 'Órdenes de pago', 'rutas' => ['ordenes.']],
    ],

    // Permisos mínimos por rol (RNF001)
    'por_defecto' => [
        'ROL001' => ['PER0001', 'PER0002', 'PER0003', 'PER0004', 'PER0005', 'PER0006', 'PER0015'],
        'ROL002' => ['PER0013', 'PER0003', 'PER0015'],
        'ROL003' => ['PER0007', 'PER0008', 'PER0003'],
        'ROL004' => ['PER0011'],
        'ROL005' => ['PER0012', 'PER0015'],
        'ROL006' => ['PER0014', 'PER0015'],
        'ROL007' => ['PER0009'],
        'ROL008' => ['PER0010', 'PER0016'],
        'ROL009' => ['PER0004'],
    ],

    // El administrador no puede quitarse estos permisos (evita quedar fuera de la configuración)
    'protegidos' => ['ROL001' => ['PER0005']],
];
