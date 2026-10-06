<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Aircraft;
use App\Models\Enums\FareType;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Support\Math;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sugerido de PAX y carga para el despacho (SimBrief).
 *
 * QUE ES
 * ------
 * Cuando el piloto ya ha reservado un vuelo regular y abre el despacho, se le
 * ofrece la carga MINIMA que hace rentable el trayecto con un margen del 20 %
 * sobre el coste, teniendo en cuenta la elevacion y la temperatura del
 * aeropuerto de salida (que recortan plazas). Excluye charter (CH) y ferry (FR)
 * por sufijo del numero de vuelo.
 *
 * EL COSTE ES EL DEL LIBRO, NO UNA APROXIMACION
 * ---------------------------------------------
 * El modelo original del traspaso usaba `horas * (cost_block_hour + 2.124) + 15`
 * con "2.124/h = fuel medido del libro". Ese 2.124 esta ~2,2x por debajo de la
 * realidad: el libro calcula `fuel_used (lbs internas) x precio ($/lb)` -- el
 * memo lo dice, `Fuel Cost (0.9/lbs)` -- y sobre los PIREPs de la era ACARS el
 * combustible sale a 4.452-5.209 $/h. `2.124` es exactamente lo que resulta de
 * dividir esos $/h entre 2,20462 (libras -> kilos), es decir, un desliz de
 * unidades. Ademas el coste por pasajero (15 $) y por kilo de carga (0,65 $) que
 * el libro SI debita no estaban en la formula, y el pago al piloto tampoco.
 *
 * Por eso aqui se reproduces el coste y el ingreso REALES:
 *
 *   coste  = horas x (cost_block_hour + combustible$/h + pagoPiloto$/h)
 *            + 15 (handling/tasas) + pax x costeTarifa + kgCarga x costeCarga
 *   ingreso= reparto de PAX por clases (el MISMO que PirepEconomicsService)
 *            + kgCarga x precioCarga
 *
 * y se busca el menor numero de PAX que cumpla `ingreso >= 1,20 x coste`. Como
 * el coste depende del propio PAX, la busqueda es directa (a lo sumo ~370
 * iteraciones) en vez de una division.
 *
 * DUPLICACION CONSCIENTE
 * ----------------------
 * La resolucion de tarifas y el reparto por clases replica
 * `PirepEconomicsService::subfleetFares()` y `mixFor()`. No se reutilizan
 * porque ese fichero esta en curso en otro hilo; si alli cambia el criterio,
 * aqui hay que acompanarlo. El objetivo es que el sugerido prediga EXACTAMENTE
 * lo que el PIREP acabara facturando con esa carga.
 */
class DispatchSuggestionService extends Service
{
    /** Margen objetivo sobre el coste: ingreso = 1,20 x coste. */
    public const MARGIN = 1.20;

    /** Coste fijo por vuelo (handling de salida/llegada y tasas). */
    public const FIXED_COST = 15.0;

    /**
     * Equipaje incluido por pasajero en cada clase (kg), de la mas barata a la
     * mas cara. Es la politica de la aerolinea (estilo Avianca): la tarifa basica
     * solo lleva 10 kg bajo el asiento, luego 1x23 y 2x23. Configurable en
     * Admin > Settings (`simbrief.baggage_by_class`).
     *
     * Todas las clases de un vuelo son las mismas tres tarifas ordenadas por
     * precio, asi que se aplica por POSICION.
     */
    private const DEFAULT_BAGGAGE_BY_CLASS = '10,23,46';

    /**
     * LA marca de una operacion no regular es `flights.route_code`.
     *
     * La pone el Centro de Operaciones: el formulario de charter
     * (`/vmsopenops/charter/create`, `CharterController::create()`) ofrece
     * exactamente estos codigos y los guarda en `flights.route_code`, creando el
     * vuelo oculto (`active=0`, `visible=0`), su subflota y el bid del piloto:
     *
     *   CH charter de pasaje | CA charter de carga | PS posicionamiento | FR ferry
     *
     * El mismo campo se puede fijar a mano en el formulario de vuelos del admin.
     * De ahi venia el "sufijo" del traspaso: el formulario tambien forma el
     * callsign como `<user_id><codigo>` ("80CH", "80FR"), y los PIREPs de esas
     * operaciones acaban con numeros como "025CH".
     *
     * El traspaso cerro CH y FR; CA y PS salen del MISMO formulario (charter de
     * carga y posicionamiento en vacio) y quedan excluidos tambien por decision
     * del mantenedor: ninguna de las cuatro es una operacion comercial de pasaje
     * a la que aplicar el minimo rentable.
     */
    private const EXCLUDED_ROUTE_CODES = ['CH', 'CA', 'PS', 'FR'];

