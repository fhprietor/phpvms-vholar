<?php

use App\Models\Enums\PirepSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill historico de pireps.source_name.
 *
 * Hasta el 2026-09-16 ningun PIREP traia source_name: lo dejaban vacio la
 * importacion de CrewSystem (source = ACARS) y los PIREPs archivados a mano en
 * el panel (source = MANUAL). Con la integracion ACARS actual el campo si se
 * rellena, por lo que la correccion se limita a las filas anteriores a esa
 * fecha para no etiquetar PIREPs legitimos de clientes que no lo envien.
 *
 * Se aplico a mano el 2026-09-29 sobre la instalacion (5718 + 227 filas); esta
 * migracion lo hace reproducible en cualquier entorno. Usa el query builder a
 * proposito: no debe tocar updated_at.
 */
return new class extends Migration
{
    private const CUTOFF = '2026-09-16 00:00:00';

    public function up(): void
    {
        // Importaciones historicas de CrewSystem
        DB::table('pireps')
            ->where('source', PirepSource::ACARS)
            ->where('created_at', '<', self::CUTOFF)
            ->where(function ($query) {
                $query->whereNull('source_name')->orWhere('source_name', '');
            })
            ->update(['source_name' => 'CrewSystem/import']);

        // PIREPs archivados a mano en el panel
        DB::table('pireps')
            ->where('source', PirepSource::MANUAL)
            ->where('created_at', '<', self::CUTOFF)
            ->where(function ($query) {
                $query->whereNull('source_name')->orWhere('source_name', '');
            })
            ->update(['source_name' => 'manual']);
    }

    public function down(): void
    {
        // Sin vuelta atras: el valor original era vacio/nulo y no se puede
        // distinguir de un PIREP que ya lo traia asi.
    }
};
