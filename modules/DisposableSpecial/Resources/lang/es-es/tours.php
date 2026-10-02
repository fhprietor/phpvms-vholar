<?php

return [
    'tour'      => 'Tour',
    'tours'     => 'Tours',
    'ttype'     => 'Tipo de Tour',
    'topen'     => 'Tour Genérico',
    'tairline'  => 'Tour Aerolínea',
    'tcode'     => 'Código de Tour',
    'tdesc'     => 'Descripción',
    'tdates'    => 'Valido entre',
    'tlegs'     => 'Cantidad de etapas',
    'legs'      => 'Etapas del Tour',
    'trules'    => 'Normas del Tour',
    'treport'   => 'Progreso del Tour',
    'tawards'   => 'Galardonados del Tour',
    'tmap'      => 'Mapa del Tour',
    'current'   => 'Tours activos',
    'future'    => 'Próximos Tours',
    'past'      => 'Tours cerrados',
    'showhide'  => 'Mostrar / Ocultar',
    'icontrue'  => 'Pirep aceptado y válido',
    'iconfalse' => 'Pirep no encontrado o válido',
    'iconend'   => 'Tour Finalizado',
    'iconnoty'  => 'Tour No ha comenzado',
    // Tour Progress Widget Body
    'tptitle'   => 'Progreso del Tour',
    'tpyour'    => 'Tus Tours:',
    'tpactive'  => 'Tours activos:',
    'tpnotour'  => 'No tienes Tours activos... ',
    'tpclick'   => 'Clic para Tours',
    // Normas del tour. El modulo las tenia hardcodeadas en la vista, asi que no se
    // podian traducir; ahora es una clave (una entrada por parrafo, admite HTML).
    'trules_text' => [
        'Los tours se pueden volar y reportar manualmente o con soporte acars. En los tours con acars el piloto puede reservar/cargar un vuelo de la lista o introducir los datos a mano en la ventana de Nuevo Vuelo de nuestro software acars. Al enviar un pirep manual, o al usar acars con entrada manual de datos, no olvides indicar el codigo de ruta y el numero de tramo correctos. Omitir este paso puede causar problemas en la comprobacion de tramos y en el control de premios.',
        '<b>Tours abiertos</b>: se pueden volar con cualquier aerolinea y aeronave a eleccion del piloto, es decir, no hay restricciones de compania ni de aeronave. En cambio, los <b>Tours de aerolinea</b> deben volarse con el indicativo correcto y, si se indica, con la subflota asignada al tramo.',
        'Como norma general, todos los tramos de un tour deben completarse dentro del periodo de validez para obtener los premios.',
        '<b>Para ver los detalles y los tramos de un tour, basta con pulsar en el nombre del tour</b>',
        'Buenos vuelos',
    ],
];