    /**
     * Respaldo por sufijo para cuando el numero de vuelo no es un entero.
     * Hoy `flights.flight_number` es `int unsigned`, asi que no puede acabar en
     * CH/FR: la comprobacion no salta, pero cubre el dia en que se ensanche la
     * columna o llegue un dato heredado.
     */
    private const SUFFIX_CHARTER = 'CH';
    private const SUFFIX_FERRY = 'FR';

    /** ISA al nivel del mar, en grados Celsius. */
    private const ISA_SEA_LEVEL = 15.0;
    private const ISA_LAPSE_PER_1000FT = 2.0;

    /**
     * Asientos por tipo ICAO.
     *
     * Los 17 primeros son la tabla que fijo el mantenedor para esta funcion; los
     * ausentes se completan con `PirepRevenueService::SEATS` (AT46, BE58, DHC6,
     * A332, B772, B777). La base no guarda plazas: `subfleets` solo tiene
     * `cargo_capacity` y `fuel_capacity`. Los cargueros puros van a 0.
     */
    private const SEATS = [
        'A320' => 180, 'A20N' => 180, 'A21N' => 220, 'A319' => 140, 'A321' => 220,
        'B738' => 189, 'B38M' => 178, 'B737' => 140, 'B77L' => 300, 'B77W' => 350,
        'B789' => 290, 'A333' => 290, 'A339' => 290, 'A359' => 315, 'B763' => 270,
        'B764' => 245, 'AT76' => 70, 'DH8D' => 78,
        // Completados desde PirepRevenueService::SEATS (no estaban en el traspaso).
        'AT46' => 48, 'BE58' => 6, 'DHC6' => 19, 'A332' => 290, 'A330' => 290,
        'B772' => 320, 'B777' => 350,
        // Cargueros: sin plazas, todo va en bodega.
        'A306' => 0, 'A30F' => 0, 'B77F' => 0,
    ];

    /**
     * Consumo de combustible por tipo, en LIBRAS por hora de bloque.
     *
     * MEDIDO: mediana de `pireps.fuel_used / horas` sobre los PIREPs con log
     * ACARS (type=2). Se usa la mediana y no la media porque hay logs con
     * `fuel_used = 0` que hunden el promedio.
     *
     * ESTIMADO: tipos sin telemetria fiable. En A333 el libro da una mediana de
     * 4.211 lb/h que es imposible para un fuselaje ancho (los PIREPs importados
     * traen el combustible mal), asi que se usa el valor de folleto. En A306
     * solo hay una muestra.
     */
    private const FUEL_LB_PER_HOUR = [
        // --- Medidos (telemetria ACARS) ---
        'A20N' => 4120, 'A21N' => 3990, 'A319' => 4660, 'A320' => 5080, 'A321' => 5400,
        'B737' => 4260, 'B738' => 4920, 'B38M' => 4380, 'B77L' => 13030,
        'DHC6' => 447, 'BE58' => 122, 'DH8D' => 1410, 'AT46' => 1040,
        'B77F' => 15370, 'B77W' => 14580, 'A339' => 10270,
        // --- Estimados (sin telemetria fiable) ---
        'AT76' => 1300, 'A306' => 13000, 'A30F' => 13000, 'A332' => 11500,
        'A330' => 11500, 'A333' => 11500, 'A359' => 12500, 'B763' => 11000,
        'B764' => 12000, 'B772' => 15000, 'B777' => 15000, 'B789' => 11000,
    ];

    /** Consumo de reserva si el tipo no esta en la tabla, por familia. */
    private const FUEL_FALLBACK = [
        'regional' => 1300,
        'narrow'   => 4900,
        'wide'     => 13000,
    ];

    private const FAMILY_REGIONAL = ['BE58', 'DHC6', 'AT46', 'AT76', 'DH8D'];

    private const FAMILY_NARROW = ['A20N', 'A21N', 'A319', 'A320', 'A321', 'B38M', 'B737', 'B738'];

    public function __construct(
        private readonly AirportService $airportSvc
    ) {}

