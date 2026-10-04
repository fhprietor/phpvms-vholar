<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mapa publico de la red: una linea por ruta (origen-destino) coloreada por categoria
 * y un popup al pasar el raton con los vuelos que cubren esa ruta.
 *
 * Categorias (ajustables aqui abajo):
 *  - express : la ruta tiene algun vuelo que SOLO se puede volar con carguero
 *  - heavy   : ruta de >= 2500 nm
 *  - airliner: entre 800 y 2500 nm
 *  - regional: menos de 800 nm
 *
 * Nota: la aerolinea asigna hasta 16 subflotas por vuelo, asi que "la aeronave de la
 * ruta" no distingue nada; se clasifica por distancia y por carguero. Si algun dia se
 * quiere clasificar por familia de aeronave, basta cambiar `categoryFor()`.
 */
class NetworkMapController extends Controller
{
    public const CACHE_KEY = 'vholar.network_map';

    public const CACHE_MINUTES = 60;

    private const HEAVY_NM = 2500;

    private const AIRLINER_NM = 800;

    // Cargueros de la flota (A300-600RF, A30F y 777F)
    private const FREIGHTERS = ['B77F', 'A30F', 'A306'];

    private const COLORS = [
        'heavy'    => '#7E57C2',
        'airliner' => '#4A90D9',
        'regional' => '#4CAF76',
        'express'  => '#EF6C00',
    ];

    public function index(): View
    {
        $data = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->buildNetwork()
        );

        return view('vholar::network-map', $data);
    }

    private function buildNetwork(): array
    {
        $flights = DB::table('flights')
            ->join('airports as dpt', 'dpt.id', '=', 'flights.dpt_airport_id')
            ->join('airports as arr', 'arr.id', '=', 'flights.arr_airport_id')
            ->where('flights.active', 1)
            ->whereNotNull('dpt.lat')->whereNotNull('dpt.lon')
            ->whereNotNull('arr.lat')->whereNotNull('arr.lon')
            ->get([
                'flights.id',
                'flights.flight_number',
                'flights.distance',
                'flights.dpt_airport_id',
                'flights.arr_airport_id',
                'dpt.name as dpt_name',
                'dpt.lat as dpt_lat',
                'dpt.lon as dpt_lon',
                'arr.name as arr_name',
                'arr.lat as arr_lat',
                'arr.lon as arr_lon',
            ]);

        // Subflotas por vuelo: para saber si un vuelo es exclusivo de carga
        $subfleetsByFlight = DB::table('flight_subfleet')
            ->join('subfleets', 'subfleets.id', '=', 'flight_subfleet.subfleet_id')
            ->whereIn('flight_subfleet.flight_id', $flights->pluck('id')->all())
            ->get(['flight_subfleet.flight_id', 'subfleets.type'])
            ->groupBy('flight_id')
            ->map(fn ($rows) => $rows->pluck('type')->unique()->values()->all());

        $routes = [];

        foreach ($flights as $f) {
            $key = $f->dpt_airport_id.'-'.$f->arr_airport_id;

            if (!isset($routes[$key])) {
                $routes[$key] = [
                    'dpt'      => $f->dpt_airport_id,
                    'arr'      => $f->arr_airport_id,
                    'dpt_name' => $f->dpt_name,
                    'arr_name' => $f->arr_name,
                    'dpt_lat'  => (float) $f->dpt_lat,
                    'dpt_lon'  => (float) $f->dpt_lon,
                    'arr_lat'  => (float) $f->arr_lat,
                    'arr_lon'  => (float) $f->arr_lon,
                    'distance' => 0.0,
                    'flights'  => [],
                    'freighter_only' => false,
                ];
            }

            $types = $subfleetsByFlight[$f->id] ?? [];
            $freighterOnly = count($types) > 0 && count(array_diff($types, self::FREIGHTERS)) === 0;

            $routes[$key]['distance'] = max($routes[$key]['distance'], (float) $f->distance);
            $routes[$key]['freighter_only'] = $routes[$key]['freighter_only'] || $freighterOnly;
            $routes[$key]['flights'][] = [
                'id'     => $f->id,
                'number' => $f->flight_number,
            ];
        }

        $legend = ['heavy' => 0, 'airliner' => 0, 'regional' => 0, 'express' => 0];

        foreach ($routes as $key => $route) {
            $category = $this->categoryFor($route['distance'], $route['freighter_only']);

            usort($route['flights'], fn ($a, $b) => $a['number'] <=> $b['number']);

            $routes[$key]['category'] = $category;
            $routes[$key]['color'] = self::COLORS[$category];
            $routes[$key]['distance'] = (int) round($route['distance']);

            $legend[$category]++;
        }

        return [
            'routes'       => array_values($routes),
            'legend'       => $legend,
            'totalFlights' => $flights->count(),
        ];
    }

    /**
     * Categoria de una ruta. Cambiar aqui si se prefiere clasificar por aeronave.
     */
    private function categoryFor(float $distance, bool $freighterOnly): string
    {
        if ($freighterOnly) {
            return 'express';
        }

        if ($distance >= self::HEAVY_NM) {
            return 'heavy';
        }

        if ($distance >= self::AIRLINER_NM) {
            return 'airliner';
        }

        return 'regional';
    }
}
