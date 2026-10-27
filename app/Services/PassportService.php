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
     * Idioma propio de cada pais (para el sello). Solo los que no son evidentes o cuyos
     * alfabetos se ven: el resto cae al idioma de la aplicacion.
     */
    private const NATIVE_LANG = [
        'CO' => 'es', 'MX' => 'es', 'AR' => 'es', 'CL' => 'es', 'PE' => 'es', 'EC' => 'es',
        'VE' => 'es', 'PA' => 'es', 'CR' => 'es', 'GT' => 'es', 'SV' => 'es', 'HN' => 'es',
        'NI' => 'es', 'DO' => 'es', 'PR' => 'es', 'CU' => 'es', 'BO' => 'es', 'PY' => 'es',
        'UY' => 'es', 'ES' => 'es',
        'BR' => 'pt', 'PT' => 'pt', 'FR' => 'fr', 'DE' => 'de', 'AT' => 'de', 'CH' => 'de',
        'IT' => 'it', 'NL' => 'nl', 'BE' => 'nl', 'SE' => 'sv', 'NO' => 'no', 'DK' => 'da',
        'FI' => 'fi', 'PL' => 'pl', 'CZ' => 'cs', 'RO' => 'ro', 'HU' => 'hu', 'GR' => 'el',
        'TR' => 'tr', 'RU' => 'ru', 'UA' => 'uk', 'BG' => 'bg',
        'EG' => 'ar', 'MA' => 'ar', 'DZ' => 'ar', 'TN' => 'ar', 'AE' => 'ar', 'SA' => 'ar',
        'QA' => 'ar', 'KW' => 'ar', 'JO' => 'ar', 'LB' => 'ar', 'IL' => 'he', 'IR' => 'fa',
        'KE' => 'sw', 'ZA' => 'en', 'NG' => 'en',
        'CN' => 'zh', 'JP' => 'ja', 'KR' => 'ko', 'TH' => 'th', 'VN' => 'vi', 'MY' => 'ms',
        'ID' => 'id', 'PH' => 'fil', 'IN' => 'hi',
        'US' => 'en', 'CA' => 'en', 'GB' => 'en', 'IE' => 'en', 'AU' => 'en', 'NZ' => 'en',
    ];

    /** La palabra "inmigracion" en cada idioma, corta para que quepa en el sello. */
    private const NATIVE_LABEL = [
        'en'  => 'IMMIGRATION', 'es' => 'INMIGRACIÓN', 'pt' => 'IMIGRAÇÃO', 'fr' => 'IMMIGRATION',
        'de'  => 'EINREISE', 'it' => 'IMMIGRAZIONE', 'nl' => 'IMMIGRATIE', 'sv' => 'INRESA',
        'no'  => 'INNREISE', 'da' => 'INDREJSE', 'fi' => 'MAAHANM.', 'pl' => 'WJAZD',
        'cs'  => 'VSTUP', 'ro' => 'INTRARE', 'hu' => 'BELÉPÉS', 'el' => 'ΕΛΕΓΧΟΣ',
        'tr'  => 'GİRİŞ', 'ru' => 'ВЪЕЗД', 'uk' => "В'ЇЗД", 'bg' => 'ВЛИЗАНЕ',
        'ar'  => 'الجوازات', 'he' => 'כניסה', 'fa' => 'ورود', 'sw' => 'UHAMIAJI',
        'zh'  => '入境', 'ja' => '入国', 'ko' => '입국', 'th' => 'เข้าเมือง', 'vi' => 'NHẬP CẢNH',
        'ms'  => 'IMIGRESEN', 'id' => 'IMIGRASI', 'fil' => 'IMMIGRATION', 'hi' => 'आगमन',
    ];

    /** Formas del sello y rotaciones/rotuladores: deterministas por pais. */
    private const SHAPES = ['oval', 'circle', 'rect', 'round'];
    private const ROTATIONS = [-7, 5, -4, 8];
    private const INKS = ['--vh-accent', '--vh-danger', '--vh-warning'];

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
            // Puerto de entrada: el aeropuerto de la primera visita (el ultimo de la lista,
            // que va ordenada de mas reciente a mas antigua).
            $first = end($country['airports']);
            $country['first_airport'] = is_array($first) ? $first['iata'] : null;
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
     * Nombre del pais en su propio idioma (intl), para el sello. Null si no hay dato.
     */
    private function nativeName(string $iso, ?string $lang): ?string
    {
        if (!$lang || !class_exists(\Locale::class)) {
            return null;
        }

        try {
            $name = \Locale::getDisplayRegion('-'.strtoupper($iso), $lang);

            if (is_string($name) && $name !== '' && strtoupper($name) !== strtoupper($iso)) {
                return $name;
            }
        } catch (\Throwable $e) {
            // sin dato nativo
        }

        return null;
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
            'first_airport' => null,
            'total_flights' => 0,
            'lang'          => self::NATIVE_LANG[$iso] ?? null,
            'native_name'   => $this->nativeName($iso, self::NATIVE_LANG[$iso] ?? null),
            'label'         => self::NATIVE_LABEL[self::NATIVE_LANG[$iso] ?? ''] ?? null,
            'shape'         => self::SHAPES[crc32($iso.'shape') % 4],
            'rotate'        => self::ROTATIONS[crc32($iso.'rotate') % 4],
            'ink'           => self::INKS[crc32($iso.'ink') % 3],
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
