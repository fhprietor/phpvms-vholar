<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasaporte del piloto: paises visitados y, dentro de cada uno, los aeropuertos, los vuelos
 * y los tipos de avion usados.
 *
 * No hay tabla de sellos ni listener: se CALCULA desde los PIREPs aceptados. Asi es
 * retroactivo (todo el mundo los tiene ya), no se desincroniza con los vuelos y no hay
 * migracion que mantener.
 *
 * "Internacional" = aeropuerto cuyo pais no es la base (HOME_COUNTRY). El universo de sellos
 * posibles son los destinos de la RED ACTIVA; los aeropuertos ya visitados fuera de la red se
 * anaden igualmente, para que el denominador nunca quede por debajo de lo visitado.
 */
class PassportService extends Service
{
    /** Pais base de la aerolinea: los sellos son para el resto. */
    public const HOME_COUNTRY = 'CO';

    /** PirepState::ACCEPTED */
    private const ACCEPTED = 2;

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $visits = $this->visits($user);

        $flights = $this->flights($user)->groupBy('country');

        $countries = [];

        foreach ($visits as $visit) {
            $iso = strtoupper((string) $visit->country);

            if (!isset($countries[$iso])) {
                $countries[$iso] = $this->emptyCountry($iso);
            }

            $countries[$iso]['airports'][] = [
                'iata'       => $visit->iata ?: $visit->id,
                'name'       => $visit->name,
                'first_seen' => $visit->first_seen,
            ];

            if ($countries[$iso]['first_seen'] === null || $visit->first_seen < $countries[$iso]['first_seen']) {
                $countries[$iso]['first_seen'] = $visit->first_seen;
            }
        }

        foreach ($flights as $iso => $rows) {
            $iso = strtoupper((string) $iso);

            if (!isset($countries[$iso])) {
                $countries[$iso] = $this->emptyCountry($iso);
            }

            foreach ($rows as $row) {
                $type = $row->aircraft_type ?: '—';

                $countries[$iso]['flights'][] = [
                    'number'     => $row->flight_number,
                    'dpt'        => $row->dpt_airport_id,
                    'arr'        => $row->arr_airport_id,
                    'date'       => $row->submitted_at,
                    'type'       => $type,
                    'reg'        => $row->registration,
                ];

                $countries[$iso]['types'][$type] = ($countries[$iso]['types'][$type] ?? 0) + 1;
            }
        }

        foreach ($countries as &$country) {
            usort($country['airports'], fn ($a, $b) => strcmp((string) $b['first_seen'], (string) $a['first_seen']));
            arsort($country['types']);
            $country['total_flights'] = count($country['flights']);
        }
        unset($country);

        uasort($countries, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // El pasaporte se mide contra TODOS los paises del mundo (los que tienen aeropuerto en
        // la base), no contra la red: la gracia es ir coleccionando paises.
        $visitedIsos = array_keys($countries);
        $worldIsos = $this->worldCountries();
        $allIsos = array_unique(array_merge($worldIsos, $visitedIsos));

        $missing = [];
        foreach (array_diff($allIsos, $visitedIsos) as $iso) {
            $missing[$iso] = $this->countryName((string) $iso);
        }
        asort($missing);

        return [
            'countries'         => array_values($countries),
            'missing'           => $missing,
            'visited_airports'  => count($visits),
            'countries_visited' => count($countries),
            'countries_total'   => count($allIsos),
            'tours'             => $this->completedTours($user),
        ];
    }

    /**
     * Paises del mundo: los codigos ISO-2 que aparecen en la tabla de aeropuertos.
     *
     * Se filtra por longitud 2 porque el import de aeropuertos dejo una fila con
     * `country = "country"` (cabecera del CSV).
     *
     * @return array<int, string>
     */
    private function worldCountries(): array
    {
        return DB::table('airports')
            ->whereRaw('char_length(country) = 2')
            ->distinct()
            ->pluck('country')
            ->map(fn ($iso) => strtoupper((string) $iso))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Nombre del pais en el idioma activo (intl); si no esta, el codigo ISO.
     */
    public function countryName(string $iso): string
    {
        try {
            if (class_exists(\Locale::class)) {
                $name = \Locale::getDisplayRegion('-'.strtoupper($iso), app()->getLocale());

                if (is_string($name) && $name !== '' && strtoupper($name) !== strtoupper($iso)) {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            // sin intl: se usa el codigo
        }

        return strtoupper($iso);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCountry(string $iso): array
    {
        return [
            'country'       => $iso,
            'name'          => $this->countryName($iso),
            'airports'      => [],
            'flights'       => [],
            'types'         => [],
            'first_seen'    => null,
            'total_flights' => 0,
        ];
    }

    /**
     * Aeropuertos internacionales visitados, con la fecha de la primera visita.
     *
     * @return Collection<int, object>
     */
    private function visits(User $user): Collection
    {
        return DB::table('pireps')
            ->join('airports', 'airports.id', '=', 'pireps.arr_airport_id')
            ->where('pireps.user_id', $user->id)
            ->where('pireps.state', self::ACCEPTED)
            ->where('airports.country', '!=', self::HOME_COUNTRY)
            ->selectRaw('airports.id as id, airports.iata as iata, airports.name as name, airports.country as country')
            ->selectRaw('min(pireps.submitted_at) as first_seen')
            ->groupBy('airports.id', 'airports.iata', 'airports.name', 'airports.country')
            ->get();
    }

    /**
     * Vuelos internacionales del piloto, con el tipo de avion (subflota) y la matricula.
     *
     * @return Collection<int, object>
     */
    private function flights(User $user): Collection
    {
        return DB::table('pireps')
            ->join('airports', 'airports.id', '=', 'pireps.arr_airport_id')
            ->leftJoin('aircraft', 'aircraft.id', '=', 'pireps.aircraft_id')
            ->leftJoin('subfleets', 'subfleets.id', '=', 'aircraft.subfleet_id')
            ->where('pireps.user_id', $user->id)
            ->where('pireps.state', self::ACCEPTED)
            ->where('airports.country', '!=', self::HOME_COUNTRY)
            ->orderByDesc('pireps.submitted_at')
            ->get([
                'pireps.flight_number',
                'pireps.dpt_airport_id',
                'pireps.arr_airport_id',
                'pireps.submitted_at',
                'airports.country',
                'subfleets.type as aircraft_type',
                'aircraft.registration',
            ]);
    }

    /**
     * Tours completados: todos los tramos volados por el piloto.
     *
     * @return array<int, array{code: string, legs: int}>
     */
    private function completedTours(User $user): array
    {
        if (!Schema::hasTable('disposable_tour_flights')) {
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
