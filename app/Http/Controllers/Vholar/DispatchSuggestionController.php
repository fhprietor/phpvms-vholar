<?php

namespace App\Http\Controllers\Vholar;

use App\Contracts\Controller;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Services\DispatchSuggestionService;
use App\Services\SimBriefAirframeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint del sugerido de PAX/carga del modal de despacho.
 *
 * Devuelve JSON porque el modal lo consume por `fetch()` desde el navegador.
 * El avion reservado (matricula) es la fuente del tipo y de la subflota; si no
 * llega, se cae al tipo declarado en el boton y a la primera subflota de ese
 * tipo, para que el endpoint nunca devuelva un 500 por un data-* que falte.
 *
 * Ademas del sugerido, la respuesta lleva el `type` que hay que mandar a SimBrief
 * para seleccionar el airframe: el del piloto si lo ha guardado en su perfil, y
 * si no el del avion/subflota, y si no el ICAO pelado. Sin campo de perfil todo
 * sigue funcionando igual que antes.
 */
class DispatchSuggestionController extends Controller
{
    public function __construct(
        private readonly DispatchSuggestionService $suggestionSvc,
        private readonly SimBriefAirframeService $airframeSvc
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

        $aircraft = $this->resolveAircraft($data);
        $user = $request->user();

        $suggestion = $this->suggestionSvc->suggest($flight, $aircraft, $user);
        $simbrief = $this->airframeSvc->resolveType($aircraft, $user, $data['actype'] ?? null);

        $notes = $suggestion['notes'] ?? [];
        if ($simbrief['note'] !== null) {
            $notes[] = $simbrief['note'];
        }

        return response()->json($this->payload($suggestion, $simbrief, $notes));
    }

    /**
     * Arma la respuesta.
     *
     * OJO con `+`: no pisa claves ya existentes, asi que las notas del sugerido y
     * el aviso del airframe se asignan al final. Con `+ ['notes' => $notes]` el
     * aviso del airframe se perdia en silencio (las notas del sugerido ya
     * ocupaban la clave).
     *
     * @param array<string, mixed>                                              $suggestion
     * @param array{type: string, airframe: string|null, source: string, note: array<string, string>|null} $simbrief
     * @param array<int, array{level: string, text: string}>                    $notes
     *
     * @return array<string, mixed>
     */
    private function payload(array $suggestion, array $simbrief, array $notes): array
    {
        $payload = [
            'ok'       => true,
            'simbrief' => [
                'type'     => $simbrief['type'],
                'airframe' => $simbrief['airframe'],
                'source'   => $simbrief['source'],
            ],
        ] + $suggestion;

        $payload['notes'] = $notes;

        return $payload;
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
