<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Pirep;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Revision previa de la ruta contra los espacios aereos de NavData.
 *
 * La usa `PirepService::prefile()`: el cliente prefila ANTES de despegar, asi que el aviso
 * llega a tiempo de corregir el plan (al presentar el PIREP ya seria tarde).
 *
 * Fuentes, en este orden:
 *   1. `route_navlog` (version 1) que manda el cliente: "IDENT,lat,lon,alt;IDENT,lat,lon,alt;..."
 *   2. el OFP de SimBrief pegado al PIREP (`simbrief_id`), cuyo `navlog` trae lat/lon/altitud.
 *   3. nada: no hay aviso.
 *
 * La altitud es parte del dato, no un extra: sin ella un SID que pasa por encima de un area
 * GND-3000ft MSL a 11.500ft se marcaria como incursion. Solo se avisa si el punto de cruce
 * esta DENTRO de los limites del area.
 *
 * Las areas se piden a NavData alrededor de un punto; el endpoint garantiza 200 NM de
 * cobertura (indice local) o ~108 NM (API REST), asi que una peticion cubre una franja
 * ancha. La cache (`storage/app/auditoria/airspaces.json`, la misma que usa
 * phpvms:airspace-audit) guarda, por cada consulta, su centro y radio; una consulta nueva
 * solo se hace cuando la ruta sale de toda la cobertura ya conocida.
 *
 * Nunca lanza: si no hay ruta, no hay red o NavData falla, devuelve null y el prefile sigue.
 */
class AirspaceService extends Service
{
    /** Rejilla de la cache historica por celdas (solo se usa para leer caches viejas). */
    private const CELL = 0.25;

    /** Tipos que cuentan como incumplimiento; Danger se guarda pero no avisa. */
    private const BLOCKING = ['Restricted', 'Prohibited'];

    /** Tipos que se conservan en cache (Danger se guarda para no volver a pedirlo). */
    private const KEEP = ['Restricted', 'Prohibited', 'Danger'];

    /**
     * Radio con el que se planifica antes de conocer el real. 100 NM queda por debajo del
     * minimo garantizado (~108 NM con la API REST), asi que nunca se da por cubierto un
     * punto que la respuesta no cubria: en el peor caso se pide de mas, nunca de menos.
     */
    private const PROVISIONAL_RADIUS = 100.0;

    /** Radio que se asume para las celdas 0,25 de una cache anterior a `coverage`. */
    private const LEGACY_RADIUS = 100.0;

    /** Separacion entre muestras de la RUTA para comprobar si hay cobertura. */
    private const SAMPLE_NM = 25.0;

    /** Peticiones simultaneas a NavData. */
    private const POOL_SIZE = 12;

    /** Techo para limites que no se pueden leer (equivale a "sin techo practico"). */
    private const MAX_ALT_FT = 60000.0;

    /** @var array<string, array<string, mixed>> nombre => area */
    private array $areas = [];

    /** @var array<string, array<int, string>> rejilla 0,25 para compatibilidad con el audit */
    private array $cells = [];

    /** @var array<int, array{lat: float, lon: float, radius: float, names: array<int, string>}> */
    private array $coverage = [];

    private string $cacheFile;

    public function __construct(?string $cacheFile = null)
    {
        $this->cacheFile = $cacheFile ?? storage_path('app/auditoria/airspaces.json');
        $this->loadCache();
    }

    /**
     * Aviso de ruta para un PIREP recien prefiliado.
     *
     * Manda el navlog del cliente (v1); si no viene, el OFP de SimBrief. `$navlogVersion`
     * solo se acepta como 1: cualquier otra version se ignora.
     *
     * @return array{area: string, type: string, limits: string, leg: string, lat: float, lon: float, alt: float|null}|null
     */
    public function warningForPirep(Pirep $pirep, ?string $routeNavlog = null, ?int $navlogVersion = null): ?array
    {
        if ($routeNavlog !== null && trim($routeNavlog) !== '' && ($navlogVersion === null || $navlogVersion === 1)) {
            $points = $this->parseRouteNavlog($routeNavlog);

            if (count($points) >= 2) {
                return $this->warningForRoute($points);
            }
        }

        // Respaldo: el OFP de SimBrief, si el `simbrief_id` resolvio a un OFP local.
        $xml = $pirep->simbrief?->ofp_xml;

        if (empty($xml)) {
            return null;
        }

        return $this->warningForOfp($xml);
    }

