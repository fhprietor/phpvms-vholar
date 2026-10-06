<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;
use Carbon\Carbon;

/**
 * URL de despacho de SimBrief (`dispatch.simbrief.com/options/custom`).
 *
 * POR QUE VIVE EN EL SERVIDOR
 * ---------------------------
 * Hasta ahora la montaba el JavaScript del modal, y vmsOpenAcars la monta por su
 * cuenta: dos implementaciones de los mismos parametros. Cualquier correccion
 * hay que replicarla en cada una, y ese es el fallo que ya nos costo caro: el
 * `fl` viajaba en centenas (330 para 33.000 ft) cuando SimBrief lo documenta en
 * PIES (`34000, FL340`). Aqui queda una sola definicion, que es la que devuelve
 * el endpoint del API.
 *
 * Los parametros replican a proposito los del modal, para que el cliente
 * despache igual que la web. Tres valores siguen pendientes de decidir porque no
 * cuadran con la tabla oficial de SimBrief; cuando se decidan, se cambian AQUI:
 *
 *   - `maps=detailed`: la tabla documenta `detail, simple, none`.
 *   - `static_url=1` : no aparece en ninguna de las dos tablas oficiales.
 *   - `extrarmk`     : incluye un `CS/VHOLAR IVAOVA/<aerolinea>` que no es un
 *                      callsign valido (el campo CS/ espera el indicativo).
 */
class SimBriefUrlService extends Service
{
    public const BASE_URL = 'https://dispatch.simbrief.com/options/custom';

    /** Margen sobre la hora actual para la salida por defecto (igual que el modal). */
    private const DEPARTURE_MARGIN_MINUTES = 40;

    /** Nivel de crucero por defecto si el vuelo no lo trae, por familia de equipo. */
    private const DEFAULT_LEVELS = [
        '/^(A38|B74|A35|B78|A33|B76|B77)/' => 36000,
        '/^(A32|B73)/'                     => 35000,
        '/^(AT[457]|DH8)/'                 => 18000,
        '/^(E[12]|CRJ)/'                   => 28000,
    ];

    private const DEFAULT_LEVEL = 33000;

    /** Cost index por defecto, igual que el modal. */
    private const DEFAULT_COST_INDEX = '30';

    /**
     * Parametros de despacho para un vuelo y un avion.
     *
     * Solo se anaden `pax`, `cargo` y `route` cuando tienen valor: es lo que hace
     * el modal, y evita mandar un `route` vacio que SimBrief interpretaria como
     * "ruta vacia" en vez de "genera la tuya".
     *
     * @return array<string, string>
     */
    public function params(
        Flight $flight,
        ?Aircraft $aircraft,
        ?User $user,
        int $pax = 0,
        int $cargo = 0,
        ?string $depTime = null
    ): array {
        $airline = (string) (optional($flight->airline)->icao ?? '');
        $type = strtoupper(trim((string) ($aircraft?->icao ?: ($aircraft?->subfleet?->type ?? ''))));
        [$deph, $depm] = $this->departure($depTime);

        $params = [
            'airline'    => $airline,
            'fltnum'     => (string) $flight->flight_number,
            'orig'       => (string) $flight->dpt_airport_id,
            'dest'       => (string) $flight->arr_airport_id,
            'type'       => $type,
            'reg'        => (string) ($aircraft?->registration ?? ''),
            'cpt'        => (string) ($user?->name ?? ''),
            'civalue'    => self::DEFAULT_COST_INDEX,
            'units'      => 'kgs',
            'maps'       => 'detailed',
            'static_url' => '1',
            'deph'       => $deph,
            'depm'       => $depm,
            'extrarmk'   => 'OPR/'.$airline.' CS/VHOLAR IVAOVA/'.$airline,
            'flighttype' => 's',
            'fl'         => (string) $this->flightLevel($flight, $type),
        ];

        if ($pax > 0) {
            $params['pax'] = (string) $pax;
        }

        if ($cargo > 0) {
            $params['cargo'] = (string) $cargo;
        }

        $route = trim((string) ($flight->getRawOriginal('route') ?? ''));
        if ($route !== '') {
            $params['route'] = $route;
        }

        return $params;
    }

    /**
     * @param array<string, string> $params
     */
    public function url(array $params): string
    {
        return self::BASE_URL.'?'.http_build_query($params);
    }

    /**
     * Nivel de crucero en PIES.
     *
     * Usa el que traiga el vuelo (`flights.level`, que en esta base esta a 0 en
     * la mayoria) y, si no lo hay, el mismo criterio que el modal por familia de
     * equipo. NUNCA en centenas: SimBrief documenta el parametro asi
     * (`Altitude | fl | 34000, FL340`).
     */
    public function flightLevel(Flight $flight, ?string $type = null): int
    {
        $level = (int) $flight->getRawOriginal('level');
        if ($level > 0) {
            return $level;
        }

        $type = strtoupper(trim((string) $type));

        foreach (self::DEFAULT_LEVELS as $pattern => $default) {
            if (preg_match($pattern, $type) === 1) {
                return $default;
            }
        }

        return self::DEFAULT_LEVEL;
    }

    /**
     * Hora de salida en UTC como [hh, mm].
     *
     * Acepta `HHMM` o `HH:MM`; si no, la hora actual mas el margen del modal.
     *
     * @return array{0: string, 1: string}
     */
    private function departure(?string $depTime): array
    {
        $raw = preg_replace('/[^0-9]/', '', (string) $depTime);

        if (is_string($raw) && strlen($raw) >= 3 && strlen($raw) <= 4) {
            $padded = str_pad($raw, 4, '0', STR_PAD_LEFT);
            $hour = (int) substr($padded, 0, 2);
            $minute = (int) substr($padded, 2, 2);

            if ($hour <= 23 && $minute <= 59) {
                return [sprintf('%02d', $hour), sprintf('%02d', $minute)];
            }
        }

        $now = Carbon::now('UTC')->addMinutes(self::DEPARTURE_MARGIN_MINUTES);

        return [$now->format('H'), $now->format('i')];
    }
}