    /**
     * Calcula el sugerido.
     *
     * @param Flight       $flight       Vuelo reservado
     * @param Aircraft|null $aircraft    Avion reservado (de el salen subflota y tipo)
     * @param User|null    $user         Piloto, para el pago por rango
     * @param float|null   $temperatureC Temperatura en salida; si es null se lee del METAR
     *
     * @return array<string, mixed>
     */
    public function suggest(
        Flight $flight,
        ?Aircraft $aircraft = null,
        ?User $user = null,
        ?float $temperatureC = null
    ): array {
        $number = strtoupper(trim((string) ($flight->getRawOriginal('flight_number') ?: $flight->flight_number)));
        $routeCode = strtoupper(trim((string) ($flight->getRawOriginal('route_code') ?? '')));
        $subfleet = $aircraft?->subfleet;
        $type = strtoupper((string) ($subfleet?->type ?: ($aircraft?->icao ?? '')));

        $base = [
            'applicable'    => false,
            'flight_number' => $number,
            'flight_id'     => $flight->id,
            'aircraft_type' => $type ?: null,
            'route_code'    => $routeCode ?: null,
        ];

        // Operaciones no regulares: la marca la pone el Centro de Operaciones.
        if (in_array($routeCode, self::EXCLUDED_ROUTE_CODES, true)
            || ($number !== '' && (str_ends_with($number, self::SUFFIX_CHARTER) || str_ends_with($number, self::SUFFIX_FERRY)))
        ) {
            return $base + [
                'reason' => 'charter_or_ferry',
                'notes'  => [[
                    'level' => 'info',
                    'text'  => 'Operacion no regular ('.($routeCode ?: 'CH/FR').'): sin sugerido de carga.',
                ]],
            ];
        }

        $seats = self::SEATS[$type] ?? null;
        if ($seats === null) {
            return $base + [
                'reason' => 'unknown_type',
                'notes'  => [[
                    'level' => 'warn',
                    'text'  => 'Tipo de aeronave sin plazas conocidas ('.($type ?: 'desconocido').'): no se puede sugerir carga.',
                ]],
            ];
        }

        $hours = ((int) $flight->getRawOriginal('flight_time')) / 60;
        if ($hours <= 0) {
            return $base + [
                'reason' => 'no_block_time',
                'notes'  => [['level' => 'warn', 'text' => 'El vuelo no tiene tiempo de bloque: no se puede estimar el coste.']],
            ];
        }

        $dep = $flight->dpt_airport;
        $elevationFt = (float) ($dep?->elevation ?? 0);

        // --- Rendimiento: recorte de plazas por elevacion y temperatura ---
        $isaC = self::ISA_SEA_LEVEL - self::ISA_LAPSE_PER_1000FT * ($elevationFt / 1000.0);

        $tempSource = 'metar';
        if ($temperatureC === null) {
            $temperatureC = $dep !== null ? $this->metarTemperature($dep->id) : null;
        }
        if ($temperatureC === null) {
            $tempSource = 'unavailable';
        }

        $elevationPenalty = 0.01 * ($elevationFt / 1000.0);
        $tempDelta = $temperatureC !== null ? max(0.0, $temperatureC - $isaC) : 0.0;
        $tempPenalty = 0.01 * ($tempDelta / 5.0);
        $totalPenalty = min(0.90, $elevationPenalty + $tempPenalty);
        $seatsMax = (int) floor($seats * (1.0 - $totalPenalty));

        // --- Coste por hora ---
        $blockHour = (float) ($subfleet?->cost_block_hour ?? 0);
        $fuelPrice = (float) (setting('airports.default_jet_a_fuel_cost', 0.9) ?: 0.9);
        $fuelLbHour = $this->fuelBurn($type);
        $fuelHour = $fuelLbHour * $fuelPrice;

        $pilotFixed = (float) ($flight->pilot_pay ?? 0);
        $pilotHour = $pilotFixed > 0 ? 0.0 : $this->pilotPayPerHour($user, $subfleet);

        $fixedCost = $hours * ($blockHour + $fuelHour + $pilotHour) + $pilotFixed + self::FIXED_COST;

        // --- Tarifas: mismas que usara el PIREP ---
        [$paxFares, $cargoFare] = $this->resolveFares($flight, $subfleet);
        $mix = $this->mixFor($type);
        $baggageAvg = $this->averageBaggageKg($paxFares, $mix);

        // --- Menor PAX que cubre coste + 20 % ---
        $pax = 0;
        $cargo = 0;
        $targetReached = false;

        for ($n = 1; $n <= $seatsMax; $n++) {
            [$revenue, $paxCost] = $this->paxBalance($n, $paxFares, $mix);
            if ($revenue >= self::MARGIN * ($fixedCost + $paxCost)) {
                $pax = $n;
                $targetReached = true;
                break;
            }
        }

        // Si el avion no da para el objetivo con pasaje, se completa con carga.
        if (!$targetReached) {
            $pax = $seatsMax;
            [$paxRevenue, $paxCost] = $this->paxBalance($pax, $paxFares, $mix);
            $needed = self::MARGIN * ($fixedCost + $paxCost) - $paxRevenue;

            if ($cargoFare !== null) {
                $cargoUnitMargin = (float) $cargoFare->price - self::MARGIN * (float) $cargoFare->cost;
                if ($cargoUnitMargin > 0) {
                    $cargo = (int) max(0, ceil($needed / $cargoUnitMargin));
                    $targetReached = true;
                }
            }
        }

        [$paxRevenue, $paxCost] = $this->paxBalance($pax, $paxFares, $mix);
        $cargoRevenue = $cargoFare !== null ? $cargo * (float) $cargoFare->price : 0.0;
        $cargoCost = $cargoFare !== null ? $cargo * (float) $cargoFare->cost : 0.0;

        $revenue = $paxRevenue + $cargoRevenue;
        $cost = $fixedCost + $paxCost + $cargoCost;
        $marginPct = $cost > 0 ? round(($revenue / $cost - 1.0) * 100.0, 1) : null;

        return [
            'applicable'    => true,
            'reason'        => $targetReached ? 'ok' : 'target_unreachable',
            'flight_id'     => $flight->id,
            'flight_number' => $number,
            'route_code'    => $routeCode ?: null,
            'aircraft_type' => $type,
            'registration'  => $aircraft?->registration,
            'route'         => [
                'dpt' => $flight->dpt_airport_id,
                'arr' => $flight->arr_airport_id,
            ],
            'block' => [
                'minutes'    => (int) $flight->getRawOriginal('flight_time'),
                'hours'      => round($hours, 2),
                'cost_hour'  => round($blockHour + $fuelHour + $pilotHour, 2),
            ],
            'weather' => [
                'elevation_ft'   => (int) $elevationFt,
                'temperature_c'  => $temperatureC !== null ? round($temperatureC, 1) : null,
                'isa_c'          => round($isaC, 1),
                'source'         => $tempSource,
            ],
            'limits' => [
                'seats'          => $seats,
                'seats_max'      => $seatsMax,
                'penalty_pct'    => round($totalPenalty * 100.0, 1),
                'elevation_pct'  => round($elevationPenalty * 100.0, 1),
                'temperature_pct'=> round($tempPenalty * 100.0, 1),
            ],
            'costs' => [
                'block_hour'   => round($blockHour, 2),
                'fuel_hour'    => round($fuelHour, 2),
                'fuel_lb_hour' => $fuelLbHour,
                'fuel_price'   => $fuelPrice,
                'pilot_hour'   => round($pilotHour, 2),
                'pilot_fixed'  => round($pilotFixed, 2),
                'fixed_total'  => round($fixedCost, 2),
                'pax_unit'     => $paxFares->isNotEmpty() ? round($this->unitCost($paxFares, $mix), 2) : null,
                'cargo_unit'   => $cargoFare !== null ? round((float) $cargoFare->cost, 2) : null,
                'total'        => round($cost, 2),
            ],
            'fares' => [
                'pax'         => $paxFares->map(fn ($f) => [
                    'code'  => $f->code,
                    'price' => round((float) $f->price, 2),
                    'cost'  => round((float) $f->cost, 2),
                ])->values()->all(),
                'pax_avg'     => $paxFares->isNotEmpty() ? round((float) $paxFares->avg('price'), 2) : null,
                'cargo_code'  => $cargoFare?->code,
                'cargo_price' => $cargoFare !== null ? round((float) $cargoFare->price, 2) : null,
                'mix'         => $mix,
                // Equipaje medio por pasajero, ponderado por el reparto de
                // clases. SimBrief solo admite UN peso de equipaje, asi que se
                // le manda este promedio (lo que importa es el peso total).
                'baggage_avg_kg' => $baggageAvg !== null ? round($baggageAvg, 2) : null,
            ],
            'suggestion' => [
                'pax'            => $pax,
                'cargo'          => $cargo,
                'pax_revenue'    => round($paxRevenue, 2),
                'cargo_revenue'  => round($cargoRevenue, 2),
                'revenue'        => round($revenue, 2),
                'target'         => round(self::MARGIN * $cost, 2),
                'margin_pct'     => $marginPct,
                'target_reached' => $targetReached,
            ],
            'notes' => $this->buildNotes([
                'number'         => $number,
                'tempSource'     => $tempSource,
                'temperatureC'   => $temperatureC,
                'isaC'           => $isaC,
                'elevationFt'    => $elevationFt,
                'seats'          => $seats,
                'seatsMax'       => $seatsMax,
                'elevationPct'   => $elevationPenalty * 100.0,
                'temperaturePct' => $tempPenalty * 100.0,
                'paxFares'       => $paxFares,
                'cargoFare'      => $cargoFare,
                'targetReached'  => $targetReached,
                'pax'            => $pax,
                'cargo'          => $cargo,
                'marginPct'      => $marginPct,
                'pilotHour'      => $pilotHour,
                'pilotFixed'     => $pilotFixed,
                'blockHour'      => $blockHour,
            ]),
        ];
    }

