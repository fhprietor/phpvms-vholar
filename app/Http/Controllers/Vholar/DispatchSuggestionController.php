<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Services\DispatchSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint del sugerido de PAX/carga del modal de despacho.
 *
 * Devuelve JSON porque el modal lo consume por `fetch()` desde el navegador.
 * El avion reservado (matricula) es la fuente del tipo y de la subflota; si no
 * llega, se cae al tipo declarado en el boton y a la primera subflota de ese
 * tipo, para que el endpoint nunca devuelva un 500 por un data-* que falte.
 */
class DispatchSuggestionController extends Controller
{
    public function __construct(
        private readonly DispatchSuggestionService $suggestionSvc
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'flight_id'   => 'required|string|max:36',
            'aircraft_id' => 'nullable|string|max:36',
            'actype'      => 'nullable|string|max:8',
            'acreg'       => 'nullable|string|max:16',
        ]);

        $flight = Flight::with('dpt_airport')->find($data['flight_id']);
        if ($flight === null) {
            return response()->json(['ok' => false, 'error' => 'flight_not_found'], 404);
        }

        $suggestion = $this->suggestionSvc->suggest(
            $flight,
            $this->resolveAircraft($data),
            $request->user()
        );

        return response()->json(['ok' => true] + $suggestion);
    }

    /**
     * Avion reservado: por id, si no por matricula y, como ultimo recurso, una
     * subflota del tipo declarado (sin matricula).
     *
     * @param array<string, string|null> $data
     */
    private function resolveAircraft(array $data): ?Aircraft
    {
        if (!empty($data['aircraft_id'])) {
            $aircraft = Aircraft::with('subfleet')->find($data['aircraft_id']);
            if ($aircraft !== null) {
                return $aircraft;
            }
        }

        if (!empty($data['acreg'])) {
            $aircraft = Aircraft::with('subfleet')->where('registration', $data['acreg'])->first();
            if ($aircraft !== null) {
                return $aircraft;
            }
        }

        $type = strtoupper((string) ($data['actype'] ?? ''));
        if ($type === '') {
            return null;
        }

        $subfleet = Subfleet::where('type', $type)->first();

        // Avion transitorio: solo aporta tipo y subflota al sugerido.
        $aircraft = new Aircraft();
        $aircraft->icao = $type;
        if ($subfleet !== null) {
            $aircraft->setRelation('subfleet', $subfleet);
        }

        return $aircraft;
    }
}
