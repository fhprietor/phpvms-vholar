<?php

return [
    'tour'      => 'Tour',
    'tours'     => 'Tours',
    'ttype'     => 'Tour Type',
    'topen'     => 'Generic Tour',
    'tairline'  => 'Airline Tour',
    'tcode'     => 'Tour Code',
    'tdesc'     => 'Description',
    'tdates'    => 'Valid Between',
    'tlegs'     => 'Leg Count',
    'legs'      => 'Tour Legs',
    'trules'    => 'Tour Rules',
    'treport'   => 'Tour Report',
    'tawards'   => 'Tour Award Winners',
    'tmap'      => 'Tour Map',
    'current'   => 'Active Tours',
    'future'    => 'Future Tours',
    'past'      => 'Closed Tours',
    'showhide'  => 'Show / Hide',
    'icontrue'  => 'Pirep Accepted and Valid',
    'iconfalse' => 'Pirep Not Found or Not Valid',
    'iconend'   => 'Tour Ended',
    'iconnoty'  => 'Tour Not Started Yet',
    // Tour Progress Widget Body
    'tptitle'   => 'Tour Progress',
    'tpyour'    => 'Your Tours:',
    'tpactive'  => 'Active Tours:',
    'tpnotour'  => 'You have no active Tours... ',
    'tpclick'   => 'Click for Tours',
    // Tour rules. El modulo lo tenia hardcodeado en la vista, asi que no se podia
    // traducir; ahora es una clave (una entrada por parrafo, admite HTML).
    'trules_text' => [
        'Tours can be flown and reported either manually or with acars support, for acars supported tour flights pilots can either bid/load a flight from the list or enter required info manually to New Flight window of our acars software. While sending a manual pirep or using acars with manual flight info entry, please do not forget to add correct route code and leg number to your reports. Missing this step may cause problems during route leg checks and award controls.',
        '<b>Open Tours</b> can be flown with any airline and aircraft according to pilot\'s choice, simply there are no company and/or aircraft restrictions for this type. While on the other hand <b>Airline Tours</b> must be flown with correct airline callsign and if provided with the subfleet assigned to the leg.',
        'As a general rule, all tour legs must be completed between validity period for earning awards.',
        '<b>To see the details and legs of a tour, simply click on the Tour Name</b>',
        'Safe Flights',
    ],
];