    /**
     * Reparto de PAX por clases e ingreso/coste con ese reparto.
     *
     * Es el mismo reparto que `PirepEconomicsService::estimateRevenue()`: las
     * tarifas van de mas barata a mas cara, el mix decide el porcentaje de cada
     * clase y el sobrante del redondeo cae en la mas barata.
     *
     * @param Collection<int, object> $fares
     * @param float[]                 $mix
     *
     * @return array{0: float, 1: float} [ingreso, coste]
     */
    private function paxBalance(int $pax, Collection $fares, array $mix): array
    {
        if ($pax <= 0 || $fares->isEmpty()) {
            return [0.0, 0.0];
        }

        $counts = [];
        $assigned = 0;

        foreach ($fares as $i => $fare) {
            $pct = $mix[$i] ?? 0;
            $counts[$i] = (int) floor($pax * $pct / 100);
            $assigned += $counts[$i];
        }

        $counts[0] += max(0, $pax - $assigned);

        $revenue = 0.0;
        $cost = 0.0;

        foreach ($fares as $i => $fare) {
            $revenue += $counts[$i] * (float) $fare->price;
            $cost += $counts[$i] * (float) $fare->cost;
        }

        return [$revenue, $cost];
    }

    /**
     * Coste medio por pasajero que impone el reparto (para mostrar en el modal).
     *
     * @param Collection<int, object> $fares
     * @param float[]                 $mix
     */
    private function unitCost(Collection $fares, array $mix): float
    {
        [, $cost] = $this->paxBalance(100, $fares, $mix);

        return $cost / 100.0;
    }

