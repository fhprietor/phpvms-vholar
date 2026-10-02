<?php

namespace Modules\DisposableSpecial\Listeners;

use App\Contracts\Listener;
use App\Events\PirepFiled;
use Illuminate\Support\Facades\DB;

/**
 * Sella el PIREP con el tour y el tramo cuando el vuelo reportado es un tramo de tour.
 *
 * Los tramos viven en su propia tabla (disposable_tour_flights), asi que los vuelos de
 * la programacion ya NO llevan route_code = codigo del tour. phpVMS copia al PIREP el
 * route_code/route_leg del vuelo, asi que sin esto los premios, el widget de progreso y
 * los informes (que buscan por route_code) dejarian de ver el tour.
 *
 * Aqui se ponen esos dos campos en el PIREP. Los vuelos no se tocan.
 */
class Gen_TourPirepStamp extends Listener
{
    public function handle(PirepFiled $event): void
    {
        $pirep = $event->pirep;

        if (empty($pirep) || filled($pirep->route_leg) || empty($pirep->flight_id)) {
            return;
        }

        $leg = DB::table('disposable_tour_flights')
            ->join('disposable_tours', 'disposable_tours.id', '=', 'disposable_tour_flights.tour_id')
            ->where('disposable_tour_flights.flight_id', $pirep->flight_id)
            ->select('disposable_tours.tour_code', 'disposable_tour_flights.leg')
            ->first();

        if (empty($leg)) {
            return;
        }

        $pirep->route_code = $leg->tour_code;
        $pirep->route_leg = $leg->leg;
        $pirep->save();
    }
}
