<?php

namespace App\Console\Commands;

use App\Models\Enums\AcarsType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Auditoria de trazas contra espacios aereos restringidos (SÓLO LECTURA).
 *
 * No hay waypoints de la ruta planificada en la base (los PIREPs guardan el texto de la
 * ruta, sin coordenadas; no hay puntos ACARS de tipo ROUTE ni OFP de SimBrief guardados),
 * asi que se audita la TRAZA REAL de cada PIREP contra las areas restringidas de NavData.
 * La ruta planificada se muestra en el informe para poder cruzarla a ojo.
 *
 * Las areas se piden a NavData por celda de 0,25 grados (el endpoint devuelve las areas
 * en un radio alrededor del punto) y se cachean en storage/app/auditoria/airspaces.json.
 *
 *   php artisan phpvms:airspace-audit --since=14
 *   php artisan phpvms:airspace-audit --pirep=o5aODZ3590nKmYzj
 *   php artisan phpvms:airspace-audit --since=60 --json > audit.json
 */
class AirspaceAudit extends Command
{
    protected $signature = 'phpvms:airspace-audit
        {--since=30 : Dias hacia atras}
        {--pirep= : Auditar solo este PIREP}
        {--source= : Filtrar por source_name (LIKE)}
        {--limit=0 : Maximo de PIREPs (0 = todos)}
        {--min-nm=3 : Reportar areas a menos de X nm de la traza}
        {--json : Salida JSON}
        {--refresh : Ignorar la cache de areas}';

    protected $description = 'Cruza las trazas de los PIREPs con las areas restringidas de NavData (solo lectura)';

    private const CELL = 0.25;

    private array $areas = [];      // nombre => ['name','type','lower','upper','ring']

    private array $cells = [];      // "lat,lon" => [nombres]

    // Consultas hechas por AirspaceService (centro + radio); el audit NO las usa para buscar,
    // pero las conserva al guardar para no tirar la cache del prefile.
    private array $coverage = [];

    private int $apiCalls = 0;

    private int $apiErrors = 0;

    private string $cacheFile;

