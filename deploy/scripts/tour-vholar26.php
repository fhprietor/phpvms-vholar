<?php

/*
 * Tour VHOLAR 2026 — 18 tramos (SKBO base, con salidas a MROC y KMIA).
 *
 * IMPORTANTE: en DisposableSpecial un "tramo" es un VUELO normal de la aerolinea
 * marcado con route_code = codigo del tour, route_leg = N y owner = DS_Tour
 * (ver DS_Tour::legs() y leg_actions()/normalize, que lo libera al terminar).
 * Por eso este script **marca los vuelos que ya existen** en lugar de duplicar la
 * programacion; solo crea los dos tramos que no tienen vuelo programado.
 *
 * Codigo del tour: VHR26 (flights.route_code y disposable_tours.tour_code son
 * varchar(5): no cabe mas).
 *
 * Ejecutar desde la raiz del proyecto:
 *   php artisan tinker --execute="require 'deploy/scripts/tour-vholar26.php';"
 *
 * Es idempotente. Para revertir un tramo enlazado: route_code = NULL,
 * route_leg = NULL, owner_type = NULL, owner_id = NULL (asi estaban los 16 vuelos).
 */

use App\Models\Flight;
use Illuminate\Support\Facades\DB;
use Modules\DisposableSpecial\Models\DS_Tour;

$tourCode = 'VHR26'; // OJO: flights.route_code y disposable_tours.tour_code son varchar(5)
$tourName = 'Tour VHOLAR 2026';
$airlineId = 1;

// Familia Airbus A32X / A32X Neo y familia Boeing 737 (las que existen en la flota).
// Solo se usan al CREAR los tramos sin vuelo programado: a los vuelos existentes no
// se les tocan sus subflotas.
$subfleetIds = DB::table('subfleets')->whereIn('type', ['A20N', 'A21N', 'A319', 'A320', 'A321', 'B38M', 'B737', 'B738'])
    ->pluck('id')->all();

// [leg, dpt, arr, distancia NM, numero de vuelo de la aerolinea (null = crear)]
$legs = [
    [1, 'SKBO', 'SKSM', 385, 1265],
    [2, 'SKSM', 'SKPE', 389, null], // no hay vuelo programado: se crea
    [3, 'SKPE', 'SKRG', 83, 8180],
    [4, 'SKRG', 'SKSP', 536, 8320],
    [5, 'SKSP', 'SKCL', 628, 4271],
    [6, 'SKCL', 'SKBO', 151, 1111],
    [7, 'SKBO', 'SKPS', 274, 1244],
    [8, 'SKPS', 'SKCL', 140, 7994],
    [9, 'SKCL', 'SKCG', 417, 4152],
    [10, 'SKCG', 'MROC', 514, null], // no hay vuelo programado: se crea
    [11, 'MROC', 'SKBO', 678, 193],
    [12, 'SKBO', 'SKCC', 217, 9320],
    [13, 'SKCC', 'SKRG', 203, 9509],
    [14, 'SKRG', 'KMIA', 1211, 30],
    [15, 'KMIA', 'SKBO', 1315, 5],
    [16, 'SKBO', 'SKBG', 157, 2150],
    [17, 'SKBG', 'SKBO', 157, 2151],
    [18, 'SKBO', 'SKRH', 416, 8428],
];

// Numeros para los dos tramos que hay que crear (serie 26xx de la temporada).
$newLegNumbers = [2 => 2604, 10 => 2612];

$rules = '<b>Authorized Aircraft:</b> Airbus A32X / A32X Neo family and Boeing 737 family<br><br>'
    .'<b>Weather Conditions:</b> Actual METAR/TAF conditions at the time of flight must be used.<br><br>'
    .'<b>Callsign:</b> VHR** callsign is mandatory<br><br>'
    .'<b>IVAO RMK:</b> VHOLAR26';

// 1) El tour
$tour = DS_Tour::where('tour_code', $tourCode)->first();