    /**
     * @return array{area: string, type: string, limits: string, leg: string, lat: float, lon: float, alt: float|null}|null
     */
    public function warningForOfp(string $ofpXml): ?array
    {
        return $this->warningForRoute($this->navlog($ofpXml));
    }

    /**
     * Aviso de ruta para una lista de puntos con altitud opcional (ver warningForPirep).
     *
     * @return array{area: string, type: string, limits: string, leg: string, lat: float, lon: float, alt: float|null}|null
     */
    public function warningForRoute(array $points): ?array
    {
        $points = array_values(array_filter($points, fn ($p) => isset($p['lat'], $p['lon'])
            && is_numeric($p['lat']) && is_numeric($p['lon'])));

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
                    'alt'    => $leg['alt'],
                ];
            }
        }

        return null;
    }

    /**
     * Navlog del cliente v1: "IDENT,lat,lon,alt;IDENT,lat,lon,alt;..."
     *
     * Se tolera ademas el tramo sin ident ("lat,lon,alt") y las lineas vacias o mal formadas
     * se descartan en silencio: el prefile nunca debe fallar por esto.
     *
     * @return array<int, array{ident: string, lat: float, lon: float, alt: float|null}>
     */
    public function parseRouteNavlog(string $navlog): array
    {
        $out = [];

        foreach (explode(';', $navlog) as $chunk) {
            $parts = array_map('trim', explode(',', $chunk));

            if (count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2])) {
                // Sin ident: lat,lon,alt
                $parts = ['?', $parts[0], $parts[1], $parts[2]];
            }

            if (count($parts) < 3) {
                continue;
            }

            $lat = (float) $parts[1];
            $lon = (float) $parts[2];

            if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) {
                continue;
            }

            if ($lat === 0.0 && $lon === 0.0) {
                continue;
            }

            $alt = isset($parts[3]) && $parts[3] !== '' && is_numeric($parts[3]) ? (float) $parts[3] : null;

            $out[] = [
                'ident' => trim($parts[0]) !== '' ? trim($parts[0]) : '?',
                'lat'   => $lat,
                'lon'   => $lon,
                'alt'   => $alt,
            ];
        }

        return $out;
    }

    /**
     * Puntos del navlog del OFP de SimBrief: [{ident, lat, lon, alt}, ...]
     *
     * @return array<int, array{ident: string, lat: float, lon: float, alt: float|null}>
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

            $alt = null;

            if (isset($fix->altitude_feet)) {
                $raw = str_replace(',', '', trim((string) $fix->altitude_feet));

                if (is_numeric($raw)) {
                    $alt = (float) $raw;
                }
            }

            $out[] = ['ident' => trim((string) $fix->ident) ?: '?', 'lat' => $lat, 'lon' => $lon, 'alt' => $alt];
        }

        libxml_use_internal_errors($prev);

        return $out;
    }

    /**
     * Areas restringidas/prohibidas candidatas para la ruta.
     *
     * Primero se asegura cobertura a lo largo de la ruta (muestras cada 25 NM, pidiendo a
     * NavData solo los puntos que caen fuera de toda la cobertura conocida) y despues se
     * devuelven las areas que cubren alguna muestra; el cruce real lo decide firstCrossing().
     *
     * @param  array<int, array{ident: string, lat: float, lon: float, alt: float|null}> $points
     * @return array<int, array<string, mixed>>
     */
    private function areasAlong(array $points): array
    {
        $samples = $this->densify($points, self::SAMPLE_NM);
        $this->ensureCoverage($samples);

        $names = [];

        foreach ($samples as $sample) {
            foreach ($this->namesAt($sample['lat'], $sample['lon']) ?? [] as $name) {
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
     * Pide a NavData los puntos de la ruta que ninguna consulta anterior cubre.
     *
     * @param array<int, array{lat: float, lon: float}> $samples
     */
    private function ensureCoverage(array $samples): void
    {
        $planned = [];

        foreach ($samples as $sample) {
            if ($this->namesAt($sample['lat'], $sample['lon']) !== null) {
                continue;
            }

            if ($this->nearAny($planned, $sample['lat'], $sample['lon'], self::PROVISIONAL_RADIUS)) {
                continue;
            }

            $planned[] = ['lat' => (float) $sample['lat'], 'lon' => (float) $sample['lon']];
        }

        if ($planned !== []) {
            $this->fetchMany($planned);
        }
    }

    /**
     * @param array<int, array{lat: float, lon: float}> $points
     */
    private function fetchMany(array $points): void
    {
        $url = rtrim((string) setting('general.navdata_api_url'), '/').'/airspaces/';
        $headers = [
            'X-API-Key'       => (string) setting('general.navdata_api_key'),
            'X-Origin-Domain' => 'vholar.co',
        ];

        foreach (array_chunk($points, self::POOL_SIZE) as $chunk) {
            $chunk = array_values($chunk);
            $responses = $this->sendRequests($chunk, $url, $headers);

            foreach ($chunk as $i => $point) {
                $response = $responses[(string) $i] ?? null;

                if (!$response instanceof Response || !$response->ok()) {
                    continue;
                }

                try {
                    $this->storeResponse((float) $point['lat'], (float) $point['lon'], (array) $response->json());
                } catch (\Throwable $e) {
                    Log::warning('prefile: respuesta de espacios aereos ilegible: '.$e->getMessage());
                }
            }

            $this->saveCache();
        }
    }

    /**
     * Una peticion suelta no necesita el manejador multiple (y evita su ruido); varias van
     * en paralelo, que es lo que mantiene el prefile por debajo del medio segundo.
     *
     * @param  array<int, array{lat: float, lon: float}> $chunk
     * @return array<string, Response>
     */
    private function sendRequests(array $chunk, string $url, array $headers): array
    {
        try {
            if (count($chunk) === 1) {
                $point = $chunk[0];

                return ['0' => Http::withHeaders($headers)->timeout(15)->get($url, [
                    'lat' => $point['lat'],
                    'lon' => $point['lon'],
                ])];
            }

            /** @var array<string, Response> $responses */
            $responses = Http::pool(function (Pool $pool) use ($chunk, $url, $headers) {
                foreach ($chunk as $i => $point) {
                    $pool->as((string) $i)
                        ->withHeaders($headers)
                        ->timeout(15)
                        ->get($url, ['lat' => $point['lat'], 'lon' => $point['lon']]);
                }
            });

            return $responses;
        } catch (\Throwable $e) {
            // Sin red no se cachea nada: el siguiente prefile lo vuelve a intentar.
            Log::warning('prefile: no se pudieron pedir areas a NavData: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Guarda las areas de una respuesta y anota la cobertura que aporta.
     *
     * @param array<string, mixed> $data
     */
    private function storeResponse(float $lat, float $lon, array $data): void
    {
        $radius = (float) ($data['radius_nm'] ?? self::PROVISIONAL_RADIUS);

        if (!is_finite($radius) || $radius <= 0) {
            $radius = self::PROVISIONAL_RADIUS;
        }

        $items = $data['airspaces'] ?? $data['data'] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        $names = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');

            if (!in_array($type, self::KEEP, true)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $coords = $item['geometry']['coordinates'] ?? null;

            if ($name === '' || $coords === null) {
                continue;
            }

            $rings = $this->rings($coords);

            if ($rings === []) {
                continue;
            }

            $this->areas[$name] = [
                'name'      => $name,
                'type'      => $type,
                'lower'     => (string) ($item['lower_limit']['display'] ?? ''),
                'upper'     => (string) ($item['upper_limit']['display'] ?? ''),
                'lower_ft'  => $this->numberOrNull($item['lower_limit']['value_ft'] ?? null),
                'upper_ft'  => $this->numberOrNull($item['upper_limit']['value_ft'] ?? null),
                'lower_ref' => (string) ($item['lower_limit']['reference'] ?? ''),
                'upper_ref' => (string) ($item['upper_limit']['reference'] ?? ''),
                'rings'     => $rings,
            ];

            $names[] = $name;
        }

        $names = array_values(array_unique($names));

        // Una consulta al mismo punto (mismo prefile repetido) reemplaza a la anterior:
        // sin esto el fichero creceria con cada prefile del mismo aeropuerto.
        $kept = [];

        foreach ($this->coverage as $entry) {
            if ($this->nm($entry['lat'], $entry['lon'], $lat, $lon) <= 1.0) {
                continue;
            }

            $kept[] = $entry;
        }

        $kept[] = [
            'lat'    => round($lat, 4),
            'lon'    => round($lon, 4),
            'radius' => $radius,
            'names'  => $names,
        ];

        $this->coverage = $kept;

        // Compatibilidad con phpvms:airspace-audit, que sigue leyendo la rejilla 0,25.
        $this->cells[$this->cellKey($lat, $lon)] = $names;
    }

    /**
     * Nombres de las areas que cubren un punto, o null si ninguna consulta lo cubre.
     *
     * @return array<int, string>|null
     */
    private function namesAt(float $lat, float $lon): ?array
    {
        $names = [];
        $covered = false;

        foreach ($this->coverage as $entry) {
            if ($this->nm($lat, $lon, $entry['lat'], $entry['lon']) > $entry['radius']) {
                continue;
            }

            $covered = true;

            foreach ($entry['names'] as $name) {
                $names[$name] = true;
            }
        }

        return $covered ? array_keys($names) : null;
    }

    /**
     * @param array<int, array{lat: float, lon: float}> $points
     */
    private function nearAny(array $points, float $lat, float $lon, float $radius): bool
    {
        foreach ($points as $point) {
            if ($this->nm($lat, $lon, $point['lat'], $point['lon']) <= $radius) {
                return true;
            }
        }

        return false;
    }

    /**
     * Interpola muestras a lo largo de la ruta (no sustituye a los puntos originales).
     *
     * @param  array<int, array{ident: string, lat: float, lon: float, alt: float|null}> $points
     * @return array<int, array{ident: string, lat: float, lon: float, alt: float|null}>
     */
    private function densify(array $points, float $stepNm): array
    {
        $out = [];

        foreach ($points as $point) {
            if ($out === []) {
                $out[] = $point;

                continue;
            }

            $prev = $out[count($out) - 1];
            $dist = $this->nm($prev['lat'], $prev['lon'], $point['lat'], $point['lon']);
            $steps = (int) ceil($dist / max(1.0, $stepNm));

            for ($s = 1; $s < $steps; $s++) {
                $t = $s / $steps;
                $out[] = [
                    'ident' => '~',
                    'lat'   => $prev['lat'] + ($point['lat'] - $prev['lat']) * $t,
                    'lon'   => $prev['lon'] + ($point['lon'] - $prev['lon']) * $t,
                    'alt'   => $this->altAt($prev['alt'], $point['alt'], $t),
                ];
            }

            $out[] = $point;
        }

        return $out;
    }

    /**
     * Primer tramo que cruza el area, con la altitud en el punto de cruce.
     *
     * @return array{from: string, to: string, lat: float, lon: float, alt: float|null}|null
     */
    private function firstCrossing(array $points, array $area): ?array
    {
        for ($i = 1; $i < count($points); $i++) {
            $a = $points[$i - 1];
            $b = $points[$i];
            $dist = $this->nm($a['lat'], $a['lon'], $b['lat'], $b['lon']);
            $steps = max(1, (int) ceil($dist));

            for ($s = 0; $s <= $steps; $s++) {
                $t = $s / $steps;
                $lat = $a['lat'] + ($b['lat'] - $a['lat']) * $t;
                $lon = $a['lon'] + ($b['lon'] - $a['lon']) * $t;
                $alt = $this->altAt($a['alt'] ?? null, $b['alt'] ?? null, $t);

                if (!$this->insideAltitude($alt, $area)) {
                    continue;
                }

                foreach ($area['rings'] as $ring) {
                    if ($this->inRing($lat, $lon, $ring)) {
                        return ['from' => $a['ident'], 'to' => $b['ident'], 'lat' => $lat, 'lon' => $lon, 'alt' => $alt];
                    }
                }
            }
        }

        return null;
    }

    private function altAt(?float $from, ?float $to, float $t): ?float
    {
        if ($from === null && $to === null) {
            return null;
        }

        if ($from === null) {
            return $to;
        }

        if ($to === null) {
            return $from;
        }

        return $from + ($to - $from) * $t;
    }

    /**
     * Altitud dentro de los limites del area. Sin altitud no se puede decidir: se avisa
     * (comportamiento anterior a este cambio) en vez de callar un cruce real.
     *
     * @param array<string, mixed> $area
     */
    private function insideAltitude(?float $alt, array $area): bool
    {
        if ($alt === null) {
            return true;
        }

        $lower = $this->limitFeet($area, 'lower');
        $upper = $this->limitFeet($area, 'upper');

        if ($lower === null && $upper === null) {
            return true;
        }

        if ($lower === null) {
            $lower = 0.0;
        }

        if ($upper === null) {
            $upper = self::MAX_ALT_FT;
        }

        return $alt >= $lower && $alt <= $upper;
    }

    /**
     * @param array<string, mixed> $area
     */
    private function limitFeet(array $area, string $which): ?float
    {
        $ft = $area[$which.'_ft'] ?? null;

        if (is_numeric($ft)) {
            return (float) $ft;
        }

        return $this->feetFromDisplay($area[$which] ?? null);
    }

    private function feetFromDisplay(mixed $display): ?float
    {
        $text = strtoupper(trim((string) $display));

        if ($text === '') {
            return null;
        }

        if (in_array($text, ['GND', 'SFC', 'SURFACE'], true)) {
            return 0.0;
        }

        if (str_starts_with($text, 'UNL')) {
            return self::MAX_ALT_FT;
        }

        if (preg_match('/^FL\s*(\d{2,3})/', $text, $m)) {
            return (float) $m[1] * 100;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)/', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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

    private function cellKey(float $lat, float $lon): string
    {
        return floor($lat / self::CELL).','.floor($lon / self::CELL);
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

        if (!is_array($data)) {
            return;
        }

        $this->areas = $data['areas'] ?? [];
        $this->cells = $data['cells'] ?? [];
        $this->coverage = $data['coverage'] ?? [];

        // Cache anterior a `coverage`: cada celda 0,25 se trata como una consulta con el
        // radio minimo garantizado. Es conservador a proposito (pedir de mas, nunca de menos).
        if ($this->coverage === [] && $this->cells !== []) {
            foreach ($this->cells as $key => $names) {
                [$ci, $cj] = array_map('floatval', explode(',', (string) $key));

                $this->coverage[] = [
                    'lat'    => ($ci + 0.5) * self::CELL,
                    'lon'    => ($cj + 0.5) * self::CELL,
                    'radius' => self::LEGACY_RADIUS,
                    'names'  => (array) $names,
                ];
            }
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
            'coverage'   => $this->coverage,
        ], JSON_UNESCAPED_UNICODE));
    }
}
