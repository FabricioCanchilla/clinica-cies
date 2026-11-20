<?php

/*
 * Datos institucionales de CIES que muestra el portal público (RF001, RF002, RF004).
 * Fuente: sitio oficial https://www.cies.org.bo (páginas «Sobre CIES», «Historia y Misión»,
 * «CIES La Paz» e inicio), revisado el 20/11/2026. Si cambian, se actualizan aquí.
 */
return [
    'nombre'    => 'CIES · Salud Sexual Salud Reproductiva',
    'razon'     => 'Centro de Investigación, Educación y Servicios (CIES)',
    'lema'      => 'Tuyo de verdad',
    'sitio'     => 'https://www.cies.org.bo',

    // Sede que atiende este sistema
    'sede' => [
        'nombre'    => 'CIES Miraflores',
        'direccion' => 'Av. Tejada Sorzano # 393, Miraflores, La Paz',
        'telefono'  => '(2) 2216061',
        'tel_link'  => '+59122216061',
        'celular'   => '77261375',
        'mapa'      => 'https://www.google.com/maps?q=Av.+Tejada+Sorzano+393,+Miraflores,+La+Paz,+Bolivia&output=embed',
        'mapa_link' => 'https://www.google.com/maps/search/?api=1&query=Av.+Tejada+Sorzano+393,+Miraflores,+La+Paz,+Bolivia',
    ],

    // Clínica CIES La Paz (emergencias 24 h) y su horario publicado
    'clinica_la_paz' => [
        'direccion' => 'Colombia entre Boquerón y Zoilo Flores # 539, La Paz',
        'telefono'  => '(2) 2485111 – 2493425',
        'tel_link'  => '+59122485111',
        'horario'   => [
            'Lunes a viernes' => '07:30 a 22:00',
            'Sábados'         => '07:30 a 17:00',
            'Domingos'        => '09:00 a 12:00',
            'Emergencias'     => '24 horas',
        ],
    ],

    // Contacto nacional
    'linea_gratuita' => '800 11 2437',
    'whatsapp'       => '789 81995',
    'whatsapp_link'  => 'https://wa.me/59178981995',
    'correo'         => 'programasyproyectos@cies.org.bo',
    'oficina'        => 'Obrajes calle 6 # 614, La Paz, Bolivia',
    'redes' => [
        ['fa-brands fa-facebook', 'CiesSSSR', 'https://www.facebook.com/CiesSSSR'],
        ['fa-brands fa-instagram', 'ciesbolivia', 'https://www.instagram.com/ciesbolivia/'],
        ['fa-brands fa-tiktok', '@ciesbo', 'https://www.tiktok.com/@ciesbo'],
        ['fa-brands fa-youtube', 'YouTube CIES', 'https://www.youtube.com/channel/UC-GMeJLXl7nYjwKz3IIhgng'],
        ['fa-brands fa-linkedin', 'ciessssr', 'https://www.linkedin.com/company/ciessssr/'],
        ['fa-brands fa-x-twitter', '@CIESBolivia', 'https://x.com/CIESBolivia'],
    ],

    // Puntos de atención de la red en La Paz y El Alto (como los publica cies.org.bo)
    'red_la_paz' => [
        ['Clínica CIES La Paz', 'Colombia entre Boquerón y Zoilo Flores # 539', '(2) 2485111 – 2493425', 'Emergencias 24 h, cirugías e internación'],
        ['CIES Miraflores', 'Av. Tejada Sorzano # 393', '(2) 2216061 · Cel. 77261375', 'Sede de este sistema'],
        ['CIES San Pedro', 'Calle Boquerón # 1444 esq. Colombia, Z. San Pedro', '(2) 2492279 · Cel. 78897409', null],
        ['CIES El Rosario', 'Calle Tumusla # 481, El Rosario', '(2) 2454271 · Cel. 75223825', null],
        ['CIES Chasquipampa', 'Av. Palca # 50, Chasquipampa', '(2) 2771206 · Cel. 78897408', null],
        ['CIES Calacoto', 'Calle 9 esq. Inofuentes, Calacoto', null, null],
        ['CIES Miraflores (Tórax)', 'Calle Claudio Sanjinez # 1697 esq. República Dominicana, frente al Instituto Nacional del Tórax', null, null],
        ['CIES Terminal de Buses', 'Terminal de Buses La Paz (entrada por la puerta de salida de buses)', '2280044', null],
        ['CIES El Alto', 'Zona Bolívar Municipal calle 1 # 132 (Cruce Villa Adela), El Alto', '76768149', null],
    ],

    'red_nacional' => [
        'departamentos' => 9, 'clinicas' => 8, 'policonsultorios' => 13, 'umosas' => 3, 'comunidades' => 200,
        'ciudades' => ['La Paz', 'El Alto', 'Santa Cruz', 'Cochabamba', 'Tarija', 'Oruro', 'Potosí', 'Sucre', 'Riberalta', 'Cobija'],
    ],

    'mision'  => 'Somos una organización de desarrollo social que promueve el ejercicio pleno de los Derechos Sexuales y Derechos Reproductivos implementando un modelo de promoción y asistencia en salud integral, con enfoque de género, generacional, intercultural e interseccional; para mejorar la calidad de vida de la población boliviana.',
    'vision'  => 'Ser una organización laica, sostenible, innovadora, de excelencia, líder a nivel nacional e internacional en Salud Sexual y Salud Reproductiva que contribuye a una sociedad boliviana saludable, empoderada y que ejerza sus derechos.',
    'calidad' => 'CIES te ofrece la mejor experiencia en salud y educación, con servicios integrales, accesibles, oportunos; con personal sensibilizado, comprometido y capacitado; con un enfoque de mejora continua, para el ejercicio pleno de tus derechos.',
    'historia' => 'CIES inició su trabajo con un grupo de mujeres con sensibilidad política y social, comprometidas con los derechos y la salud sexual y salud reproductiva —María del Rosario Calderón, Bertha Pooley, Luisa Rada y Celia Taborga— a través de los Consultorios Populares de Salud en La Paz, El Alto, Oruro y Potosí, en coordinación con organizaciones sindicales y gremiales. Al inicio el trabajo se centró en información y educación a la comunidad: anticoncepción, prevención del embarazo en adolescentes y trabajo con diversidades sexuales. Esa experiencia llevó a organizar los Centros Regionales que hoy llegan a los 9 departamentos de Bolivia.',
    'valores' => [
        'Igualdad de derechos y oportunidades para todas las personas',
        'Respeto a la diversidad',
        'Equidad económico-social',
        'Participación social',
        'Búsqueda de la excelencia',
        'Integralidad de sus acciones',
        'Transparencia en su accionar',
    ],
    'compromisos' => [
        ['fa-user-doctor', 'Profesionales altamente calificados'],
        ['fa-heart', 'Atención con calidad y calidez'],
        ['fa-hand-holding-dollar', 'Precios accesibles'],
        ['fa-user-shield', 'Confidencialidad'],
        ['fa-microscope', 'Tecnología de punta'],
    ],

    // Proyectos vigentes publicados por CIES (institución aliada, proyecto, ubicación, público)
    'proyectos' => [
        ['CIES / Oxfam', 'Poder elegir: salud y derechos sexuales y reproductivos para mujeres jóvenes y adolescentes', 'Riberalta (Beni)', 'Adolescentes (15 a 17 años) y jóvenes (18 a 24 años)'],
        ['CIES / Alianza por la Solidaridad', 'Acceso de adolescentes, jóvenes y mujeres a servicios públicos de salud sexual y reproductiva con adecuación cultural', 'Pucarani y Viacha (La Paz)', 'Mujeres, hombres y jóvenes'],
        ['CIES / Louvain Coopération', 'Iniciativas en Salud', 'La Paz', 'Adolescentes, jóvenes, maestras y maestros, madres y padres, autoridades y personal de salud'],
        ['CIES / Bergstrom Foundation', 'CIES Vasectomies', 'La Paz, Cochabamba, Santa Cruz, Oruro, Potosí, Sucre y Tarija', 'Varones en edad reproductiva'],
        ['CIES / Fos Feminista / Bergstrom Foundation', 'UMOSAS: unidades móviles de salud', 'Caranavi, Alto Beni, Palos Blancos, Coroico, Coripata, Yanacachi, Chulumani, La Asunta, Irupana, Guanay, Teoponte y Mapiri', 'Población vulnerable'],
        ['CIES / Plan International / GAC', 'Proyecto REACH', 'Tarija, Santa Cruz, Cochabamba y Chuquisaca', 'Adolescentes y jóvenes'],
        ['CIES / Medicor Foundation', 'Acceso a servicios integrales de salud sexual y salud reproductiva', 'Riberalta (Beni) y Cobija (Pando)', 'Población vulnerable'],
        ['CIES / Iniciativas Sanitarias', 'Plataforma digital de diabetes', 'Nacional', 'Mujeres'],
        ['CIES / BID Lab', 'Salud digital', 'La Paz y Santa Cruz', 'Mujeres embarazadas'],
    ],

    // Servicios que la red CIES La Paz publica, agrupados como en cies.org.bo
    'servicios_red' => [
        'Especialidades médicas' => ['Audiometría', 'Cardiología', 'Dermatología', 'Endocrinología', 'Fisioterapia', 'Gastroenterología', 'Ginecología', 'Hematología', 'Mastología', 'Medicina General', 'Medicina Interna', 'Neumología', 'Neurología', 'Nutrición', 'Odontología', 'Oftalmología', 'Oncología', 'Otorrinolaringología', 'Patología', 'Pediatría', 'Proctología', 'Psicología', 'Psiquiatría', 'Reumatología', 'Traumatología', 'Urología'],
        'Servicios complementarios' => ['Laboratorio', 'Ecografía', 'Farmacia', 'Emergencia 24 h'],
        'Cirugías e internación' => ['Cirugía General', 'Cirugía Pediátrica', 'Cirugía Ginecológica', 'Cirugía de Vesícula', 'Cirugía Maxilofacial', 'Cirugía Cardiovascular', 'Neurocirugía'],
    ],
];
