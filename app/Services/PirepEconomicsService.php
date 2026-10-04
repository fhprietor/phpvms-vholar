<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Enums\FareType;
use App\Models\Pirep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Economia de un PIREP: coste real y ingreso estimado.
 *
 * COSTE: sale del libro mayor y es exacto. `journal_transactions` guarda
 * importes en centimos y, para un PIREP, todos los debitos cuelgan del diario
 * de la aerolinea (el abono espejo de la paga del piloto va a su wallet, no es
 * un debito del PIREP), asi que sumar los debitos da el coste total sin doble
 * conteo. Verificado: 4,85 h de A20N a 5.200 $/h = 25.220,00 $ frente a
 * 25.220,97 $ en el libro.
 *
 * INGRESO: es una ESTIMACION, y hay que decirlo. `pirep_fares` esta vacia en
 * los vuelos de ACARS porque el cliente no envia el desglose de tarifas, asi
 * que el unico ingreso posible hoy es derivarlo del payload que el cliente SI
 * escribe en el log (PAX y CARGO) cruzado con las tarifas del subfleet volado.
 * El reparto de clases es una decision de negocio, configurable en Settings.
 */
class PirepEconomicsService extends Service
{
    /**
     * Modelo al que se apuntan los asientos del PIREP.
     */
    private const LEDGER_MODEL = 'App\Models\Pirep';

    /**
     * `journal_transactions` guarda centimos.
     */
    private const CENTS = 100;

    /**
     * Coste real del vuelo, desglosado por concepto. Vacio si no hay asientos.
     *
     * @return array{total: float, breakdown: array<string, float>}|array{}
     */
    public function costs(Pirep $pirep): array
    {
        $rows = DB::table('journal_transactions')
            ->where('ref_model', self::LEDGER_MODEL)
            ->where('ref_model_id', $pirep->id)
            ->where('debit', '>', 0)
            ->get(['memo', 'debit']);

        if ($rows->isEmpty()) {
            return [];
        }

        $breakdown = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $amount = ((int) $row->debit) / self::CENTS;
            $concept = $this->concept((string) $row->memo);

            $breakdown[$concept] = round(($breakdown[$concept] ?? 0) + $amount, 2);
            $total += $amount;
        }

        arsort($breakdown);

