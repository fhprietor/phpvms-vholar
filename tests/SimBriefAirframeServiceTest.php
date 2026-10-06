<?php

namespace Tests;

use App\Models\Aircraft;
use App\Models\Subfleet;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\SimBriefAirframeService;

/**
 * Airframe de SimBrief del piloto.
 *
 * Lo que se protege aqui:
 *  - el campo de perfil se interpreta con tolerancia (separadores, espacios,
 *    tipo en minusculas) y lo que no encaja se ignora;
 *  - la cadena de prioridad es piloto -> avion -> subflota -> ICAO;
 *  - y, sobre todo, que SIN campo (o con el campo vacio, o con basura) no se
 *    rompe nada: se cae al ICAO y no se avisa de nada.
 */
final class SimBriefAirframeServiceTest extends TestCase
{
    private SimBriefAirframeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SimBriefAirframeService::class);
    }

    /**
     * Piloto con el campo de perfil. `null` = sin campo ninguno; '' = campo vacio.
     */
    private function userWithAirframes(?string $value): User
    {
        $user = User::factory()->create();

        if ($value === null) {
            return $user->fresh();
        }

        $field = UserField::create([
            'name'     => 'SimBrief Airframe IDs',
            'private'  => 1,
            'internal' => 0,
            'active'   => 1,
        ]);

        UserFieldValue::create([
            'user_field_id' => $field->id,
            'user_id'       => $user->id,
            'value'         => $value,
        ]);

        return $user->fresh();
    }

    private function aircraft(string $type, ?string $aircraftType = null, ?string $subfleetType = null): Aircraft
    {
        $subfleet = Subfleet::factory()->create([
            'type'          => $type,
            'simbrief_type' => $subfleetType,
        ]);

        return Aircraft::factory()->create([
            'subfleet_id'   => $subfleet->id,
            'icao'          => $type,
            'simbrief_type' => $aircraftType,
        ])->load('subfleet');
    }

    // ------------------------------------------------------------------ lectura

    public function test_it_parses_pairs_with_any_separator_and_lowercase_type(): void
    {
        $map = $this->service->parse(
            "b738=349674_1661382482154, A320=80_1709125568637;\n A21N = 1199171_1700000000000"
        );

        $this->assertSame('349674_1661382482154', $map['B738']);
        $this->assertSame('80_1709125568637', $map['A320']);
        $this->assertSame('1199171_1700000000000', $map['A21N']);
    }

    public function test_it_ignores_whatever_does_not_fit_the_format(): void
    {
        $map = $this->service->parse(
            'esto no es un airframe, B738=, =1234, B738=id con espacios, ZZ=1, A320=ok_1234'
        );

        $this->assertSame(['A320' => 'ok_1234'], $map);
    }

    public function test_it_resolves_the_airframe_of_the_pilot_for_the_type(): void
    {
        $user = $this->userWithAirframes('A320=1199171_1700000000000');

        $this->assertSame('1199171_1700000000000', $this->service->resolve($user, 'a320'));
        $this->assertNull($this->service->resolve($user, 'B738'));
    }

    // ------------------------------------------------- sin campo: no debe romper

    public function test_a_pilot_without_the_field_gets_the_bare_icao_and_no_note(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertNull($this->service->resolve($user, 'A320'));

        $result = $this->service->resolveType($this->aircraft('A320'), $user);

        $this->assertSame('A320', $result['type']);
        $this->assertNull($result['airframe']);
        $this->assertSame('icao', $result['source']);
        $this->assertNull($result['note']);
    }

    public function test_an_empty_field_is_the_same_as_not_having_it(): void
    {
        $user = $this->userWithAirframes('');

        $this->assertNull($this->service->resolve($user, 'A320'));

        $result = $this->service->resolveType($this->aircraft('A320'), $user);
        $this->assertSame('A320', $result['type']);
        $this->assertSame('icao', $result['source']);
    }

    public function test_a_malformed_value_falls_back_instead_of_breaking(): void
    {
        $user = $this->userWithAirframes('esto no es un airframe');

        $result = $this->service->resolveType($this->aircraft('A320'), $user);

        $this->assertSame('A320', $result['type']);
        $this->assertSame('icao', $result['source']);
        $this->assertNull($result['airframe']);
    }

    public function test_a_null_user_and_a_null_aircraft_are_safe(): void
    {
        $this->assertNull($this->service->resolve(null, 'A320'));

        $result = $this->service->resolveType($this->aircraft('A320'), null);
        $this->assertSame('A320', $result['type']);

        // El avion transitorio del endpoint (sin matricula) tampoco rompe.
        $result = $this->service->resolveType(null, null, 'B738');
        $this->assertSame('B738', $result['type']);
        $this->assertSame('icao', $result['source']);
    }

    // ------------------------------------------------------------------ cadena

    public function test_the_chain_is_pilot_then_aircraft_then_subfleet_then_icao(): void
    {
        $aircraft = $this->aircraft('A320', 'ac_airframe_1234', 'sf_airframe_1234');

        // 1. Piloto.
        $user = $this->userWithAirframes('A320=1199171_1700000000000');
        $result = $this->service->resolveType($aircraft, $user);
        $this->assertSame('1199171_1700000000000', $result['type']);
        $this->assertSame('1199171_1700000000000', $result['airframe']);
        $this->assertSame('pilot', $result['source']);
        $this->assertNotNull($result['note']);
        $this->assertStringContainsString('1199171_1700000000000', $result['note']['text']);

        // 2. Avion (el piloto no ha guardado nada).
        $result = $this->service->resolveType($aircraft, User::factory()->create()->fresh());
        $this->assertSame('ac_airframe_1234', $result['type']);
        $this->assertNull($result['airframe']);
        $this->assertSame('aircraft', $result['source']);

        // 3. Subflota.
        $result = $this->service->resolveType($this->aircraft('A20N', null, 'sf_airframe_5678'), null);
        $this->assertSame('sf_airframe_5678', $result['type']);
        $this->assertSame('subfleet', $result['source']);

        // 4. ICAO.
        $result = $this->service->resolveType($this->aircraft('B738'), null);
        $this->assertSame('B738', $result['type']);
        $this->assertSame('icao', $result['source']);
    }

    /**
     * Hoy `simbrief_type` repite el ICAO en casi toda la flota: eso no debe
     * generar un aviso en el modal.
     */
    public function test_a_simbrief_type_equal_to_the_icao_does_not_raise_a_note(): void
    {
        $result = $this->service->resolveType($this->aircraft('A320', 'A320'), null);

        $this->assertSame('A320', $result['type']);
        $this->assertSame('aircraft', $result['source']);
        $this->assertNull($result['note']);
    }

    /**
     * El campo del piloto solo aplica al tipo que le corresponde: en otro avion
     * la cadena sigue su curso.
     */
    public function test_the_pilot_airframe_only_applies_to_its_own_type(): void
    {
        $user = $this->userWithAirframes('A320=1199171_1700000000000');

        $result = $this->service->resolveType($this->aircraft('B738', 'B738'), $user);

        $this->assertSame('B738', $result['type']);
        $this->assertSame('aircraft', $result['source']);
        $this->assertNull($result['airframe']);
    }
}
