<?php

namespace Tests;

use App\Services\AirspaceService;

/**
 * Revision previa de la ruta contra espacios aereos (AirspaceService).
 *
 * No toca la red: el cache de areas se inyecta como fichero temporal y las celdas de los
 * fixes se dan en el propio cache, asi que la prueba es determinista.
 */
final class RouteAirspaceCheckTest extends TestCase
{
    /**
     * @param array<string, array<string, mixed>> $areas
     * @param array<string, array<int, string>>   $cells
     */
    private function service(array $areas, array $cells): AirspaceService
    {
        $file = tempnam(sys_get_temp_dir(), 'asp').'.json';
        file_put_contents($file, json_encode(['areas' => $areas, 'cells' => $cells]));

        return new AirspaceService($file);
    }

    private function ofp(string $fromLat, string $fromLon, string $toLat, string $toLon, ?string $fromAlt = null, ?string $toAlt = null): string
    {
        $alt = function (?string $value): string {
            return $value === null ? '' : '<altitude_feet>'.$value.'</altitude_feet>';
        };

        return '<?xml version="1.0" encoding="UTF-8"?><OFP><navlog>'
            .'<fix><ident>AAAA</ident><pos_lat>'.$fromLat.'</pos_lat><pos_long>'.$fromLon.'</pos_long>'.$alt($fromAlt).'</fix>'
            .'<fix><ident>BBBB</ident><pos_lat>'.$toLat.'</pos_lat><pos_long>'.$toLon.'</pos_long>'.$alt($toAlt).'</fix>'
            .'</navlog></OFP>';
    }

    private function box(): array
    {
        // caja 9.5..9.8 N, 74.2..73.9 W  -> [lon, lat]
        return [[-74.2, 9.5], [-73.9, 9.5], [-73.9, 9.8], [-74.2, 9.8], [-74.2, 9.5]];
    }

    public function test_it_flags_a_route_that_crosses_a_restricted_area(): void
    {
        $areas = ['SKR99 / TEST' => [
            'name'  => 'SKR99 / TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => 'FL999',
            'rings' => [$this->box()],
        ]];

        // celdas de los dos fixes (0,25 grados): 9.4 y 9.9 -> filas 37 y 39; -74.0 -> col -296
        $cells = ['37,-296' => ['SKR99 / TEST'], '39,-296' => ['SKR99 / TEST']];

        $warning = $this->service($areas, $cells)->warningForOfp($this->ofp('9.4', '-74.0', '9.9', '-74.0'));

        $this->assertNotNull($warning);
        $this->assertSame('SKR99 / TEST', $warning['area']);
        $this->assertSame('AAAA–BBBB', $warning['leg']);
        $this->assertSame('GND–FL999', $warning['limits']);
    }

    public function test_a_route_outside_the_area_has_no_warning(): void
    {
        $areas = ['SKR99 / TEST' => [
            'name'  => 'SKR99 / TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => 'FL999',
            'rings' => [$this->box()],
        ]];

        // misma area en cache, pero la ruta pasa al oeste de la caja
        $cells = ['32,-296' => ['SKR99 / TEST'], '34,-296' => ['SKR99 / TEST']];

        $this->assertNull($this->service($areas, $cells)->warningForOfp($this->ofp('8.0', '-75.0', '8.5', '-75.0')));
    }

    public function test_a_danger_area_does_not_warn(): void
    {
        $areas = ['SVD1 / TEST' => [
            'name'  => 'SVD1 / TEST', 'type' => 'Danger', 'lower' => 'GND', 'upper' => 'FL100',
            'rings' => [$this->box()],
        ]];
        $cells = ['37,-296' => ['SVD1 / TEST'], '39,-296' => ['SVD1 / TEST']];

        $this->assertNull($this->service($areas, $cells)->warningForOfp($this->ofp('9.4', '-74.0', '9.9', '-74.0')));
    }

    public function test_an_ofp_without_navlog_returns_no_warning(): void
    {
        $this->assertNull($this->service([], [])->warningForOfp('<?xml version="1.0"?><OFP></OFP>'));
    }

    /**
     * El caso SVR2508: area GND-3000ft MSL y el SID LLIX2A de SKRG pasando a 11.500ft.
     * Sin altitud en el dato, ese vuelo se marcaba como incursion.
     */
    public function test_an_area_below_the_route_does_not_warn(): void
    {
        $areas = ['SVR2508 - TEST' => [
            'name'  => 'SVR2508 - TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => '3000ft MSL',
            'rings' => [$this->box()],
        ]];
        $cells = ['37,-296' => ['SVR2508 - TEST'], '39,-296' => ['SVR2508 - TEST']];

        $ofp = $this->ofp('9.4', '-74.0', '9.9', '-74.0', '11500', '11500');

        $this->assertNull($this->service($areas, $cells)->warningForOfp($ofp));
    }

    public function test_the_same_area_warns_when_the_route_is_low(): void
    {
        $areas = ['SVR2508 - TEST' => [
            'name'  => 'SVR2508 - TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => '3000ft MSL',
            'rings' => [$this->box()],
        ]];
        $cells = ['37,-296' => ['SVR2508 - TEST'], '39,-296' => ['SVR2508 - TEST']];

        $warning = $this->service($areas, $cells)->warningForOfp($this->ofp('9.4', '-74.0', '9.9', '-74.0', '2000', '2000'));

        $this->assertNotNull($warning);
        $this->assertSame('GND–3000ft MSL', $warning['limits']);
        $this->assertEqualsWithDelta(2000.0, $warning['alt'], 1.0);
    }

    public function test_an_area_with_fl_limits_still_warns_at_cruise(): void
    {
        $areas = ['SKR99 / TEST' => [
            'name'  => 'SKR99 / TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => 'FL999',
            'rings' => [$this->box()],
        ]];
        $cells = ['37,-296' => ['SKR99 / TEST'], '39,-296' => ['SKR99 / TEST']];

        $ofp = $this->ofp('9.4', '-74.0', '9.9', '-74.0', '11500', '11500');

        $this->assertNotNull($this->service($areas, $cells)->warningForOfp($ofp));
    }

    public function test_the_clients_route_navlog_is_parsed(): void
    {
        $points = $this->service([], [])->parseRouteNavlog(
            'DE14L,4.692706,-74.124725,9900;BO907,4.784872,-74.065556,9900;BAD;0,0,0'
        );

        $this->assertCount(2, $points);
        $this->assertSame('DE14L', $points[0]['ident']);
        $this->assertEqualsWithDelta(4.692706, $points[0]['lat'], 0.000001);
        $this->assertEqualsWithDelta(-74.124725, $points[0]['lon'], 0.000001);
        $this->assertEqualsWithDelta(9900.0, $points[0]['alt'], 0.1);
    }

    public function test_a_route_from_the_clients_navlog_is_checked_with_its_altitude(): void
    {
        $areas = ['SKR99 / TEST' => [
            'name'  => 'SKR99 / TEST', 'type' => 'Restricted', 'lower' => 'GND', 'upper' => '3000ft MSL',
            'rings' => [$this->box()],
        ]];
        $cells = ['37,-296' => ['SKR99 / TEST'], '39,-296' => ['SKR99 / TEST']];

        $svc = $this->service($areas, $cells);

        $high = $svc->parseRouteNavlog('AAAA,9.4,-74.0,11500;BBBB,9.9,-74.0,11500');
        $low = $svc->parseRouteNavlog('AAAA,9.4,-74.0,2000;BBBB,9.9,-74.0,2000');

        $this->assertNull($svc->warningForRoute($high));
        $this->assertNotNull($svc->warningForRoute($low));
    }
}
