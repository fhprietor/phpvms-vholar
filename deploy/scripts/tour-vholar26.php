<?php

/*
 * Tour VHOLAR 2026 — 18 tramos (SKBO base, con salidas a MROC y KMIA).
 *
 * Los "legs" de un tour de DisposableSpecial **son vuelos**: Flight con
 * route_code = tour_code y route_leg = N (ver DS_Tour::legs()). Las aeronaves
 * autorizadas son las subflotas del pivote flight_subfleet de cada tramo.
 *
 * Ejecutar desde la raiz del proyecto:
 *   php artisan tinker --execute="require 'deploy/scripts/tour-vholar26.php';"
 *
 * Es idempotente: si el tour o sus tramos ya existen, no duplica nada.
 */

use App\Models\Flight;
use Illuminate\Support\Facades\DB;
use Modules\DisposableSpecial\Models\DS_Tour;

$tourCode = 'VHR26'; // OJO: flights.route_code y disposable_tours.tour_code son varchar(5)
$tourName = 'Tour VHOLAR 2026';
// El codigo del tour tiene que caber en 5 caracteres (varchar(5) en las dos tablas);
// si no, MySQL lo trunca y luego la relacion legs() no encuentra los tramos.
$airlineId = 1;

// Familia Airbus A32X / A32X Neo y familia Boeing 787 (las que existen en la flota).
$subfleetIds = DB::table('subfleets')->whereIn('type', ['A20N', 'A21N', 'A319', 'A320', 'A321', 'B789'])
    ->pluck('id')->all();

// [leg, dpt, arr, distancia NM]
$legs = [
    [1, 'SKBO', 'SKSM', 385],
    [2, 'SKSM', 'SKPE', 389],
    [3, 'SKPE', 'SKRG', 83],
    [4, 'SKRG', 'SKSP', 536],
    [5, 'SKSP', 'SKCL', 628],
    [6, 'SKCL', 'SKBO', 151],
    [7, 'SKBO', 'SKPS', 274],
    [8, 'SKPS', 'SKCL', 140],
    [9, 'SKCL', 'SKCG', 417],
    [10, 'SKCG', 'MROC', 514],
    [11, 'MROC', 'SKBO', 678],
    [12, 'SKBO', 'SKCC', 217],
    [13, 'SKCC', 'SKRG', 203],
    [14, 'SKRG', 'KMIA', 1211],
    [15, 'KMIA', 'SKBO', 1315],
    [16, 'SKBO', 'SKBG', 157],
    [17, 'SKBG', 'SKBO', 157],
    [18, 'SKBO', 'SKRH', 416],
];

$rules = '<b>Authorized Aircraft:</b> Airbus A32X / A32X Neo family and Boeing 787 family<br><br>'
    .'<b>Weather Conditions:</b> Actual METAR/TAF conditions at the time of flight must be used.<br><br>'
    .'<b>Callsign:</b> VHR** callsign is mandatory<br><br>'
    .'<b>IVAO RMK:</b> VHOLAR26';

if (DS_Tour::where('tour_code', $tourCode)->exists()) {
    echo "El tour {$tourCode} ya existe: no se hace nada.\n";

    return;
}

// 1) El tour
$tour = DS_Tour::create([
    'tour_name'     => $tourName,
    'tour_code'     => $tourCode,
    'tour_desc'     => '<p>18 legs across Colombia with two international escapes: San Jose (Costa Rica) and Miami. '
        .'From the Caribbean coast to the Andes, flying the Airbus A32X family or the Boeing 787.</p>',
    'tour_rules'    => $rules,
    'tour_airline'  => $airlineId,
    'tour_token'    => 0,
    'tour_fplremark' => 'VHOLAR26',
    'start_date'    => '2026-10-02',
    'end_date'      => '2026-12-31',
    'active'        => 1,
]);

// 2) Los tramos: vuelos programados con route_code = codigo del tour
$firstNumber = 2603; // 2601-2602 los usa el tour ANDES
$created = 0;

foreach ($legs as [$leg, $dpt, $arr, $nm]) {
    if (Flight::where('route_code', $tourCode)->where('route_leg', $leg)->exists()) {
        continue;
    }

    // Bloque estimado: ~450 kt de crucero (7.5 nm/min) + 15 min de rodaje, en multiplos de 5.
    $blockTime = (int) (round(($nm / 7.5 + 15) / 5) * 5);

    $flight = Flight::create([
        'airline_id'      => $airlineId,
        'flight_number'   => $firstNumber + $leg - 1,
        'route_code'      => $tourCode,
        'route_leg'       => $leg,
        'dpt_airport_id'  => $dpt,
        'arr_airport_id'  => $arr,
        'alt_airport_id'  => null, // sin alterno: evita que se marque diversion
        'distance'        => $nm,
        'flight_time'     => $blockTime,
        'level'           => 40000,
        'flight_type'     => 'J', // pasajeros programado
        'route'           => null,
        'notes'           => 'Tour VHOLAR 2026 - Leg '.$leg.' | IFR | IVAO RMK/VHOLAR26',
        'active'          => 1,
        'visible'         => 1,
    ]);

    $flight->subfleets()->attach($subfleetIds);
    $created++;
}

echo "Tour {$tourCode} creado (id {$tour->id}) con {$created} tramos y ".count($subfleetIds)." subflotas por tramo.\n";
