<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peso de aterrizaje PLANEADO (el del OFP), aparte del MEDIDO.
 *
 * vmsOpenACars lo manda en `metrics.planned_landing_weight_lbs` desde la 0.9.72. Es el peso
 * al que pertenece el `vref_kt` que envia el cliente (el plan de SimBrief), no el peso real
 * medido, que sigue en `landing_weight_lbs` y no se sustituye: tener los dos permite auditar
 * la diferencia entre lo planeado y lo volado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pirep_landings', function (Blueprint $table) {
            $table->float('planned_landing_weight_lbs')->nullable()->after('landing_weight_lbs');
        });
    }

    public function down(): void
    {
        Schema::table('pirep_landings', function (Blueprint $table) {
            $table->dropColumn('planned_landing_weight_lbs');
        });
    }
};
