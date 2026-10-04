<?php

namespace App\Helpers;

use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use Illuminate\Support\Collection;

class FlightAnalysisHelper
{
    /**
     * Obtener el punto de despegue (status = TAKEOFF)
     */
    public static function getTakeoffPoint(Pirep $pirep): ?Acars
    {
        return $pirep->acars()
            ->where('type', AcarsType::FLIGHT_PATH)
            ->where('status', PirepStatus::TAKEOFF)
            ->orderBy('order', 'asc')
            ->first();
    }

    /**
     * Obtener el punto de aterrizaje (status = LANDING o LANDED)
     */
    public static function getLandingPoint(Pirep $pirep): ?Acars
    {
        return $pirep->acars()
            ->where('type', AcarsType::FLIGHT_PATH)
            ->whereIn('status', [PirepStatus::LANDING, PirepStatus::LANDED])
            ->orderBy('order', 'desc')
            ->first();
    }

    /**
     * Obtener puntos por fase usando status
     */
    public static function getPointsByStatus(Pirep $pirep, string $status): Collection
    {
        return $pirep->acars()
            ->where('type', AcarsType::FLIGHT_PATH)
            ->where('status', $status)
            ->orderBy('order', 'asc')
            ->get();
    }

    /**
     * Obtener el perfil de altitud (todos los puntos)
     */
    public static function getAltitudeProfile(Pirep $pirep): Collection
    {
        return $pirep->acars()
            ->whereIn('type', [AcarsType::FLIGHT_PATH, AcarsType::ROUTE])
            ->where('status', '!=', 'CHK')
            ->orderBy('order', 'asc')
            ->get(['order', 'altitude_msl', 'gs', 'ias', 'status', 'sim_time', 'lat', 'lon']);
    }

    /**
     * Obtener el landing rate
     */
    public static function getLandingRate(Pirep $pirep): ?int
    {
        $landing = self::getLandingPoint($pirep);

        return $landing ? (int) $landing->vs : null;
    }

    /**
     * Obtener el log de eventos
     */
    public static function getFlightLog(Pirep $pirep): Collection
    {
        return $pirep->acars()
            ->where('type', AcarsType::LOG)
            ->orderBy('order', 'asc')
            ->get();
    }

    /**
     * Analizar el rendimiento completo del vuelo
     */
    public static function analyzePerformance(Pirep $pirep): array
    {
        $takeoff = self::getTakeoffPoint($pirep);
        $landing = self::getLandingPoint($pirep);
        $allPoints = $pirep->acars()
            ->where('type', AcarsType::FLIGHT_PATH)
            ->orderBy('order', 'asc')
            ->get();

        $maxAltitude = 0;
        $maxSpeed = 0;
        $overspeedDetected = false;
        $stallDetected = false;
        $crashDetected = false;
        $slewDetected = false;

        foreach ($allPoints as $point) {
            if ($point->altitude_msl > $maxAltitude) {
                $maxAltitude = $point->altitude_msl;
            }
            if ($point->gs > $maxSpeed) {
                $maxSpeed = $point->gs;
            }
            // Overspeed: más de 350kt por debajo de 10000ft
            if ($point->gs > 350 && $point->altitude_msl < 10000) {
                $overspeedDetected = true;
            }
            // Stall: velocidad baja a alta altitud
            if ($point->ias < 120 && $point->altitude_msl > 20000) {
                $stallDetected = true;
            }
            if (isset($point->gforce) && $point->gforce > 3.0) {
                $crashDetected = true;
            }
        }

        return [
            'takeoff'            => $takeoff,
            'landing'            => $landing,
            'max_altitude'       => $maxAltitude,
            'max_speed'          => $maxSpeed,
            'overspeed_detected' => $overspeedDetected,
            'stall_detected'     => $stallDetected,
            'crash_detected'     => $crashDetected,
            'slew_detected'      => $slewDetected,
            'total_points'       => $allPoints->count(),
        ];
    }

