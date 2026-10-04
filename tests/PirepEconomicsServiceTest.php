<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Enums\AcarsType;
use App\Models\Enums\FareType;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Models\Setting;
use App\Services\PirepEconomicsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Economia del PIREP: coste real del libro mayor e ingreso estimado del payload.
 *
 * Lo que se protege aqui: que el coste salga de los debitos contables sin doble
 * conteo (el abono espejo de la paga del piloto NO es un coste del PIREP), que
 * la estimacion de ingreso cuadre siempre con los pasajeros del log, y que la
 * marca de estimacion viaje hasta el payload que ve el instructor.
 */
final class PirepEconomicsServiceTest extends TestCase
{
    private PirepEconomicsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PirepEconomicsService::class);
    }

    /**
     * PIREP con las tarifas indicadas colgando de su subfleet.
     *
     * @param array<int, array{code:string, price:float, type:int}> $fares
     */
    private function makePirepWithFares(array $fares): Pirep
    {
        $pirep = Pirep::factory()->create([
            'source'       => 1,
            'flight_time'  => 300,
            'distance'     => 1000,
            'landing_rate' => -80,
        ]);

        $subfleetId = $pirep->aircraft->subfleet_id;

        foreach ($fares as $fare) {
            $id = DB::table('fares')->insertGetId([
                'code'     => $fare['code'],
                'name'     => $fare['code'],
                'price'    => $fare['price'],
                'cost'     => 10,
                'capacity' => 1,
                'type'     => $fare['type'],
                'active'   => true,
            ]);

            DB::table('subfleet_fare')->insert([
                'subfleet_id' => $subfleetId,
                'fare_id'     => $id,
            ]);
        }

        return $pirep->fresh();
    }

    /**
     * Asientos contables del PIREP. `journal_id` 1 = aerolinea (debitos) y
     * 2 = wallet del piloto (el abono espejo).
     *
     * @param array<int, array{0:string, 1:int}> $entries [memo, importe en centimos]
     */
    private function ledger(Pirep $pirep, array $entries): void
    {
        $ledgerId = DB::table('ledgers')->insertGetId([
            'name' => 'Test Ledger',
            'type' => 'asset',
        ]);

        // Los ids no se fijan a mano: el seed de los tests ya puede traer
        // diarios creados, asi que se usan los que devuelva el insert.
        $airlineJournal = DB::table('journals')->insertGetId([
            'ledger_id' => $ledgerId,
            'type'      => 0,
            'balance'   => 0,
            'currency'  => 'USD',
        ]);

        $pilotJournal = DB::table('journals')->insertGetId([
            'ledger_id' => $ledgerId,
            'type'      => 1,
            'balance'   => 0,
            'currency'  => 'USD',
        ]);

        foreach ($entries as [$memo, $cents]) {
            DB::table('journal_transactions')->insert([
                'id'           => Str::uuid()->toString(),
                'journal_id'   => $airlineJournal,
                'debit'        => $cents,
                'currency'     => 'USD',
                'memo'         => $memo,
                'ref_model'    => 'App\Models\Pirep',
                'ref_model_id' => $pirep->id,
                'post_date'    => now()->toDateString(),
            ]);
        }

        // Abono espejo de la paga del piloto: mismo importe, otro diario. No
        // debe sumarse al coste.
        DB::table('journal_transactions')->insert([
            'id'           => Str::uuid()->toString(),
            'journal_id'   => $pilotJournal,
            'credit'       => 145500,
            'currency'     => 'USD',
            'memo'         => 'Pilot Payment @ 300',
            'ref_model'    => 'App\Models\Pirep',
            'ref_model_id' => $pirep->id,
            'post_date'    => now()->toDateString(),
        ]);
    }

    // ------------------------------------------------------------------ coste

    public function test_costs_are_summed_from_debits_only(): void
    {
        $pirep = $this->makePirepWithFares([]);
        $this->ledger($pirep, [
            ['Subfleet A20N: Block Time Cost', 2522097],
            ['Fuel Cost (0.9/lbs)', 801630],
            ['Ground Handling (Departure)', 800],
            ['Pilot Payment @ 300', 145500],
        ]);

        $costs = $this->service->costs($pirep);

        // 25.220,97 + 8.016,30 + 8,00 + 1.455,00 = 34.700,27. El abono de la
        // paga del piloto (journal 2) no entra.
        $this->assertSame(34700.27, $costs['total']);
        $this->assertSame(25220.97, $costs['breakdown']['block_time']);
        $this->assertSame(8016.30, $costs['breakdown']['fuel']);
        $this->assertSame(1455.00, $costs['breakdown']['pilot_payment']);
    }

    public function test_a_pirep_without_ledger_entries_has_no_costs(): void
    {
        $pirep = $this->makePirepWithFares([]);

        $this->assertSame([], $this->service->costs($pirep));
        $this->assertNull($this->service->summary($pirep, []));
    }

    // ---------------------------------------------------------------- ingreso

    public function test_revenue_is_estimated_from_pax_cargo_and_fare_mix(): void
    {
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '80,15,5']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y',   'price' => 180.0, 'type' => FareType::PASSENGER],
            ['code' => 'C',   'price' => 520.0, 'type' => FareType::PASSENGER],
            ['code' => 'CGO', 'price' => 2.80,  'type' => FareType::CARGO],
        ]);

        $revenue = $this->service->estimateRevenue($pirep, ['pax' => 159, 'cargo' => 200]);

        // 80% de 159 = 127 -> mas el resto (9) = 136 en Economy; 15% = 23 en Business.
        $this->assertSame(136, $revenue['passengers']['lines'][0]['count']);
        $this->assertSame(23, $revenue['passengers']['lines'][1]['count']);
        $this->assertSame(159, array_sum(array_column($revenue['passengers']['lines'], 'count')));
        $this->assertSame(24480.0, $revenue['passengers']['lines'][0]['revenue']);
        $this->assertSame(11960.0, $revenue['passengers']['lines'][1]['revenue']);
        $this->assertSame(560.0, $revenue['cargo']['revenue']);
        $this->assertSame(37000.0, $revenue['total']);
        // La marca de estimacion debe viajar con el dato.
        $this->assertTrue($revenue['is_estimate']);
    }

    public function test_the_pax_remainder_always_lands_on_the_cheapest_class(): void
    {
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '33,33,33']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y', 'price' => 100.0, 'type' => FareType::PASSENGER],
            ['code' => 'W', 'price' => 200.0, 'type' => FareType::PASSENGER],
            ['code' => 'C', 'price' => 300.0, 'type' => FareType::PASSENGER],
        ]);

        $revenue = $this->service->estimateRevenue($pirep, ['pax' => 100, 'cargo' => 0]);

        $lines = collect($revenue['passengers']['lines'])->keyBy('code');
        // 33 + 33 + 33 = 99: el pasajero que falta va al mas barato.
        $this->assertSame(34, $lines['Y']['count']);
        $this->assertSame(33, $lines['W']['count']);
        $this->assertSame(33, $lines['C']['count']);
        // 100 pasajeros exactos, nunca inventados.
        $this->assertSame(100, $revenue['passengers']['total']);
    }

    public function test_a_per_type_mix_override_wins_over_the_global_one(): void
    {
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '50,50']);
        Setting::where('key', 'finance.revenue_pax_mix_by_type')->update(['value' => '{"B738":"100"}']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y', 'price' => 100.0, 'type' => FareType::PASSENGER],
            ['code' => 'C', 'price' => 900.0, 'type' => FareType::PASSENGER],
        ]);
        // El tipo del fixture es el que traiga la aeronave; se fuerza a B738.
        Aircraft::where('id', $pirep->aircraft_id)->update(['icao' => 'B738']);
        $pirep = $pirep->fresh();

        $revenue = $this->service->estimateRevenue($pirep, ['pax' => 100, 'cargo' => 0]);

        $lines = collect($revenue['passengers']['lines'])->keyBy('code');
        $this->assertSame(100, $lines['Y']['count']);
        $this->assertArrayNotHasKey('C', $lines->all());
    }

    public function test_revenue_can_be_switched_off(): void
    {
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 0]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y', 'price' => 180.0, 'type' => FareType::PASSENGER],
        ]);

        $this->assertNull($this->service->estimateRevenue($pirep, ['pax' => 100, 'cargo' => 0]));
    }

    public function test_revenue_is_null_without_payload_or_fares(): void
    {
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $withFares = $this->makePirepWithFares([['code' => 'Y', 'price' => 180.0, 'type' => FareType::PASSENGER]]);
        $this->assertNull($this->service->estimateRevenue($withFares, ['pax' => 0, 'cargo' => 0]));

        $withoutFares = $this->makePirepWithFares([]);
        $this->assertNull($this->service->estimateRevenue($withoutFares, ['pax' => 100, 'cargo' => 0]));
    }

    // ----------------------------------------------------------------- cuadro

    public function test_the_summary_combines_costs_revenue_and_unit_metrics(): void
    {
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '100']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y', 'price' => 200.0, 'type' => FareType::PASSENGER],
        ]);
        $this->ledger($pirep, [['Subfleet A320: Block Time Cost', 1000000]]); // 10.000,00

        // 100 pax a 200 = 20.000 de ingreso frente a 10.000 de coste.
        $summary = $this->service->summary($pirep, ['payload' => ['pax' => 100, 'cargo' => 0]]);

        $this->assertSame(10000.0, $summary['costs']['total']);
        $this->assertSame(20000.0, $summary['revenue']['total']);
        $this->assertSame(10000.0, $summary['profit']);
        $this->assertSame(100.0, $summary['margin_pct']);
        $this->assertSame(100.0, $summary['cost_per_pax']);
        // 300 min de vuelo = 5 h.
        $this->assertSame(2000.0, $summary['cost_per_hour']);
        // 1000 nm.
        $this->assertSame(10.0, $summary['cost_per_nm']);
    }

    /**
     * La marca de estimacion debe llegar al payload que ve el instructor: sin
     * ella afirmaria beneficios como si fueran contabilidad cerrada.
     */
    public function test_the_payload_marks_the_revenue_as_an_estimate(): void
    {
        Setting::where('key', 'finance.revenue_pax_mix')->update(['value' => '100']);
        Setting::where('key', 'finance.revenue_estimate_enabled')->update(['value' => 1]);

        $pirep = $this->makePirepWithFares([
            ['code' => 'Y', 'price' => 200.0, 'type' => FareType::PASSENGER],
        ]);
        $this->ledger($pirep, [['Subfleet A320: Block Time Cost', 1000000]]);

        // buildPayload exige telemetria real de aterrizaje y payload en el log.
        foreach ([
            'DATOS DE ATERRIZAJE:',
            'Aterrizaje registrado: -80 fpm, 1,10 G, Rumbo: 30°, Cabeceo: 6,0°, Alabeo: 0,0°',
            'PAX 100  FUEL 5000  TRIP 4000  CARGO 0  FL350',
        ] as $i => $line) {
            Acars::factory()->create([
                'pirep_id'   => $pirep->id,
                'type'       => AcarsType::LOG,
                'log'        => $line,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        $payload = app(\App\Services\PirepFeedbackService::class)->buildPayload($pirep->fresh());

        $this->assertNotNull($payload);
        $this->assertTrue($payload['economia']['ingreso_estimado']['es_estimado']);
        $this->assertSame(10000.0, $payload['economia']['coste_real']['total']);
        $this->assertSame(10000.0, $payload['economia']['beneficio']);
    }

    /**
     * El area de economia se valida y se guarda con la misma escala que la
     * gravedad general.
     */
    public function test_areas_are_validated_and_persisted(): void
    {
        $pirep = Pirep::factory()->create(['source' => 1]);

        $feedback = PirepAiFeedback::create([
            'pirep_id' => $pirep->id,
            'model'    => 'deepseek-flash',
            'severity' => 1,
            'areas'    => [
                ['area' => 'arranque', 'valoracion' => 1, 'nota' => 'Idle asimetrico'],
                ['area' => 'despegue', 'valoracion' => 0, 'nota' => 'Centro de pista 1 ft'],
            ],
        ]);

        $this->assertCount(2, $feedback->fresh()->areas);
        $this->assertSame('Mejorable', $feedback->areaLabel(1));
        $this->assertSame('Correcto', $feedback->areaLabel(0));
        $this->assertSame('Crítico', $feedback->areaLabel(2));
    }
}
