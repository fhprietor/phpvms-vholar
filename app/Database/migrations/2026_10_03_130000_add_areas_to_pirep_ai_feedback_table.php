<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Valoracion por area del vuelo.
     *
     * El analisis nacio mirando solo el aterrizaje. Ahora el instructor comenta
     * tambien arranque, rodaje, despegue, combustible y economia, y cada area
     * necesita su propia nota para poder pintarlas por separado en la tarjeta,
     * el email y el embed.
     *
     * Se guarda como lista de {area, valoracion, nota}, con la misma escala de
     * gravedad que `severity` (0 correcto, 1 mejorable, 2 critico) para poder
     * reutilizar el mismo esquema de color del tema.
     */
    public function up(): void
    {
        if (Schema::hasTable('pirep_ai_feedback') && !Schema::hasColumn('pirep_ai_feedback', 'areas')) {
            Schema::table('pirep_ai_feedback', function (Blueprint $table) {
                $table->json('areas')->nullable()->after('action');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pirep_ai_feedback', 'areas')) {
            Schema::table('pirep_ai_feedback', function (Blueprint $table) {
                $table->dropColumn('areas');
            });
        }
    }
};