        return ['total' => round($total, 2), 'breakdown' => $breakdown];
    }

    /**
     * Ingreso estimado a partir del payload del log y las tarifas del subfleet.
     *
     * @param  array<string, mixed>      $payload Bloque `payload` de las operaciones (pax, cargo)
     * @return array<string, mixed>|null null si no hay datos o esta desactivado
     */
    public function estimateRevenue(Pirep $pirep, array $payload): ?array
    {
        if (!setting('finance.revenue_estimate_enabled', true)) {
            return null;
        }

        $pax = (int) ($payload['pax'] ?? 0);
        $cargoUnits = (float) ($payload['cargo'] ?? 0);

        if ($pax <= 0 && $cargoUnits <= 0) {
            return null;
        }

        $fares = $this->subfleetFares($pirep);
        if ($fares->isEmpty()) {
            return null;
        }

        // Ordenadas por precio ascendente: el reparto se define en ese orden
        // (la primera cuota es la clase mas barata).
        $paxFares = $fares->where('type', FareType::PASSENGER)->sortBy('price')->values();

        $lines = [];
        $total = 0.0;

        if ($pax > 0 && $paxFares->isNotEmpty()) {
            $mix = $this->mixFor($pirep);

            $counts = [];
            $assigned = 0;
            foreach ($paxFares as $i => $fare) {
                $pct = $mix[$i] ?? 0;
                $counts[$i] = (int) floor($pax * $pct / 100);
                $assigned += $counts[$i];
            }

            // Todo lo que no reparte el mix va a la clase mas barata, para que
            // la suma de pasajeros cuadre siempre con el payload del log.
            $counts[0] += max(0, $pax - $assigned);

            foreach ($paxFares as $i => $fare) {
                if ($counts[$i] <= 0) {
                    continue;
                }

                $revenue = round($counts[$i] * (float) $fare->price, 2);
                $total += $revenue;

                $lines[] = [
                    'code'    => $fare->code,
                    'name'    => $fare->name,
                    'count'   => $counts[$i],
                    'price'   => (float) $fare->price,
                    'revenue' => $revenue,
                ];
            }
        }

        $cargo = null;
        if ($cargoUnits > 0) {
            $cargoFare = $this->cargoFare($fares);
            if ($cargoFare !== null) {
                $revenue = round($cargoUnits * (float) $cargoFare->price, 2);
                $total += $revenue;

                $cargo = [
                    'code'    => $cargoFare->code,
                    'name'    => $cargoFare->name,
                    'units'   => $cargoUnits,
                    'price'   => (float) $cargoFare->price,
                    'revenue' => $revenue,
                ];
            }
        }

        return [
            'is_estimate' => true,
            'passengers'  => ['total' => $pax, 'lines' => $lines],
            'cargo'       => $cargo,
            'total'       => round($total, 2),
        ];
    }

    /**
     * Cuadro económico completo: coste real, ingreso estimado, margen y costes
     * unitarios. Es lo que se le pasa al instructor.
     *
     * @param  array<string, mixed>      $operations Bloque `operations` del parser
     * @return array<string, mixed>|null
     */
    public function summary(Pirep $pirep, array $operations): ?array
    {
        $costs = $this->costs($pirep);
        if (empty($costs)) {
            return null;
        }

        $payload = $operations['payload'] ?? [];
        $revenue = $this->estimateRevenue($pirep, is_array($payload) ? $payload : []);

        $summary = ['costs' => $costs];

        if ($revenue !== null) {
            $summary['revenue'] = $revenue;
            $summary['profit'] = round($revenue['total'] - $costs['total'], 2);
            $summary['margin_pct'] = $costs['total'] > 0
                ? round(($summary['profit'] / $costs['total']) * 100, 1)
                : null;
        }

        // Coste por unidad de carga transportada y por hora de vuelo: lo que
        // permite comparar vuelos de distinta duracion y ocupacion.
        $pax = (int) ($payload['pax'] ?? 0);
        if ($pax > 0) {
            $summary['cost_per_pax'] = round($costs['total'] / $pax, 2);
        }

        $cargoUnits = (float) ($payload['cargo'] ?? 0);
        if ($pax + $cargoUnits > 0) {
            $summary['cost_per_payload_unit'] = round($costs['total'] / ($pax + $cargoUnits), 2);
        }

        $hours = ((int) $pirep->getRawOriginal('flight_time')) / 60;
        if ($hours > 0) {
            $summary['cost_per_hour'] = round($costs['total'] / $hours, 2);
        }

        $distance = (float) $pirep->getRawOriginal('distance');
        if ($distance > 0) {
            $summary['cost_per_nm'] = round($costs['total'] / $distance, 2);
        }

        return $summary;
    }

    /**
     * Tarifas definidas en el subfleet de la aeronave volada.
     *
     * @return Collection<int, object>
     */
    private function subfleetFares(Pirep $pirep): Collection
    {
        $subfleet = $pirep->aircraft?->subfleet;
        if ($subfleet === null) {
            return collect();
        }

        return DB::table('subfleet_fare')
            ->join('fares', 'fares.id', '=', 'subfleet_fare.fare_id')
            ->where('subfleet_fare.subfleet_id', $subfleet->id)
            ->where('fares.active', true)
            ->whereNull('fares.deleted_at')
            ->get(['fares.code', 'fares.name', 'fares.price', 'fares.type']);
    }

    /**
     * Tarifa de carga: se prefiere la general (CGO) y, si no esta, la mas
     * barata, que es la estimacion mas conservadora.
     *
     * @param Collection<int, object> $fares
     */
    private function cargoFare(Collection $fares): ?object
    {
        $cargo = $fares->where('type', FareType::CARGO)->sortBy('price')->values();
        if ($cargo->isEmpty()) {
            return null;
        }

        return $cargo->firstWhere('code', 'CGO') ?? $cargo->first();
    }

    /**
     * Reparto de pasajeros por clase, en porcentajes sobre las tarifas de
     * pasajero ordenadas por precio ascendente.
     *
     * @return float[]
     */
    private function mixFor(Pirep $pirep): array
    {
        $raw = setting('finance.revenue_pax_mix', '80,15,5');

        // Override por tipo de aeronave, si lo hay.
        $type = $pirep->aircraft?->icao;
        $byType = json_decode((string) setting('finance.revenue_pax_mix_by_type', ''), true);
        if ($type && is_array($byType) && isset($byType[$type]) && is_string($byType[$type])) {
            $raw = $byType[$type];
        }

        $parts = [];
        foreach (explode(',', (string) $raw) as $part) {
            $value = (float) trim($part);
            if ($value > 0) {
                $parts[] = $value;
            }
        }

        return $parts;
    }

    /**
     * Concepto de coste a partir del memo del asiento.
     */
    private function concept(string $memo): string
    {
        return match (true) {
            str_contains($memo, 'Fuel')            => 'fuel',
            str_contains($memo, 'Block Time')      => 'block_time',
            str_contains($memo, 'Ground Handling') => 'ground_handling',
            str_contains($memo, 'Pilot Payment')   => 'pilot_payment',
            str_contains($memo, 'Landing')         => 'landing_fees',
            default                                => 'other',
        };
    }
}
