<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pasaporte del piloto: sellos de los aeropuertos internacionales que ha visitado.
 *
 * No hay tabla de sellos ni listener: se CALCULA desde los PIREPs aceptados. Ventajas:
 * los sellos son retroactivos (todo el mundo los tiene ya), no se puede desincronizar con
 * la contabilidad de vuelos y no hay nada que mantener ni migrar.
 *
 * "Internacional" = aeropuerto cuyo pais no es el de la base (HOME_COUNTRY). El universo de
 * sellos posibles son los destinos de la aerolinea: los que aparecen en PIREPs aceptados o
 * en la programacion, para que el denominador no sea nunca menor que lo ya visitado.
 */
class PassportService extends Service
{
    /** Pais base de la aerolinea. Los sellos son para el resto. */
    public const HOME_COUNTRY = 'CO';

    /** Estado de PIREP aceptado (PirepState::ACCEPTED). */
    private const ACCEPTED = 2;

    /**
     * Pasaporte del piloto: sellos, totales y tours completados.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $visited = $this->visited($user);
        $universe = $this->universe();

        $stamps = [];
        foreach ($universe as $airport) {
            $stamps[$airport->id] = [
                'id'        => $airport->id,
                'iata'      => $airport->iata ?: $airport->id,
                'name'      => $airport->name,
                'country'   => strtoupper((string) $airport->country),
                'visited'   => isset($visited[$airport->id]),
                'first_seen' => $visited[$airport->id]->first_seen ?? null,
            ];
        }

        // Un aeropuerto visitado que ya no este en la programacion tambien cuenta.
        foreach ($visited as $id => $row) {
            if (!isset($stamps[$id])) {
                $stamps[$id] = [
                    'id'        => $id,
                    'iata'      => $row->iata ?: $id,
                    'name'      => $row->name,
                    'country'   => strtoupper((string) $row->country),
                    'visited'   => true,
                    'first_seen' => $row->first_seen,
                ];
            }
        }

        $stamps = array_values($stamps);

        $got = array_values(array_filter($stamps, fn ($s) => $s['visited']));
        $missing = array_values(array_filter($stamps, fn ($s) => !$s['visited']));

        usort($got, fn ($a, $b) => strcmp((string) $b['first_seen'], (string) $a['first_seen']));
        usort($missing, fn ($a, $b) => strcmp($a['country'].$a['iata'], $b['country'].$b['iata']));

        return [
            'stamps'   => $got,
            'missing'  => $missing,
            'visited'  => count($got),
            'total'    => count($stamps),
            'countries' => count(array_unique(array_column($got, 'country'))),
            'countries_total' => count(array_unique(array_column($stamps, 'country'))),
            'tours'    => $this->completedTours($user),
        ];
    }

    /**
     * Aeropuertos internacionales visitados, con la fecha de la primera visita.
     *
     * @return Collection<string, object>
     */
    private function visited(User $user): Collection
    {
        return DB::table('pireps')
            ->join('airports', 'airports.id', '=', 'pireps.arr_airport_id')
            ->where('pireps.user_id', $user->id)
            ->where('pireps.state', self::ACCEPTED)
            ->where('airports.country', '!=', self::HOME_COUNTRY)
            ->selectRaw('airports.id as id, airports.iata as iata, airports.name as name, airports.country as country')
            ->selectRaw('min(pireps.submitted_at) as first_seen')
            ->groupBy('airports.id', 'airports.iata', 'airports.name', 'airports.country')
            ->get()
            ->keyBy('id');
    }

    /**
     * Destinos internacionales de la aerolinea: los de la RED ACTIVA.
     *
     * No se usan los PIREPs para el universo: el import del CrewSystem trajo cientos de
     * aeropuertos por los que ya no se vuela y el album mostraba 413 destinos "pendientes",
     * casi todos inalcanzables. Los aeropuertos visitados que ya no esten en la red se anaden
     * igualmente en forUser(), asi que el denominador nunca queda por debajo de lo visitado.
     */
    private function universe(): Collection
    {
        return DB::table('airports')
            ->where('airports.country', '!=', self::HOME_COUNTRY)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))->from('flights')
                    ->whereColumn('flights.arr_airport_id', 'airports.id')
                    ->where('flights.active', 1);
            })
            ->orderBy('airports.country')
            ->orderBy('airports.iata')
            ->get(['airports.id', 'airports.iata', 'airports.name', 'airports.country']);
    }

    /**
     * Tours completados por el piloto: ha volado todos los tramos del tour.
     *
     * @return array<int, array{code: string, legs: int}>
     */
    private function completedTours(User $user): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('disposable_tour_flights')) {
            return [];
        }

        $flown = DB::table('pireps')
            ->where('pireps.user_id', $user->id)
            ->where('pireps.state', self::ACCEPTED)
            ->whereNotNull('pireps.flight_id')
            ->pluck('pireps.flight_id')
            ->unique()
            ->all();

        if (empty($flown)) {
            return [];
        }

        $out = [];
        foreach (DB::table('disposable_tours')->where('active', 1)->get() as $tour) {
            $legs = DB::table('disposable_tour_flights')->where('tour_id', $tour->id)->pluck('flight_id')->all();

            if ($legs && count(array_diff($legs, $flown)) === 0) {
                $out[] = ['code' => (string) ($tour->tour_code ?? $tour->id), 'legs' => count($legs)];
            }
        }

        return $out;
    }
}