if (!$tour) {
    $tour = DS_Tour::create([
        'tour_name'      => $tourName,
        'tour_code'      => $tourCode,
        'tour_desc'      => '<p>18 legs across Colombia with two international escapes: San Jose (Costa Rica) and Miami. '
            .'From the Caribbean coast to the Andes, flying the Airbus A32X family or the Boeing 737 family.</p>',
        'tour_rules'     => $rules,
        'tour_airline'   => $airlineId,
        'tour_token'     => 0,
        'tour_fplremark' => 'VHOLAR26',
        'start_date'     => '2026-10-02',
        'end_date'       => '2026-12-31',
        'active'         => 1,
    ]);
    echo "Tour {$tourCode} creado (id {$tour->id}).\n";
}

// 2) Los tramos
$linked = 0;
$created = 0;

foreach ($legs as [$leg, $dpt, $arr, $nm, $flightNumber]) {
    $flight = $flightNumber
        ? Flight::where('airline_id', $airlineId)
            ->where('flight_number', $flightNumber)
            ->where('dpt_airport_id', $dpt)
            ->where('arr_airport_id', $arr)
            ->first()
        : null;

    if ($flight) {
        // Vuelo real de la aerolinea: solo se marca como tramo (no se toca nada mas)
        $flight->route_code = $tourCode;
        $flight->route_leg = $leg;
        $flight->owner_type = 'DS_Tour';
        $flight->owner_id = $tour->id;
        $flight->save();
        $linked++;

        continue;
    }

    // Sin vuelo programado: se crea el tramo (salvo que ya exista de una pasada anterior)
    $existing = Flight::where('route_code', $tourCode)->where('route_leg', $leg)->first();
    if ($existing) {
        continue;
    }

    // Bloque estimado: ~450 kt de crucero (7.5 nm/min) + 15 min de rodaje, en multiplos de 5.
    $blockTime = (int) (round(($nm / 7.5 + 15) / 5) * 5);

    $flight = Flight::create([
        'airline_id'     => $airlineId,
        'flight_number'  => $newLegNumbers[$leg],
        'route_code'     => $tourCode,
        'route_leg'      => $leg,
        'dpt_airport_id' => $dpt,
        'arr_airport_id' => $arr,
        'alt_airport_id' => null, // sin alterno: evita que se marque diversion
        'distance'       => $nm,
        'flight_time'    => $blockTime,
        'level'          => 40000,
        'flight_type'    => 'J', // pasajeros programado
        'notes'          => 'Tour VHOLAR 2026 - Leg '.$leg.' | IFR | IVAO RMK/VHOLAR26',
        'active'         => 1,
        'visible'        => 1,
    ]);

    $flight->subfleets()->attach($subfleetIds);
    $created++;
}

// 3) Limpieza: vuelos creados por la version anterior del script que ya no son tramos.
// Solo se borran si no tienen reservas ni PIREPs.
$keepNumbers = array_values(array_filter($newLegNumbers));
$obsolete = Flight::where('route_code', $tourCode)
    ->whereBetween('flight_number', [2603, 2620])
    ->whereNotIn('flight_number', $keepNumbers)
    ->get();

$removed = 0;
foreach ($obsolete as $flight) {
    $hasBids = DB::table('bids')->where('flight_id', $flight->id)->exists();
    $hasPireps = DB::table('pireps')->where('flight_id', $flight->id)->exists();
    if ($hasBids || $hasPireps) {
        echo "AVISO: el vuelo {$flight->flight_number} tiene reservas/PIREPs, no se borra.\n";

        continue;
    }

    $flight->subfleets()->detach();
    $flight->delete();
    $removed++;
}

$legsInTour = Flight::where('route_code', $tourCode)->count();
echo "Tour {$tourCode}: {$legsInTour} tramos ({$linked} enlazados a vuelos existentes, {$created} creados, {$removed} obsoletos borrados).\n";
