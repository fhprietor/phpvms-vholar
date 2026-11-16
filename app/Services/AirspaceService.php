<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Pirep;
use Illuminate\Support\Facades\Http;

/**
 * Revision previa de la ruta contra los espacios aereos de NavData.
 *
 * La usa `PirepService::prefile()`: el cliente prefila ANTES de despegar, asi que el aviso
 * llega a tiempo de corregir el plan (al presentar el PIREP ya seria tarde).
 *
 * Fuentes: el OFP de SimBrief pegado al PIREP (su `navlog` trae las coordenadas de cada
 * fix) y la API de NavData, que devuelve las areas en un radio alrededor de un punto; las
 * areas se cachean por celda de 0,25 grados en storage/app/auditoria/airspaces.json (la
 * misma cache que usa phpvms:airspace-audit).
 *
 * Nunca lanza: si no hay OFP, no hay red o NavData falla, devuelve null y el prefile sigue.
 */
class AirspaceService extends Service
{
    private const CELL = 0.25;

    /** Tipos que cuentan como incumplimiento; Danger se informa aparte. */
    private const BLOCKING = ['Restricted', 'Prohibited'];

    private array $areas = [];

    private array $cells = [];

    private string $cacheFile;

    public function __construct(?string $cacheFile = null)
    {
        $this->cacheFile = $cacheFile ?? storage_path('app/auditoria/airspaces.json');
        $this->loadCache();
    }

    /**
     * Aviso de ruta para un PIREP recien prefiliado, a partir de su OFP de SimBrief.
     *
     * @return array{area: string, type: string, limits: string, leg: string, lat: float, lon: float}|null
     */
    public function warningForPirep(Pirep $pirep): ?array
    {
        $simbrief = $pirep->simbrief;
        $xml = $simbrief?->ofp_xml;

        if (empty($xml)) {
            return null;
        }

        return $this->warningForOfp($xml);
    }

    /**
     * @return array{area: string, type: string, limits: string, leg: string, lat: float, lon: float}|null
     */
    public function warningForOfp(string $ofpXml): ?array
    {
        $points = $this->navlog($ofpXml);

        if (count($points) < 2) {
            return null;
        }

        foreach ($this->areasAlong($points) as $area) {
            $leg = $this->firstCrossing($points, $area);

            if ($leg !== null) {
                return [
                    'area'   => $area['name'],
                    'type'   => $area['type'],
                    'limits' => $area['lower'].'–'.$area['upper'],
                    'leg'    => $leg['from'].'–'.$leg['to'],
                    'lat'    => $leg['lat'],
                    'lon'    => $leg['lon'],
                ];
            }
        }

        return null;
    }

    /**
     * Puntos del navlog del OFP: [{ident, lat, lon}, ...]
     *
     * @return array<int, array{ident: string, lat: float, lon: float}>
     */
    public function navlog(string $ofpXml): array
    {
        $out = [];

        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($ofpXml);

        if ($xml === false || !isset($xml->navlog)) {
            libxml_use_internal_errors($prev);

            return [];
        }

        foreach ($xml->navlog->fix as $fix) {
            $lat = (float) $fix->pos_lat;
            $lon = (float) $fix->pos_long;

            if ($lat === 0.0 && $lon === 0.0) {
                continue;
            }

            $out[] = ['ident' => trim((string) $fix->ident) ?: '?', 'lat' => $lat, 'lon' => $lon];
        }

        libxml_use_internal_errors($prev);

        return $out;
    }

    /**
     * Areas restringidas/prohibidas que caen en la ruta (celda a celda, con cache).
     *
     * @param  array<int, array{ident: string, lat: float, lon: float}> $points
     * @return array<int, array<string, mixed>>
     */
    private function areasAlong(array $points): array
    {
        $names = [];

        foreach ($points as $p) {
            $key = floor($p['lat'] / self::CELL).','.floor($p['lon'] / self::CELL);

            if (!isset($this->cells[$key])) {
                $this->fetchCell($key);
            }

            foreach ($this->cells[$key] ?? [] as $name) {
                $names[$name] = true;
            }
        }

        $out = [];
        foreach (array_keys($names) as $name) {
            if (isset($this->areas[$name]) && in_array($this->areas[$name]['type'], self::BLOCKING, true)) {
                $out[] = $this->areas[$name];
            }
        }

        return $out;
    }