    /**
     * Calcular la puntuación de rendimiento (0-100)
     */
    public static function calculatePerformanceScore(Pirep $pirep): int
    {
        $analysis = self::analyzePerformance($pirep);
        $landingRate = self::getLandingRate($pirep);

        $score = 100;

        if ($landingRate) {
            if ($landingRate < -500) {
                $score -= 30;
            } elseif ($landingRate < -400) {
                $score -= 20;
            } elseif ($landingRate < -300) {
                $score -= 10;
            } elseif ($landingRate < -200) {
                $score -= 5;
            }
        }

        if ($analysis['overspeed_detected']) {
            $score -= 15;
        }
        if ($analysis['stall_detected']) {
            $score -= 25;
        }
        if ($analysis['crash_detected']) {
            $score -= 50;
        }

        return max(0, min(100, $score));
    }

    /**
     * Parse structured data from ACARS log entries (type=LOG, source=vmsOp)
     */
    public static function parseLogData(Pirep $pirep): array
    {
        // Se traen tambien las marcas de tiempo: las fases (pushback, taxi,
        // vuelo, rodaje de llegada) solo se pueden medir con ellas. El primer
        // bucle sigue trabajando con el texto, como siempre.
        $logRows = Acars::where('pirep_id', $pirep->id)
            ->where('type', AcarsType::LOG)
            ->orderBy('created_at', 'asc')
            ->get(['log', 'created_at']);

        $logs = $logRows->pluck('log')->toArray();

        if (empty($logs)) {
            return [];
        }

        $result = [
            'score'      => null,
            'penalties'  => [],
            'bonuses'    => [],
            'takeoff'    => [],
            'landing'    => [],
            'approach'   => ['stabilized' => null],
            'flight'     => [],
            'operations' => self::parseOperations($logRows),
            'aircraft'   => null,
            'network'    => null,
        ];

        $context = null; // 'takeoff' | 'landing'
        $oatList = [];   // collect all OAT/Wind pairs; assign first=takeoff, last=landing

        foreach ($logs as $log) {
            // ── Context markers ───────────────────────────────────────────
            if (str_contains($log, 'ACCURATE TAKEOFF DATA') || str_contains($log, 'DATOS DE DESPEGUE')) {
                $context = 'takeoff';

                continue;
            }
            if (str_contains($log, 'ACCURATE TOUCHDOWN DATA') || str_contains($log, 'DATOS DE ATERRIZAJE')) {
                $context = 'landing';

                continue;
            }
            if ((str_contains($log, 'Phase changed:') || str_contains($log, 'Gear UP') || str_contains($log, 'Tren de aterrizaje: UP')) && $context === 'takeoff') {
                $context = null;
            }
            if ((str_contains($log, 'TaxiIn') || str_contains($log, 'PIREP Status: TXI')) && $context === 'landing') {
                $context = null;
            }

            // ── Score ─────────────────────────────────────────────────────
            // EN: "Score: 92/100 — Smooth"  ES: "Puntuación: 85/100 — Suave"
            if (preg_match('/(?:Puntuaci[oó]n|Score):\s*(\d+)\/100\s*[—–\-]\s*(.+)/u', $log, $m)) {
                $result['score'] = ['value' => (int) $m[1], 'rating' => trim($m[2])];
            }

            // ── Penalties (final summary lines only: "−N pts: reason") ───
            // Match unicode minus − or ASCII hyphen -, but NOT the ⚠️ PENALTY: early warnings
            if (!str_contains($log, 'PENALTY:') && !str_contains($log, 'PENALIZACIÓN:') &&
                preg_match('/[−\-](\d+)\s*pts?:\s*(.+)/u', $log, $m)) {
                $result['penalties'][] = ['points' => (int) $m[1], 'reason' => trim($m[2])];
            }

            // ── Bonuses ("BONUS reason: +N pts"  /  "BONIFICACIÓN reason: +N pts") ──
            if (preg_match('/(?:BONUS|BONIFICACI[OÓ]N)\s+(.+?):\s*\+(\d+)\s*pts?/iu', $log, $m)) {
                $result['bonuses'][] = ['points' => (int) $m[2], 'reason' => trim($m[1])];
            }

            // ── Takeoff ───────────────────────────────────────────────────
            // EN: "TAKEOFF DETECTED … Speed: X kts … Alt: X ft … VS: X fpm"
            // ES: "DESPEGUE DETECTADO - Vel: X kts, Alt: X ft, VS: X fpm"
            if (preg_match('/TAKEOFF DETECTED.*Speed:\s*(\d+)\s*kts.*Alt:\s*([\d,]+)\s*ft.*VS:\s*(-?\d+)\s*fpm/iu', $log, $m) ||
                preg_match('/DESPEGUE DETECTADO.*Vel:\s*(\d+)\s*kts.*Alt:\s*([\d,]+)\s*ft.*VS:\s*(-?\d+)\s*fpm/iu', $log, $m)) {
                $result['takeoff']['speed_kts'] = (int) str_replace(',', '', $m[1]);
                $result['takeoff']['alt_ft'] = (int) str_replace(',', '', $m[2]);
                $result['takeoff']['vs_fpm'] = (int) $m[3];
                $context = 'takeoff';
            }
            if (preg_match('/ROTATION at (\d+) kts.*Pitch:\s*([\d.]+)/iu', $log, $m)) {
                $result['takeoff']['rotation_kts'] = (int) $m[1];
                $result['takeoff']['pitch_deg'] = (float) $m[2];
            }
            if (preg_match('/LIFTOFF.*Speed:\s*(\d+)\s*kts/iu', $log, $m)) {
                $result['takeoff']['liftoff_kts'] = (int) $m[1];
            }
            // ES: "Velocidad en Tierra: X kts" — appears before DATOS DE DESPEGUE context block
            if (preg_match('/Velocidad en Tierra:\s*(\d+)\s*kts/iu', $log, $m)) {
                $result['takeoff']['gs_kts'] = (int) $m[1];
            }
            if (!isset($result['takeoff']['qnh_delta']) && preg_match('/QNH.*Δ(-?\d+)\s*hPa/iu', $log, $m)) {
                $result['takeoff']['qnh_delta'] = (int) $m[1];
            }
            // Takeoff runway: "PISTA 14L | ALINEACIÓN: 543 ft … CL: 19 ft"
            if (preg_match('/PISTA\s+(\S+)\s*\|\s*ALINEACI[OÓ]N:\s*(\d+)\s*ft.*CL:\s*(\d+)\s*ft/ui', $log, $m)) {
                $result['takeoff']['runway'] = $m[1];
                $result['takeoff']['threshold_ft'] = (int) $m[2];
                $result['takeoff']['centerline_ft'] = (int) $m[3];
            }
            // Data inside the ACCURATE TAKEOFF DATA / DATOS DE DESPEGUE block
            if ($context === 'takeoff') {
                if (preg_match('/(?:Rotation Speed|Velocidad de Rotaci[oó]n):\s*(\d+)\s*kts/iu', $log, $m)) {
                    $result['takeoff']['rotation_kts'] = (int) $m[1];
                }
                if (preg_match('/(?:Ground Speed|Velocidad en Tierra):\s*(\d+)\s*kts/iu', $log, $m)) {
                    $result['takeoff']['gs_kts'] = (int) $m[1];
                }
                if (preg_match('/N1:\s*(\d+)%\s*\/\s*(\d+)%/iu', $log, $m)) {
                    $result['takeoff']['n1'] = [(int) $m[1], (int) $m[2]];
                }
                if (preg_match('/^Flaps:\s*(\d+)%$/iu', $log, $m)) {
                    $result['takeoff']['flaps_pct'] = (int) $m[1];
                }
                // EN: "Pitch: 6.1° | Bank: -0.4°"  ES: "Cabeceo: 6.9° | Alabeo: 0.2°"
                // Decimal separator may be comma (ES locale) or dot — normalize before cast
                if (preg_match('/(?:Pitch|Cabeceo):\s*([\d.,]+)°\s*\|\s*(?:Bank|Alabeo):\s*(-?[\d.,]+)°/iu', $log, $m)) {
                    $result['takeoff']['pitch_deg'] = (float) str_replace(',', '.', $m[1]);
                    $result['takeoff']['bank_deg'] = (float) str_replace(',', '.', $m[2]);
                }
            }

            // ── Landing ───────────────────────────────────────────────────
            // EN: "Touchdown: -216 FPM, 1.48 G, Pitch: 6.1°, Bank: 0.5°"
            if (preg_match('/Touchdown:\s*(-?\d+)\s*FPM,\s*([\d.,]+)\s*G,\s*Pitch:\s*([\d.,]+)°,\s*Bank:\s*([\d.,]+)°/iu', $log, $m)) {
                $result['landing']['vs_fpm'] = (int) $m[1];
                $result['landing']['gforce'] = (float) str_replace(',', '.', $m[2]);
                $result['landing']['pitch_deg'] = (float) str_replace(',', '.', $m[3]);
                $result['landing']['bank_deg'] = (float) str_replace(',', '.', $m[4]);
            }
            // EN: "Landing recorded: -216 fpm, 1.48 G, Heading: 339°, Pitch: 6.1°, Bank: 0.5°"
            // ES: "Aterrizaje registrado: -103 fpm, 1.10 G, Rumbo: 178°, Cabeceo: 4.8°, Alabeo: -0.6°"
            // Decimal separator may be comma (ES locale) or dot — normalize before cast
            if (preg_match('/(?:Landing recorded|Aterrizaje registrado):\s*(-?\d+)\s*fpm,\s*([\d.,]+)\s*G,\s*(?:Heading|Rumbo):\s*(\d+)°,\s*(?:Pitch|Cabeceo):\s*([\d.,]+)°,\s*(?:Bank|Alabeo):\s*(-?[\d.,]+)°/iu', $log, $m)) {
                $result['landing']['vs_fpm'] = (int) $m[1];
                $result['landing']['gforce'] = (float) str_replace(',', '.', $m[2]);
                $result['landing']['heading'] = (int) $m[3];
                $result['landing']['pitch_deg'] = (float) str_replace(',', '.', $m[4]);
                $result['landing']['bank_deg'] = (float) str_replace(',', '.', $m[5]);
            }
            // ES: "Velocidad: 127 kts (IAS) / 133 kts (GS)" — appears before DATOS DE ATERRIZAJE context block
            if (preg_match('/Velocidad:\s*(\d+)\s*kts\s*\(IAS\)\s*\/\s*(\d+)\s*kts\s*\(GS\)/iu', $log, $m)) {
                $result['landing']['ias_kts'] = (int) $m[1];
                $result['landing']['gs_kts'] = (int) $m[2];
            }
            // "Flaps: 40% | Spoilers: 0%" — landing config (format differs from climb: "Flaps: X% → Y%")
            if (preg_match('/^Flaps:\s*(\d+)%\s*\|\s*Spoilers:\s*(\d+)%$/iu', $log, $m)) {
                $result['landing']['flaps_pct'] = (int) $m[1];
                $result['landing']['spoilers_pct'] = (int) $m[2];
            }
            // Landing runway: "PISTA 35 | TD: 1151 ft … CL: 0 ft"
            if (preg_match('/PISTA\s+(\S+)\s*\|\s*TD:\s*(\d+)\s*ft.*CL:\s*(\d+)\s*ft/ui', $log, $m)) {
                $result['landing']['runway'] = $m[1];
                $result['landing']['td_ft'] = (int) $m[2];
                $result['landing']['centerline_ft'] = (int) $m[3];
            }
            // Data inside ACCURATE TOUCHDOWN DATA / DATOS DE ATERRIZAJE block
            if ($context === 'landing') {
                if (preg_match('/VS:\s*(-?\d+)\s*fpm/iu', $log, $m)) {
                    $result['landing']['vs_fpm'] = (int) $m[1];
                }
                // EN: "G-Force: 1.48g (Heavy)"  ES: "Fuerza G: 1.40g (Normal)"
                if (preg_match('/(?:G-Force|Fuerza G):\s*([\d.,]+)g\s*\((.+?)\)/iu', $log, $m)) {
                    $result['landing']['gforce'] = (float) str_replace(',', '.', $m[1]);
                    $result['landing']['gforce_label'] = trim($m[2]);
                }
                if (preg_match('/(?:Speed|Velocidad):\s*(\d+)\s*kts\s*\(IAS\).*?(\d+)\s*kts\s*\(GS\)/iu', $log, $m)) {
                    $result['landing']['ias_kts'] = (int) $m[1];
                    $result['landing']['gs_kts'] = (int) $m[2];
                }
                if (preg_match('/Flaps:\s*(\d+)%\s*\|\s*Spoilers:\s*(\d+)%/iu', $log, $m)) {
                    $result['landing']['flaps_pct'] = (int) $m[1];
                    $result['landing']['spoilers_pct'] = (int) $m[2];
                }
                // EN: "Brakes:…Autobrake: RTO"  ES: "Frenos:…Autobrake: RTO"
                if (preg_match('/(?:Brakes|Frenos).*Autobrake:\s*(\w+)/iu', $log, $m)) {
                    $result['landing']['autobrake'] = $m[1];
                }
                // EN: "Reversers: 0% / 0%"  ES: "Reversas: 0% / 0%"
                if (preg_match('/(?:Reversers|Reversas):\s*(\d+)%\s*\/\s*(\d+)%/iu', $log, $m)) {
                    $result['landing']['reversers'] = [(int) $m[1], (int) $m[2]];
                }
            }

            // ── Approach ──────────────────────────────────────────────────
            // EN: "APPROACH GATE (…): STABILIZED"
            // ES: "COMPUERTA DE APROXIMACIÓN (…): ESTABILIZADA"
            if (preg_match('/(?:APPROACH GATE|COMPUERTA DE APROXIMACI[OÓ]N)[^:]*:\s*(?:STABILIZED|ESTABILIZADA)/iu', $log)) {
                $result['approach']['stabilized'] = true;
            } elseif (preg_match('/(?:APPROACH GATE|COMPUERTA DE APROXIMACI[OÓ]N)[^:]*:\s*(?:UNSTABILIZED|INESTABLE)/iu', $log)) {
                $result['approach']['stabilized'] = false;
            }
            // EN: "APPROACH CAPTURE: RWY 02 | AGL 2998 ft | Dist 9.1 NM"
            // ES: "INICIO CAPTURA … PISTA 14 … AGL 3000 ft … Dist 9.1 NM"
            if (preg_match('/APPROACH CAPTURE:\s*RWY\s+(\S+)\s*\|\s*AGL\s+([\d,]+)\s*ft\s*\|\s*Dist\s+([\d.]+)\s*NM/iu', $log, $m)) {
                $result['approach']['runway'] = $m[1];
                $result['approach']['agl_ft'] = (int) str_replace(',', '', $m[2]);
                $result['approach']['dist_nm'] = (float) $m[3];
            }
            if (preg_match('/INICIO CAPTURA.*PISTA\s+(\S+).*AGL\s+([\d,]+)\s*ft.*Dist\s+([\d.]+)\s*NM/ui', $log, $m)) {
                $result['approach']['runway'] = $m[1];
                $result['approach']['agl_ft'] = (int) str_replace(',', '', $m[2]);
                $result['approach']['dist_nm'] = (float) $m[3];
            }

            // ── OAT / Wind (collect all, assign first=takeoff last=landing) ──
            // EN: "OAT: 18°C | Wind: 1@0°"  ES: "OAT: 18°C | Viento: 1@0°"
            if (preg_match('/OAT:\s*([-\d]+)°C\s*\|\s*(?:Wind|Viento):\s*([0-9@°]+)/iu', $log, $m)) {
                $oatList[] = ['oat_c' => (int) $m[1], 'wind' => $m[2]];
            }

            // ── Aircraft ──────────────────────────────────────────────────
            if (preg_match('/Aircraft type:\s*(\w+)\s*[→>]\s*Vmo\s*(\d+)\s*kts\s*\((.+?)\)/iu', $log, $m)) {
                $result['aircraft'] = ['type' => $m[1], 'vmo_kts' => (int) $m[2], 'category' => trim($m[3])];
            }

            // ── Network ───────────────────────────────────────────────────
            // EN: "Connected on IVAO (VID 299959)"  ES: "Conectado en IVAO (VID 194102)"
            if (preg_match('/(?:Connected on|Conectado en)\s+(\w+)\s*\(VID\s+([\w\d]+)\)/iu', $log, $m)) {
                $result['network'] = ['name' => $m[1], 'vid' => $m[2]];
            }

            // ── Flight summary ────────────────────────────────────────────
            if (preg_match('/Fuel Used:\s*([\d,.]+)\s*kg/iu', $log, $m)) {
                $result['flight']['fuel_kg'] = (int) str_replace([',', '.'], ['', ''], $m[1]);
            }
            if (preg_match('/Actual Flight Time:\s*(\d+)\s*min/iu', $log, $m)) {
                $result['flight']['actual_time_min'] = (int) $m[1];
            }
            if (preg_match('/Planned Flight Time:\s*(\d+)\s*min/iu', $log, $m)) {
                $result['flight']['planned_time_min'] = (int) $m[1];
            }
            if (preg_match('/Planned Distance:\s*([\d.]+)\s*NM/iu', $log, $m)) {
                $result['flight']['planned_dist_nm'] = (float) $m[1];
            }
        }

        // Assign OAT/Wind: first entry = takeoff, last = landing
        if (count($oatList) >= 1) {
            $result['takeoff']['oat_c'] = $oatList[0]['oat_c'];
            $result['takeoff']['wind'] = $oatList[0]['wind'];
        }
        if (count($oatList) >= 2) {
            $result['landing']['oat_c'] = $oatList[count($oatList) - 1]['oat_c'];
            $result['landing']['wind'] = $oatList[count($oatList) - 1]['wind'];
        }

        return $result;
    }