    public function handle(): int
    {
        $this->cacheFile = storage_path('app/auditoria/airspaces.json');

        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0775, true);
        }

        $this->loadCache();

        $query = DB::table('pireps')
            ->whereNotNull('pireps.submitted_at')
            ->orderByDesc('pireps.submitted_at');

        if ($id = $this->option('pirep')) {
            $query->where('pireps.id', $id);
        } else {
            $query->where('pireps.submitted_at', '>=', now()->subDays((int) $this->option('since')));
        }

        if ($source = $this->option('source')) {
            $query->where('pireps.source_name', 'like', $source.'%');
        }

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $pireps = $query->get(['pireps.id', 'pireps.flight_number', 'pireps.dpt_airport_id', 'pireps.arr_airport_id', 'pireps.submitted_at', 'pireps.route', 'pireps.source_name']);

        if (!$this->option('json')) {
            $this->line(sprintf('Auditando %d PIREPs (desde %s)%s', count($pireps), $this->option('pirep') ?: now()->subDays((int) $this->option('since'))->toDateString(), $this->option('refresh') ? ' [sin cache]' : ''));
        }

        $findings = [];

        foreach ($pireps as $pirep) {
            $track = DB::table('acars')
                ->where('pirep_id', $pirep->id)
                ->where('type', AcarsType::FLIGHT_PATH)
                ->whereNotNull('lat')
                ->whereNotNull('lon')
                ->get(['sim_time', 'lat', 'lon', 'altitude_msl', 'altitude_agl']);

            if ($track->isEmpty()) {
                continue;
            }

            // muestreo: una de cada 5 posiciones (suficiente para no saltarse un area)
            $samples = [];
            foreach ($track->values() as $i => $p) {
                if ($i % 5 !== 0) {
                    continue;
                }
                $samples[] = ['lat' => (float) $p->lat, 'lon' => (float) $p->lon, 'alt' => (float) $p->altitude_msl, 't' => $p->sim_time];
            }

            $areas = $this->areasForSamples($samples);

            foreach ($areas as $area) {
                $verdict = $this->checkArea($samples, $area);

                if ($verdict['inside'] === null) {
                    continue;
                }

                if ($verdict['inside'] > 0 || $verdict['min_nm'] <= (float) $this->option('min-nm')) {
                    $findings[] = [
                        'id'         => $pirep->id,
                        'flight'     => $pirep->flight_number,
                        'route'      => $pirep->dpt_airport_id.'-'.$pirep->arr_airport_id,
                        'date'       => substr((string) $pirep->submitted_at, 0, 16),
                        'plan'       => trim((string) $pirep->route),
                        'cliente'    => $pirep->source_name,
                        'area'       => $area['name'],
                        'type'       => $area['type'],
                        'limites'    => $area['lower'].'–'.$area['upper'],
                        'activacion' => $area['activacion'] ?? '?',
                        'dentro'     => $verdict['inside'],
                        'min_nm'     => round($verdict['min_nm'], 1),
                        'entrada'    => $verdict['first'],
                        'salida'     => $verdict['last'],
                    ];
                }
            }
        }

        usort($findings, fn ($a, $b) => $b['dentro'] <=> $a['dentro'] ?: $a['min_nm'] <=> $b['min_nm']);

        if ($this->option('json')) {
            $this->line(json_encode(['findings' => $findings, 'api_calls' => $this->apiCalls, 'api_errors' => $this->apiErrors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $inside = array_values(array_filter($findings, fn ($f) => $f['dentro'] > 0));

        $this->newLine();
        $this->info('INTRUSIONES (traza dentro del area):');
        if ($inside) {
            $this->table(['PIREP', 'Vuelo', 'Ruta', 'Fecha', 'Area', 'Activacion', 'Dentro', 'Entrada', 'Salida'], array_map(fn ($f) => [
                $f['id'], $f['flight'], $f['route'], $f['date'], $f['area'], $f['activacion'],
                $f['dentro'].' pts / '.$f['min_nm'].' nm',
                $f['entrada'] ? substr($f['entrada'], 11, 8) : '—',
                $f['salida'] ? substr($f['salida'], 11, 8) : '—',
            ], $inside));
        } else {
            $this->line('  ninguna');
        }

        $near = array_values(array_filter($findings, fn ($f) => $f['dentro'] === 0));
        if ($near) {
            $this->newLine();
            $this->info('PASOS CERCANOS (sin entrar, a '.$this->option('min-nm').' nm o menos):');
            $this->table(['PIREP', 'Vuelo', 'Ruta', 'Fecha', 'Area', 'Activacion', 'Min nm'], array_map(fn ($f) => [
                $f['id'], $f['flight'], $f['route'], $f['date'], $f['area'], $f['activacion'], $f['min_nm'],
            ], array_slice($near, 0, 40)));
        }

        $porArea = [];
        foreach ($inside as $f) {
            $porArea[$f['area']] = ($porArea[$f['area']] ?? 0) + 1;
        }
        arsort($porArea);
        $this->newLine();
        $this->line(sprintf('Resumen: %d PIREPs auditados · %d con intrusion · %d cercanos · %d llamadas a NavData (%d errores)',
            count($pireps), count($inside), count($near), $this->apiCalls, $this->apiErrors));
        if ($porArea) {
            $this->line('Areas mas invadidas:');
            foreach ($porArea as $area => $n) {
                $this->line(sprintf('  %-28s %d', $area, $n));
            }
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- areas

    /**
     * Areas restringidas que afectan a una lista de muestras, usando la cache por celda.
     *
     * @param  array<int, array<string, mixed>> $samples
     * @return array<int, array<string, mixed>>
     */
    private function areasForSamples(array $samples): array
    {
        $names = [];

        foreach ($samples as $s) {
            $key = $this->cellKey($s['lat'], $s['lon']);

            if (!isset($this->cells[$key])) {
                $this->fetchCell($key, $s['lat'], $s['lon']);
            }

            foreach ($this->cells[$key] ?? [] as $name) {
                $names[$name] = true;
            }
        }

        $out = [];
        foreach (array_keys($names) as $name) {
            if (isset($this->areas[$name])) {
                $out[] = $this->areas[$name];
            }
        }

        return $out;
    }

    private function cellKey(float $lat, float $lon): string
    {
        return floor($lat / self::CELL).','.floor($lon / self::CELL);
    }

    private function fetchCell(string $key, float $lat, float $lon): void
    {
        if ($this->option('refresh')) {
            $this->cells[$key] = [];
        }

        [$ci, $cj] = array_map('floatval', explode(',', $key));
        $lat0 = ($ci + 0.5) * self::CELL;
        $lon0 = ($cj + 0.5) * self::CELL;

        $url = rtrim((string) setting('general.navdata_api_url'), '/').'/airspaces/';
        $key_api = (string) setting('general.navdata_api_key');

        try {
            $resp = Http::withHeaders([
                'X-API-Key'       => $key_api,
                'X-Origin-Domain' => 'vholar.co',
            ])->timeout(25)->get($url, ['lat' => $lat0, 'lon' => $lon0]);

            $this->apiCalls++;

            if (!$resp->ok()) {
                $this->apiErrors++;

                return;
            }

            $data = $resp->json();
            $items = $data['data'] ?? $data['airspaces'] ?? $data ?? [];

            $names = [];
            foreach ((array) $items as $item) {
                // Restricted y Prohibited son incumplimientos; Danger se informa pero es
                // advertencia (no prohibicion legal).
                $tipo = (string) ($item['type'] ?? '');
                if (!is_array($item) || !in_array($tipo, ['Restricted', 'Prohibited', 'Danger'], true)) {
                    continue;
                }

                $name = (string) ($item['name'] ?? '');
                $coords = $item['geometry']['coordinates'] ?? null;
                if ($name === '' || $coords === null) {
                    continue;
                }

                $porNotam = (bool) ($item['by_notam'] ?? false);
                $aDemanda = (bool) ($item['on_demand'] ?? false) || (bool) ($item['on_request'] ?? false);

                $this->areas[$name] = [
                    'name'  => $name,
                    'type'  => $tipo,
                    'lower' => $item['lower_limit']['display'] ?? (string) ($item['lower_limit']['value_ft'] ?? ''),
                    'upper' => $item['upper_limit']['display'] ?? (string) ($item['upper_limit']['value_ft'] ?? ''),
                    'activacion' => $porNotam ? 'por NOTAM' : ($aDemanda ? 'a demanda' : 'permanente'),
                    'rings' => $this->rings($coords),
                ];

                $names[] = $name;
            }

            $this->cells[$key] = array_values(array_unique($names));
            $this->saveCache();
            usleep(120000);
        } catch (\Throwable $e) {
            $this->apiErrors++;
            $this->cells[$key] = [];
        }
    }

    /**
     * @return array<int, array<int, array{0: float, 1: float}>> anillos [lon, lat]
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

    // ---------------------------------------------------------------- geometria

    /**
     * @param  array<int, array<string, mixed>> $samples
     * @param  array<string, mixed> $area
     * @return array{inside: int|null, min_nm: float, first: ?string, last: ?string}
     */
    private function checkArea(array $samples, array $area): array
    {
        $inside = 0;
        $first = null;
        $last = null;
        $min = INF;

        foreach ($samples as $s) {
            $isIn = false;

            foreach ($area['rings'] as $ring) {
                if ($this->inRing($s['lat'], $s['lon'], $ring)) {
                    $isIn = true;
                    break;
                }
                $min = min($min, $this->distToRing($s['lat'], $s['lon'], $ring));
            }

            if ($isIn) {
                $inside++;
                $first ??= $s['t'];
                $last = $s['t'];
            }
        }

        if ($min === INF) {
            $min = 0.0;
        }

        return ['inside' => $inside, 'min_nm' => $min, 'first' => $first, 'last' => $last];
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

    private function distToRing(float $lat, float $lon, array $ring): float
    {
        $best = INF;
        $kx = cos(deg2rad($lat)) * 60.0;
        $n = count($ring);

        for ($i = 0; $i < $n; $i++) {
            [$ax, $ay] = $ring[$i];
            [$bx, $by] = $ring[($i + 1) % $n];

            $px = $lon * $kx;
            $py = $lat * 60.0;
            $qx = $ax * $kx;
            $qy = $ay * 60.0;
            $rx = $bx * $kx;
            $ry = $by * 60.0;

            $dx = $rx - $qx;
            $dy = $ry - $qy;
            $len = $dx * $dx + $dy * $dy;
            $t = $len == 0.0 ? 0.0 : max(0.0, min(1.0, (($px - $qx) * $dx + ($py - $qy) * $dy) / $len));

            $best = min($best, hypot($px - ($qx + $t * $dx), $py - ($qy + $t * $dy)));
        }

        return $best;
    }

    // ---------------------------------------------------------------- cache

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
    }

    private function saveCache(): void
    {
        file_put_contents($this->cacheFile, json_encode([
            'updated_at' => now()->toIso8601String(),
            'areas'      => $this->areas,
            'cells'      => $this->cells,
            'coverage'   => $this->coverage,
        ], JSON_UNESCAPED_UNICODE));
    }
}