    /**
     * Tarifas de pasaje y de carga aplicables al vuelo.
     *
     * PRIORIDAD (igual que el estimador de ingresos): primero las tarifas del
     * VUELO; solo si el vuelo no tiene ninguna se usan las de la SUBFLOTA. Las
     * de carga viven en `subfleet_fare` aunque el vuelo tenga producto de
     * pasaje.
     *
     * @return array{0: Collection<int, object>, 1: object|null}
     */
    private function resolveFares(Flight $flight, ?Subfleet $subfleet): array
    {
        $flightRows = DB::table('flight_fare')
            ->join('fares', 'fares.id', '=', 'flight_fare.fare_id')
            ->where('flight_fare.flight_id', $flight->id)
            ->where('fares.active', true)
            ->whereNull('fares.deleted_at')
            ->get(['fares.code', 'fares.name', 'fares.price', 'fares.cost', 'fares.type']);

        $allowedTypes = [FareType::PASSENGER, FareType::CARGO];

        $subfleetRows = $subfleet === null ? collect() : DB::table('subfleet_fare')
            ->join('fares', 'fares.id', '=', 'subfleet_fare.fare_id')
            ->where('subfleet_fare.subfleet_id', $subfleet->id)
            ->whereIn('fares.type', $allowedTypes)
            ->where('fares.active', true)
            ->whereNull('fares.deleted_at')
            ->get(['fares.code', 'fares.name', 'fares.price', 'fares.cost', 'fares.type']);

        $paxFares = $flightRows->where('type', FareType::PASSENGER)->sortBy('price')->values();
        if ($paxFares->isEmpty()) {
            $paxFares = $subfleetRows->where('type', FareType::PASSENGER)->sortBy('price')->values();
        }

        $cargoRows = $subfleetRows->where('type', FareType::CARGO);
        $cargoFare = $cargoRows->firstWhere('code', 'CGO') ?? $cargoRows->sortBy('price')->first();

        return [$paxFares, $cargoFare];
    }