    /**
     * Bloque operacional: lo que ocurre ANTES y DESPUES del aterrizaje.
     *
     * El analisis clasico solo miraba el toque, pero el cliente ACARS escribe
     * bastante mas y hasta ahora se tiraba: arranque de motores, rodaje a un
     * motor, combustible de rodaje, payload y las duraciones de cada fase.
     *
     * Todas las magnitudes de combustible que salen de aqui vienen del propio
     * log y comparten unidad entre si (el valor cuadra exactamente con
     * `pireps.fuel_used` / `block_fuel`), asi que los RATIOS que se calculen con
     * ellas son validos. El cliente rotula esas lineas como "kg" aunque el
     * numero coincide con el de la BD, que phpVMS interpreta en su unidad
     * interna (lbs): de ahi que aqui no se afirme ninguna unidad absoluta y solo
     * se expongan los valores y sus proporciones.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Acars> $rows
     * @return array<string, mixed>
     */
    private static function parseOperations(Collection $rows): array
    {
        $engines = ['idle_seconds' => []];
        $taxi = [];
        $fuel = [];
        $payload = [];
        $phaseSequence = [];
        $timings = [];

        $beaconOnAt = null;
        $enginesStartedAt = null;
        $parkingBrakeStates = [];

        foreach ($rows as $row) {
            $log = (string) $row->log;
            $at = $row->created_at;

            // ── Payload: "PAX 159  FUEL 12375  TRIP 9030  CARGO 200  FL380" ──
            if (preg_match('/PAX\s+(\d+)\s+FUEL\s+([\d.]+)\s+TRIP\s+([\d.]+)\s+CARGO\s+([\d.]+)\s+FL(\d+)/iu', $log, $m)) {
                $payload = [
                    'pax'               => (int) $m[1],
                    'block_fuel'        => (float) $m[2],
                    'planned_trip_fuel' => (float) $m[3],
                    'cargo'             => (float) $m[4],
                    'flight_level'      => (int) $m[5],
                ];
            }

            // ── Arranque: "Motor: ENG1 idle 1056s [STAB ✓]  ENG2 idle 461s [STAB ✓]  OAT 12°C" ──
            if (preg_match('/^Motor:/iu', $log)) {
                if (preg_match_all('/ENG(\d)\s+idle\s+(\d+)s\s*\[STAB\s*([^\]]*)\]/iu', $log, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $eng) {
                        $engines['idle_seconds']['ENG'.$eng[1]] = (int) $eng[2];
                        $engines['stabilized_'.$eng[1]] = str_contains($eng[3], '✓');
                    }
                }
                if (preg_match('/OAT\s+(-?\d+)°C/iu', $log, $m)) {
                    $engines['oat_c'] = (int) $m[1];
                }
                // "ENG1 pre-arrancado [sin estabilizar]" — arrancado antes de tiempo
                if (preg_match('/ENG\d\s+pre-arrancado/iu', $log)) {
                    $engines['pre_started'] = true;
                }
            }

            if (preg_match('/(?:Motores iniciados|Engines started)/iu', $log)) {
                $engines['started'] = true;
                $enginesStartedAt = $at;
            }
            if (preg_match('/(?:Motores apagados|Engines shutdown)/iu', $log)) {
                $engines['shutdown'] = true;
            }
            if (preg_match('/(?:Motores estabilizados|Engines stabilized)/iu', $log)) {
                $engines['oil_stabilized'] = true;
            }

            // ── Beacon: encendido antes de arrancar es el procedimiento correcto ──
            if (preg_match('/BEACON\s+ENCENDIDO/iu', $log) && $beaconOnAt === null) {
                $beaconOnAt = $at;
            }
            if (preg_match('/BEACON\s+APAGADO/iu', $log)) {
                $engines['beacon_off'] = true;
            }

            // ── Freno de parqueo ──
            if (preg_match('/Freno de parqueo:\s*(PUESTO|LIBERADO)/iu', $log, $m)) {
                $parkingBrakeStates[] = strtoupper($m[1]);
            }

            // ── Rodaje a un motor ──
            if (preg_match('/Motor único detectado en Taxi (Out|In)/iu', $log, $m)) {
                $taxi['single_engine_'.strtolower($m[1])] = true;
            }
            if (preg_match('/Single engine taxi sin bonificación[^—]*—\s*(warm-up|cool-down)/iu', $log, $m)) {
                // Sin guion: la clave que consume el payload es
                // `warmup_insufficient` / `cooldown_insufficient`.
                $taxi[str_replace('-', '', strtolower($m[1])).'_insufficient'] = true;
            }

            // ── Combustible de rodaje ──
            if (preg_match('/Combustible Taxi Out:\s*([\d.]+)/iu', $log, $m)) {
                $fuel['taxi_out'] = (float) $m[1];
            }
            if (preg_match('/Combustible Taxi In:\s*([\d.]+)/iu', $log, $m)) {
                $fuel['taxi_in'] = (float) $m[1];
            }
            if (preg_match('/Combustible Trip \(en vuelo\):\s*([\d.]+)/iu', $log, $m)) {
                $fuel['trip_actual'] = (float) $m[1];
            }
            // "Combustible: Taxi 268 kg · Vuelo 11249 kg · Total 11517 kg"
            if (preg_match('/Combustible:\s*Taxi\s+([\d.]+)\s*\S*\s*·\s*Vuelo\s+([\d.]+)\s*\S*\s*·\s*Total\s+([\d.]+)/u', $log, $m)) {
                $fuel['taxi_total'] = (float) $m[1];
                $fuel['trip_total'] = (float) $m[2];
                $fuel['total'] = (float) $m[3];
            }

            // ── Validacion de combustible plan vs simulador (bloque multilinea) ──
            if (str_contains($log, 'FUEL VALIDATION FAILED')) {
                $check = ['failed' => true];
                if (preg_match('/Planned:\s*([\d.]+)/iu', $log, $m)) {
                    $check['planned'] = (float) $m[1];
                }
                if (preg_match('/Simulator:\s*([\d.]+)/iu', $log, $m)) {
                    $check['simulator'] = (float) $m[1];
                }
                if (preg_match('/Difference:\s*(-?[\d.]+)/iu', $log, $m)) {
                    $check['difference'] = (float) $m[1];
                }
                if (preg_match('/Tolerance:\s*([\d.]+)/iu', $log, $m)) {
                    $check['tolerance'] = (float) $m[1];
                }
                $fuel['validation'] = $check;
            }

            // ── Marcadores de fase, con su hora ──
            // Se guardan como SECUENCIA, no como mapa: un vuelo puede repetir
            // fase (p. ej. un step climb vuelve a marcar CLIMB y ENROUTE a mitad
            // de crucero) y un mapa por nombre perdería la primera marca.
            if (preg_match('/^─+\s*([A-Z][A-Z ]+?)\s*─+$/u', trim($log), $m)) {
                $phaseSequence[] = ['name' => strtolower(trim($m[1])), 'at' => $at];
            }

            // ── Puntualidad y block times ──
            if (preg_match('/Salida a tiempo\s*\(Δ([+-]\d+)\s*min,\s*STD\s*([\d:]+)\s*UTC\)/u', $log, $m)) {
                $timings['on_time_delta_min'] = (int) $m[1];
                $timings['std_utc'] = $m[2];
            }
            if (preg_match('/(?:Block Off|Bloque fuera)\D*(\d{2}:\d{2}:\d{2})/u', $log, $m)) {
                $timings['block_off_utc'] = $m[1];
            }
            if (preg_match('/(?:Block On|Bloque dentro)\D*(\d{2}:\d{2}:\d{2})/u', $log, $m)) {
                $timings['block_on_utc'] = $m[1];
            }
        }

        // Beacon encendido antes de arrancar motores: procedimiento correcto.
        if ($beaconOnAt !== null && $enginesStartedAt !== null) {
            $engines['beacon_before_start'] = $beaconOnAt <= $enginesStartedAt;
        }

        if (!empty($parkingBrakeStates)) {
            $taxi['parking_brake_cycles'] = count($parkingBrakeStates);
            $taxi['parking_brake_final'] = end($parkingBrakeStates);
        }

        // Duraciones entre fases consecutivas, en segundos. La lista va por
        // POSICION, no por nombre: un vuelo con step climb repite "climb" y
        // "enroute", y agrupar por nombre perderia el ascenso inicial.
        $durations = [];
        for ($i = 1; $i < count($phaseSequence); $i++) {
            $from = $phaseSequence[$i - 1];
            $to = $phaseSequence[$i];
            if ($from['at'] === null || $to['at'] === null) {
                continue;
            }
            $durations[] = [
                'from'    => $from['name'],
                'to'      => $to['name'],
                'seconds' => (int) $to['at']->diffInSeconds($from['at']),
            ];
        }

        // Agregados con nombre, que es lo que se comenta al piloto. Se clasifica
        // cada transicion una sola vez, asi que un step climb suma en vez de
        // sobreescribir.
        $totals = ['taxi_out' => 0, 'takeoff_roll' => 0, 'climb' => 0, 'cruise' => 0, 'descent' => 0, 'taxi_in' => 0];
        foreach ($durations as $d) {
            if ($d['from'] === 'pushback' || $d['to'] === 'takeoff roll') {
                $totals['taxi_out'] += $d['seconds'];      // pushback + rodaje de salida
            } elseif ($d['from'] === 'takeoff roll') {
                $totals['takeoff_roll'] += $d['seconds'];
            } elseif ($d['from'] === 'climb') {
                $totals['climb'] += $d['seconds'];         // climb -> enroute
            } elseif ($d['from'] === 'enroute') {
                $totals['cruise'] += $d['seconds'];        // crucero hasta el siguiente cambio
            } elseif ($d['from'] === 'descent') {
                $totals['descent'] += $d['seconds'];
            } elseif ($d['to'] === 'taxi in' || $d['from'] === 'taxi in') {
                $totals['taxi_in'] += $d['seconds'];
            }
        }

        $timings['taxi_out_seconds'] = $totals['taxi_out'];
        $timings['takeoff_roll_seconds'] = $totals['takeoff_roll'];
        $timings['climb_seconds'] = $totals['climb'];
        $timings['cruise_seconds'] = $totals['cruise'];
        $timings['descent_seconds'] = $totals['descent'];
        $timings['taxi_in_seconds'] = $totals['taxi_in'];

        if (!empty($phaseSequence)) {
            $first = $phaseSequence[0]['at'];
            $last = end($phaseSequence)['at'];
            if ($first !== null && $last !== null) {
                $timings['block_seconds'] = (int) $last->diffInSeconds($first);
            }
        }

        // Los ratios son lo que de verdad se comenta, y son independientes de la
        // unidad en que vengan los valores del log.
        if (!empty($payload['planned_trip_fuel']) && !empty($fuel['trip_total'])) {
            $fuel['trip_vs_planned_pct'] = round(
                (($fuel['trip_total'] - $payload['planned_trip_fuel']) / $payload['planned_trip_fuel']) * 100,
                1
            );
        }
        if (!empty($fuel['total']) && !empty($fuel['taxi_total'])) {
            $fuel['taxi_share_pct'] = round(($fuel['taxi_total'] / $fuel['total']) * 100, 1);
        }

        return [
            'engines' => $engines,
            'taxi'    => $taxi,
            'fuel'    => $fuel,
            'payload' => $payload,
            'phases'  => $durations,
            'timings' => $timings,
        ];
    }
}
