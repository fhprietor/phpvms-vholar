<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\Enums\FlightType;
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
 * Los parametros siguen a la tabla oficial de SimBrief y al formulario del core
 * de phpVMS, que es la referencia dentro del proyecto:
 *
 *   - `maps` = `detail` (el `<select>` del formulario usa detail/simple/none).
 *     El modal mandaba `detailed`, que no es un valor documentado.
 *   - sin `static_url`: no existe en ninguna tabla; el formulario del core usa
 *     `static_id`, que es otra cosa (y no la usamos).
 *   - `extrarmk` (Extra FPL Info, Item 18) sale del setting
 *     `simbrief.extrarmk`, editable en Admin > Settings. El modal lo llevaba
 *     escrito a mano; ahora es configurable y vacio significa no mandarlo.
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

    /** Libras por kilo: los pesos de `acdata` van en libras. */
    private const LB_PER_KG = 2.20462;

    /**
     * Parametros de despacho para un vuelo y un avion.
     *
     * Solo se anaden `pax`, `cargo`, `route` y `acdata` cuando tienen valor: es lo
     * que hace el modal, y evita mandar un `route` vacio que SimBrief
     * interpretaria como "ruta vacia" en vez de "genera la tuya".
     *
     * @param float|null $baggageKg Equipaje medio por pasajero (kg), de
     *                              `DispatchSuggestionService`. Null = no se manda
     *                              `acdata` y SimBrief usa sus pesos por defecto.
     *
     * @return array<string, string>
     */
    public function params(
        Flight $flight,
        ?Aircraft $aircraft,
        ?User $user,
        int $pax = 0,
        int $cargo = 0,
        ?string $depTime = null,
        ?float $baggageKg = null
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
            'maps'       => 'detail',
            'deph'       => $deph,
            'depm'       => $depm,
            'flighttype' => 's',
            'fl'         => (string) $this->flightLevel($flight, $type),
        ];

        if ($pax > 0) {
            $params['pax'] = (string) $pax;
        }

        // OJO CON LA UNIDAD DE `cargo`: va en MILES de la unidad seleccionada, no
        // en kg. Lo hace asi SimBrief en su propio codigo (al compartir un vuelo
        // divide `cargo`, `manualpayload` y `manualzfw` entre 1e3), y los
        // ejemplos de su documentacion lo confirman (`cargo = 5.0` son 5.000 kg).
        // Mandarlo en kg lo interpreta como 7.991.000 kg y lo recorta al maximo
        // permitido, que es justo el sintoma que veiamos: Freight = max payload.
        if ($cargo > 0) {
            $params['cargo'] = (string) round($cargo / 1000, 3);
        }

        // Item 18 del plan (parametro `extrarmk`), configurable en Admin >
        // Settings. Vacio = no se manda.
        $extra = trim((string) setting('simbrief.extrarmk', ''));
        if ($extra !== '') {
            $params['extrarmk'] = $extra;
        }

        $route = trim((string) ($flight->getRawOriginal('route') ?? ''));
        if ($route !== '') {
            $params['route'] = $route;
        }

        // Pesos medios de pasajero y equipaje: asi SimBrief planifica con
        // NUESTROS numeros y no recorta la carga con los suyos.
        $acdata = $this->acdata($flight, $baggageKg);
        if ($acdata !== null) {
            $params['acdata'] = $acdata;
        }

        return $params;
    }

    /**
     * `acdata` de SimBrief con los pesos medios por pasajero.
     *
     * POR QUE HACE FALTA
     * ------------------
     * SimBrief, si no le dices otra cosa, planifica con 175 lb de pasajero y
     * 55 lb (25 kg) de equipaje. Con la politica de equipaje por clase de la
     * aerolinea (10/23/46 kg, media ~13,75 kg) eso son casi 2 t de mas en un
     * vuelo de 166 pax, y SimBrief recorta la carga para respetar el MZFW. Aqui
     * se le mandan los pesos de verdad.
     *
     * VA EN LIBRAS: los pesos de `acdata` son libras (el resto de pesos del
     * objeto, en cambio, van en miles de libras: no se usan aqui).
     *
     * @param float|null $baggageKg Equipaje medio por pasajero (kg)
     */
    public function acdata(Flight $flight, ?float $baggageKg): ?string
    {
        $paxWeightLb = $this->paxWeightLb($flight);

        $data = [];
        if ($paxWeightLb > 0) {
            $data['paxwgt'] = (int) round($paxWeightLb);
        }

        if ($baggageKg !== null) {
            $data['bagwgt'] = (int) round($baggageKg * self::LB_PER_KG);
        }

        return $data === [] ? null : (string) json_encode($data);
    }

    /**
     * Peso medio de pasajero (libras) del ajuste de la aerolinea, distinguiendo
     * charter de vuelo regular, igual que el formulario del core.
     */
    private function paxWeightLb(Flight $flight): float
    {
        $isCharter = $flight->flight_type === FlightType::CHARTER_PAX_ONLY;

        return (float) setting(
            $isCharter ? 'simbrief.charter_pax_weight' : 'simbrief.noncharter_pax_weight',
            $isCharter ? 168 : 170
        );
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