    /**
     * Reparto de clases configurado (`finance.revenue_pax_mix`), con el override
     * por tipo de aeronave. Replica `PirepEconomicsService::mixFor()`.
     *
     * @return float[]
     */
    private function mixFor(string $type): array
    {
        $raw = setting('finance.revenue_pax_mix', '80,15,5');

        $byType = json_decode((string) setting('finance.revenue_pax_mix_by_type', ''), true);
        if ($type !== '' && is_array($byType) && isset($byType[$type]) && is_string($byType[$type])) {
            $raw = $byType[$type];
        }

        $parts = [];
        foreach (explode(',', (string) $raw) as $part) {
            $value = (float) trim($part);
            if ($value > 0) {
                $parts[] = $value;
            }
        }

        return $parts ?: [80.0, 15.0, 5.0];
    }

    /**
     * Equipaje medio por pasajero, ponderado por el reparto de clases.
     *
     * SimBrief solo admite UN peso de equipaje por vuelo (`bagwgt`), asi que se
     * le manda el promedio: lo que cuenta para el ZFW es el peso TOTAL, y el
     * promedio lo reproduce. El reparto es el mismo que el del ingreso
     * (`finance.revenue_pax_mix`) y las clases van de la mas barata a la mas
     * cara, igual que `paxFares`.
     *
     * Null si el vuelo no tiene tarifas de pasaje: entonces no se manda `acdata`
     * y SimBrief usa sus valores por defecto.
     *
     * @param Collection<int, object> $paxFares
     * @param float[]                 $mix
     */
    private function averageBaggageKg(Collection $paxFares, array $mix): ?float
    {
        if ($paxFares->isEmpty()) {
            return null;
        }

        $allowances = $this->baggageAllowances();
        $weighted = 0.0;
        $weight = 0.0;

        foreach ($paxFares as $i => $fare) {
            $pct = (float) ($mix[$i] ?? 0);
            if ($pct <= 0) {
                continue;
            }

            // Si hay mas clases que valores configurados, la ultima se repite.
            $allowance = $allowances[$i] ?? $allowances[count($allowances) - 1];

            $weighted += $pct * $allowance;
            $weight += $pct;
        }

        return $weight > 0 ? $weighted / $weight : null;
    }

    /**
     * Equipaje incluido por clase (kg), de la mas barata a la mas cara, leido del
     * ajuste `simbrief.baggage_by_class`. Los valores invalidos se ignoran.
     *
     * @return float[]
     */
    private function baggageAllowances(): array
    {
        $raw = (string) setting('simbrief.baggage_by_class', self::DEFAULT_BAGGAGE_BY_CLASS);

        $values = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '' || !is_numeric($part)) {
                continue;
            }

