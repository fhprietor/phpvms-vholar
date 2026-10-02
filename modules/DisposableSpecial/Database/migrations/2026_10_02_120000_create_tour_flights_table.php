<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tramos de tour en su propia tabla.
 *
 * Hasta ahora un tramo era un vuelo marcado con route_code = codigo del tour y
 * route_leg = N (DS_Tour::legs() era hasMany por route_code), lo que obligaba a tocar
 * campos del vuelo que en phpVMS son de las rutas multietapa: ident con sufijo,
 * agrupacion en los listados, owner... Con esta tabla el tour referencia los vuelos que
 * quiera sin modificar nada del vuelo.
 *
 * No hay unico por (tour_id, leg) a proposito: varios vuelos pueden ser el mismo tramo
 * (rutas elegibles, como en las webs de referencia).
 */
return new class() extends Migration {
    public function up()
    {
        if (Schema::hasTable('disposable_tour_flights')) {
            return;
        }

        Schema::create('disposable_tour_flights', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tour_id');
            $table->string('flight_id', 36);
            $table->unsignedInteger('leg');
            $table->timestamps();

            $table->unique(['tour_id', 'flight_id']);
            $table->index(['tour_id', 'leg']);
            $table->index('flight_id');

            $table->foreign('tour_id')->references('id')->on('disposable_tours')->cascadeOnDelete();
            $table->foreign('flight_id')->references('id')->on('flights')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('disposable_tour_flights');
    }
};