    /**
     * Primer tramo que cruza el area: muestrea cada tramo cada ~1 nm y devuelve el punto.
     *
     * @return array{from: string, to: string, lat: float, lon: float}|null
     */
    private function firstCrossing(array $points, array $area): ?array
    {
        for ($i = 1; $i < count($points); $i++) {
            $a = $points[$i - 1];
            $b = $points[$i];
            $dist = $this->nm($a['lat'], $a['lon'], $b['lat'], $b['lon']);
            $steps = max(1, (int) ceil($dist));

            for ($s = 0; $s <= $steps; $s++) {
                $t = $steps === 0 ? 0.0 : $s / $steps;
                $lat = $a['lat'] + ($b['lat'] - $a['lat']) * $t;
                $lon = $a['lon'] + ($b['lon'] - $a['lon']) * $t;

                foreach ($area['rings'] as $ring) {
                    if ($this->inRing($lat, $lon, $ring)) {
                        return ['from' => $a['ident'], 'to' => $b['ident'], 'lat' => $lat, 'lon' => $lon];
                    }
                }
            }
        }

        return null;
    }

    private function nm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dy = ($lat2 - $lat1) * 60;
        $dx = ($lon2 - $lon1) * 60 * cos(deg2rad(($lat1 + $lat2) / 2));

        return sqrt($dx * $dx + $dy * $dy);
    }

    private function inRing(float $lat, float $lon, array $ring): bool
    {
        $c = false;
        $n = count($ring);

        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $ring[$i];
            [$x2, $y2] = $ring[($i + 1) % $n];

            if ((($y1 > $lat) !== ($y2 > $lat)) && ($lon < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1)) {
                $c = !$c;
            }
        }

        return $c;
    }

    private function fetchCell(string $key): void
    {
        [$ci, $cj] = array_map('floatval', explode(',', $key));
        $lat = ($ci + 0.5) * self::CELL;
        $lon = ($cj + 0.5) * self::CELL;

        try {
            $resp = Http::withHeaders([
                'X-API-Key'       => (string) setting('general.navdata_api_key'),
                'X-Origin-Domain' => 'vholar.co',
            ])->timeout(15)->get(rtrim((string) setting('general.navdata_api_url'), '/').'/airspaces/', [
                'lat' => $lat,
                'lon' => $lon,
            ]);

            if (!$resp->ok()) {
                $this->cells[$key] = [];

                return;
            }

            $data = $resp->json();
            $items = $data['data'] ?? $data['airspaces'] ?? $data ?? [];
            $names = [];

            foreach ((array) $items as $item) {
                if (!is_array($item) || !in_array((string) ($item['type'] ?? ''), ['Restricted', 'Prohibited', 'Danger'], true)) {
                    continue;
                }

                $name = (string) ($item['name'] ?? '');
                if ($name === '' || !isset($item['geometry']['coordinates'])) {
                    continue;
                }

                $this->areas[$name] = [
                    'name'  => $name,
                    'type'  => (string) $item['type'],
                    'lower' => $item['lower_limit']['display'] ?? '',
                    'upper' => $item['upper_limit']['display'] ?? '',
                    'rings' => $this->rings($item['geometry']['coordinates']),
                ];

                $names[] = $name;
            }

            $this->cells[$key] = array_values(array_unique($names));
            $this->saveCache();
        } catch (\Throwable $e) {
            $this->cells[$key] = [];
        }
    }

    /**
     * @return array<int, array<int, array{0: float, 1: float}>> [lon, lat]
     */
    private function rings(mixed $coords): array
    {
        $rings = [];

        $collect = function ($o) use (&$collect, &$rings) {
            if (is_array($o) && $o !== [] && is_array($o[0]) && isset($o[0][0]) && is_numeric($o[0][0])) {
                $rings[] = array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $o);
            } elseif (is_array($o)) {
                foreach ($o as $x) {
                    $collect($x);
                }
            }
        };

        $collect($coords);

        return $rings;
    }

    private function loadCache(): void
    {
        if (!file_exists($this->cacheFile)) {
            return;
        }

        $data = json_decode((string) file_get_contents($this->cacheFile), true);

        if (is_array($data)) {
            $this->areas = $data['areas'] ?? [];
            $this->cells = $data['cells'] ?? [];
        }
    }

    private function saveCache(): void
    {
        if (!is_dir(dirname($this->cacheFile))) {
            @mkdir(dirname($this->cacheFile), 0775, true);
        }

        @file_put_contents($this->cacheFile, json_encode([
            'updated_at' => now()->toIso8601String(),
            'areas'      => $this->areas,
            'cells'      => $this->cells,
        ], JSON_UNESCAPED_UNICODE));
    }
}