            $values[] = max(0.0, (float) $part);
        }

        return $values ?: [0.0];
    }

    /**
     * Temperatura del METAR de salida, en Celsius. Null si no hay dato.
     */
    private function metarTemperature(string $icao): ?float
    {
        try {
            $metar = $this->airportSvc->getMetar($icao);
            if ($metar === null || $metar->temperature === null) {
                return null;
            }

            return (float) $metar->temperature->internal();
        } catch (\Throwable $e) {
            Log::warning('DispatchSuggestion: sin METAR para '.$icao.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Consumo del tipo, con reserva por familia si no esta en la tabla.
     */
    private function fuelBurn(string $type): float
    {
        if (isset(self::FUEL_LB_PER_HOUR[$type])) {
            return (float) self::FUEL_LB_PER_HOUR[$type];
        }

        return (float) self::FUEL_FALLBACK[$this->familyOf($type)];
    }

    private function familyOf(string $type): string
    {
        if (in_array($type, self::FAMILY_REGIONAL, true)) {
            return 'regional';
        }

        if (in_array($type, self::FAMILY_NARROW, true)) {
            return 'narrow';
        }

        return 'wide';
    }

    /**
     * Pago al piloto por hora, segun su rango, para un vuelo ACARS.
     *
     * Replica `PirepFinanceService::getPilotPayRateForPirep()` en su rama ACARS:
     * tarifa base del rango, o el override del pivote rango-subflota.
     */
    private function pilotPayPerHour(?User $user, ?Subfleet $subfleet): float
    {
        $rank = $user?->rank;
        if ($rank === null) {
            return 0.0;
        }

        $base = (float) ($rank->acars_base_pay_rate ?? 0);
        $override = null;

        if ($subfleet !== null) {
            $pivot = $rank->subfleets()->where('subfleet_id', $subfleet->id)->first()?->pivot;
            if ($pivot !== null && $pivot->acars_pay !== null && $pivot->acars_pay !== '') {
                $override = $pivot->acars_pay;
            }
        }

        return (float) Math::applyAmountOrPercent($base, $override);
    }

    /**
     * Avisos para el modal.
     *
     * @param array<string, mixed> $ctx
     *
     * @return array<int, array{level: string, text: string}>
     */
    private function buildNotes(array $ctx): array
    {
        $notes = [];

        // Convenio es-CO, el mismo que usa el modal al pintar el titulo.
        $fmt = static fn ($value, int $decimals = 0): string => number_format((float) $value, $decimals, ',', '.');

        $recorte = $ctx['elevationPct'] + $ctx['temperaturePct'];
        if ($recorte > 0.05) {
            $parts = [];
            if ($ctx['elevationPct'] > 0.05) {
                $parts[] = 'elevacion '.$fmt($ctx['elevationFt']).' ft (-'.$fmt($ctx['elevationPct'], 1).' %)';
            }
            if ($ctx['temperaturePct'] > 0.05) {
                $parts[] = 'temperatura '.$fmt($ctx['temperatureC'], 1).' ºC frente a ISA '
                    .$fmt($ctx['isaC'], 1).' ºC (-'.$fmt($ctx['temperaturePct'], 1).' %)';
            }
            $notes[] = [
                'level' => 'warn',
                'text'  => 'Rendimiento: plazas recortadas de '.$ctx['seats'].' a '.$ctx['seatsMax']
                    .' por '.implode(' y ', $parts).'.',
            ];
        }

        if ($ctx['tempSource'] === 'unavailable') {
            $notes[] = [
                'level' => 'info',
                'text'  => 'Sin METAR de salida: el recorte solo tiene en cuenta la elevacion.',
            ];
        }

        if ($ctx['cargoFare'] === null && !$ctx['targetReached']) {
            $notes[] = [
                'level' => 'warn',
                'text'  => 'El vuelo no tiene tarifa de carga con la que completar el objetivo.',
            ];
        }

        if ($ctx['paxFares']->isEmpty()) {
            $notes[] = [
                'level' => 'warn',
                'text'  => 'El vuelo no tiene tarifas de pasaje: el objetivo se cubre solo con carga.',
            ];
        }

        if ($ctx['pilotHour'] <= 0 && $ctx['pilotFixed'] <= 0) {
            $notes[] = [
                'level' => 'info',
                'text'  => 'Sin tarifa de pago al piloto: el coste no la incluye.',
            ];
        }

        if ($ctx['blockHour'] <= 0) {
            $notes[] = [
                'level' => 'info',
                'text'  => 'La subflota no tiene coste de hora de bloque configurado.',
            ];
        }

        if (!$ctx['targetReached']) {
            $notes[] = [
                'level' => 'danger',
                'text'  => 'Objetivo no alcanzable con este avion: ni con el maximo de plazas'
                    .($ctx['cargo'] > 0 ? ' y la carga sugerida' : '').' se llega al +20 % de margen.',
            ];
        } elseif ($ctx['cargo'] > 0) {
            $notes[] = [
                'level' => 'info',
                'text'  => 'El pasaje va al maximo (' . $ctx['pax'] . ' plazas); el resto del objetivo se cubre con '
                    .$fmt($ctx['cargo']).' kg de carga.',
            ];
        }

        if ($ctx['targetReached'] && $ctx['marginPct'] !== null) {
            $notes[] = [
                'level' => 'ok',
                'text'  => 'Margen estimado: '.($ctx['marginPct'] >= 0 ? '+' : '').$fmt($ctx['marginPct'], 1).' %.',
            ];
        }

        return $notes;
    }
}
