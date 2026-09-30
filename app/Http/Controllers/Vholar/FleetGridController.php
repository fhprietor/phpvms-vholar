<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Models\Aircraft;
use App\Models\Subfleet;
use Illuminate\View\View;

class FleetGridController extends Controller
{
    public function fleetMap(): View
    {
        $aircraft = Aircraft::with(['airport', 'subfleet'])
            ->whereNotNull('airport_id')
            ->get();

        $airportFleet = [];
        foreach ($aircraft as $ac) {
            if (!$ac->airport || !$ac->airport->lat || !$ac->airport->lon) {
                continue;
            }
            $apt   = $ac->airport;
            $aptId = $apt->id;
            if (!isset($airportFleet[$aptId])) {
                $airportFleet[$aptId] = [
                    'id'    => $apt->id,
                    'iata'  => $apt->iata ?? '',
                    'name'  => $apt->name ?? '',
                    'lat'   => (float) $apt->lat,
                    'lon'   => (float) $apt->lon,
                    'types' => [],
                    'total' => 0,
                ];
            }
            $type = $ac->subfleet->type ?? $ac->icao ?? 'N/A';
            $airportFleet[$aptId]['types'][$type] = ($airportFleet[$aptId]['types'][$type] ?? 0) + 1;
            $airportFleet[$aptId]['total']++;
        }

        // Sort types within each airport by count desc
        foreach ($airportFleet as &$entry) {
            arsort($entry['types']);
        }
        unset($entry);

        return view('vholar::fleet.map', compact('airportFleet'));
    }

    public function index(): View
    {
        // Obtener subfleets con aeronaves (eager loading)
        $subfleets = Subfleet::with(['aircraft' => function ($query) {
            $query->orderBy('registration');
        }])
        ->whereHas('airline', function ($q) {
            $q->where('active', 1);
        })
        ->whereHas('aircraft') // solo subfleets con al menos un avión
        ->orderBy('name')
        ->get();

        // Unidades desde DisposableBasic (o fallback)
        $units = function_exists('DB_GetUnits') ? DB_GetUnits() : ['fuel' => setting('units.fuel')];

        return view('vholar::fleet.grid', [
            'subfleets' => $subfleets,
            'units'     => $units,
        ]);
    }
}