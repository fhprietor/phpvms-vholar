<?php

namespace App\Helpers;

use App\Models\Acars;
use App\Models\Pirep;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepStatus;
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
            'takeoff' => $takeoff,
            'landing' => $landing,
            'max_altitude' => $maxAltitude,
            'max_speed' => $maxSpeed,
            'overspeed_detected' => $overspeedDetected,
            'stall_detected' => $stallDetected,
            'crash_detected' => $crashDetected,
            'slew_detected' => $slewDetected,
            'total_points' => $allPoints->count(),
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
            if ($landingRate < -500) $score -= 30;
            elseif ($landingRate < -400) $score -= 20;
            elseif ($landingRate < -300) $score -= 10;
            elseif ($landingRate < -200) $score -= 5;
        }
        
        if ($analysis['overspeed_detected']) $score -= 15;
        if ($analysis['stall_detected']) $score -= 25;
        if ($analysis['crash_detected']) $score -= 50;
        
        return max(0, min(100, $score));
    }

    /**
     * Parse structured data from ACARS log entries (type=LOG, source=vmsOp)
     */
    public static function parseLogData(Pirep $pirep): array
    {
        $logs = Acars::where('pirep_id', $pirep->id)
            ->where('type', AcarsType::LOG)
            ->orderBy('created_at', 'asc')
            ->pluck('log')
            ->toArray();

        if (empty($logs)) {
            return [];
        }

        $result = [
            'score'     => null,
            'penalties' => [],
            'bonuses'   => [],
            'takeoff'   => [],
            'landing'   => [],
            'approach'  => ['stabilized' => null],
            'flight'    => [],
            'aircraft'  => null,
            'network'   => null,
        ];

        $context  = null; // 'takeoff' | 'landing'
        $oatList  = [];   // collect all OAT/Wind pairs; assign first=takeoff, last=landing

        foreach ($logs as $log) {
            // ── Context markers ───────────────────────────────────────────
            if (str_contains($log, 'ACCURATE TAKEOFF DATA')   || str_contains($log, 'DATOS DE DESPEGUE'))    { $context = 'takeoff'; continue; }
            if (str_contains($log, 'ACCURATE TOUCHDOWN DATA') || str_contains($log, 'DATOS DE ATERRIZAJE'))  { $context = 'landing'; continue; }
            if ((str_contains($log, 'Phase changed:') || str_contains($log, 'Gear UP') || str_contains($log, 'Tren de aterrizaje: UP')) && $context === 'takeoff') { $context = null; }
            if ((str_contains($log, 'TaxiIn') || str_contains($log, 'PIREP Status: TXI')) && $context === 'landing') { $context = null; }

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
                $result['takeoff']['alt_ft']    = (int) str_replace(',', '', $m[2]);
                $result['takeoff']['vs_fpm']    = (int) $m[3];
                $context = 'takeoff';
            }
            if (preg_match('/ROTATION at (\d+) kts.*Pitch:\s*([\d.]+)/iu', $log, $m)) {
                $result['takeoff']['rotation_kts'] = (int) $m[1];
                $result['takeoff']['pitch_deg']    = (float) $m[2];
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
                $result['takeoff']['runway']        = $m[1];
                $result['takeoff']['threshold_ft']  = (int) $m[2];
                $result['takeoff']['centerline_ft'] = (int) $m[3];
            }
            // Data inside the ACCURATE TAKEOFF DATA / DATOS DE DESPEGUE block
            if ($context === 'takeoff') {
                if (preg_match('/(?:Rotation Speed|Velocidad de Rotaci[oó]n):\s*(\d+)\s*kts/iu', $log, $m)) $result['takeoff']['rotation_kts'] = (int) $m[1];
                if (preg_match('/(?:Ground Speed|Velocidad en Tierra):\s*(\d+)\s*kts/iu', $log, $m))         $result['takeoff']['gs_kts']        = (int) $m[1];
                if (preg_match('/N1:\s*(\d+)%\s*\/\s*(\d+)%/iu', $log, $m))    $result['takeoff']['n1']        = [(int) $m[1], (int) $m[2]];
                if (preg_match('/^Flaps:\s*(\d+)%$/iu', $log, $m))              $result['takeoff']['flaps_pct'] = (int) $m[1];
                // EN: "Pitch: 6.1° | Bank: -0.4°"  ES: "Cabeceo: 6.9° | Alabeo: 0.2°"
                // Decimal separator may be comma (ES locale) or dot — normalize before cast
                if (preg_match('/(?:Pitch|Cabeceo):\s*([\d.,]+)°\s*\|\s*(?:Bank|Alabeo):\s*(-?[\d.,]+)°/iu', $log, $m)) {
                    $result['takeoff']['pitch_deg'] = (float) str_replace(',', '.', $m[1]);
                    $result['takeoff']['bank_deg']  = (float) str_replace(',', '.', $m[2]);
                }
            }

            // ── Landing ───────────────────────────────────────────────────
            // EN: "Touchdown: -216 FPM, 1.48 G, Pitch: 6.1°, Bank: 0.5°"
            if (preg_match('/Touchdown:\s*(-?\d+)\s*FPM,\s*([\d.,]+)\s*G,\s*Pitch:\s*([\d.,]+)°,\s*Bank:\s*([\d.,]+)°/iu', $log, $m)) {
                $result['landing']['vs_fpm']    = (int) $m[1];
                $result['landing']['gforce']    = (float) str_replace(',', '.', $m[2]);
                $result['landing']['pitch_deg'] = (float) str_replace(',', '.', $m[3]);
                $result['landing']['bank_deg']  = (float) str_replace(',', '.', $m[4]);
            }
            // EN: "Landing recorded: -216 fpm, 1.48 G, Heading: 339°, Pitch: 6.1°, Bank: 0.5°"
            // ES: "Aterrizaje registrado: -103 fpm, 1.10 G, Rumbo: 178°, Cabeceo: 4.8°, Alabeo: -0.6°"
            // Decimal separator may be comma (ES locale) or dot — normalize before cast
            if (preg_match('/(?:Landing recorded|Aterrizaje registrado):\s*(-?\d+)\s*fpm,\s*([\d.,]+)\s*G,\s*(?:Heading|Rumbo):\s*(\d+)°,\s*(?:Pitch|Cabeceo):\s*([\d.,]+)°,\s*(?:Bank|Alabeo):\s*(-?[\d.,]+)°/iu', $log, $m)) {
                $result['landing']['vs_fpm']    = (int) $m[1];
                $result['landing']['gforce']    = (float) str_replace(',', '.', $m[2]);
                $result['landing']['heading']   = (int) $m[3];
                $result['landing']['pitch_deg'] = (float) str_replace(',', '.', $m[4]);
                $result['landing']['bank_deg']  = (float) str_replace(',', '.', $m[5]);
            }
            // ES: "Velocidad: 127 kts (IAS) / 133 kts (GS)" — appears before DATOS DE ATERRIZAJE context block
            if (preg_match('/Velocidad:\s*(\d+)\s*kts\s*\(IAS\)\s*\/\s*(\d+)\s*kts\s*\(GS\)/iu', $log, $m)) {
                $result['landing']['ias_kts'] = (int) $m[1];
                $result['landing']['gs_kts']  = (int) $m[2];
            }
            // "Flaps: 40% | Spoilers: 0%" — landing config (format differs from climb: "Flaps: X% → Y%")
            if (preg_match('/^Flaps:\s*(\d+)%\s*\|\s*Spoilers:\s*(\d+)%$/iu', $log, $m)) {
                $result['landing']['flaps_pct']    = (int) $m[1];
                $result['landing']['spoilers_pct'] = (int) $m[2];
            }
            // Landing runway: "PISTA 35 | TD: 1151 ft … CL: 0 ft"
            if (preg_match('/PISTA\s+(\S+)\s*\|\s*TD:\s*(\d+)\s*ft.*CL:\s*(\d+)\s*ft/ui', $log, $m)) {
                $result['landing']['runway']        = $m[1];
                $result['landing']['td_ft']         = (int) $m[2];
                $result['landing']['centerline_ft'] = (int) $m[3];
            }
            // Data inside ACCURATE TOUCHDOWN DATA / DATOS DE ATERRIZAJE block
            if ($context === 'landing') {
                if (preg_match('/VS:\s*(-?\d+)\s*fpm/iu', $log, $m))                $result['landing']['vs_fpm'] = (int) $m[1];
                // EN: "G-Force: 1.48g (Heavy)"  ES: "Fuerza G: 1.40g (Normal)"
                if (preg_match('/(?:G-Force|Fuerza G):\s*([\d.,]+)g\s*\((.+?)\)/iu', $log, $m)) {
                    $result['landing']['gforce']       = (float) str_replace(',', '.', $m[1]);
                    $result['landing']['gforce_label'] = trim($m[2]);
                }
                if (preg_match('/(?:Speed|Velocidad):\s*(\d+)\s*kts\s*\(IAS\).*?(\d+)\s*kts\s*\(GS\)/iu', $log, $m)) {
                    $result['landing']['ias_kts'] = (int) $m[1];
                    $result['landing']['gs_kts']  = (int) $m[2];
                }
                if (preg_match('/Flaps:\s*(\d+)%\s*\|\s*Spoilers:\s*(\d+)%/iu', $log, $m)) {
                    $result['landing']['flaps_pct']    = (int) $m[1];
                    $result['landing']['spoilers_pct'] = (int) $m[2];
                }
                // EN: "Brakes:…Autobrake: RTO"  ES: "Frenos:…Autobrake: RTO"
                if (preg_match('/(?:Brakes|Frenos).*Autobrake:\s*(\w+)/iu', $log, $m)) $result['landing']['autobrake'] = $m[1];
                // EN: "Reversers: 0% / 0%"  ES: "Reversas: 0% / 0%"
                if (preg_match('/(?:Reversers|Reversas):\s*(\d+)%\s*\/\s*(\d+)%/iu', $log, $m)) $result['landing']['reversers'] = [(int) $m[1], (int) $m[2]];
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
                $result['approach']['runway']  = $m[1];
                $result['approach']['agl_ft']  = (int) str_replace(',', '', $m[2]);
                $result['approach']['dist_nm'] = (float) $m[3];
            }
            if (preg_match('/INICIO CAPTURA.*PISTA\s+(\S+).*AGL\s+([\d,]+)\s*ft.*Dist\s+([\d.]+)\s*NM/ui', $log, $m)) {
                $result['approach']['runway']  = $m[1];
                $result['approach']['agl_ft']  = (int) str_replace(',', '', $m[2]);
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
            if (preg_match('/Fuel Used:\s*([\d,.]+)\s*kg/iu', $log, $m))          $result['flight']['fuel_kg']           = (int) str_replace([',', '.'], ['', ''], $m[1]);
            if (preg_match('/Actual Flight Time:\s*(\d+)\s*min/iu', $log, $m))    $result['flight']['actual_time_min']   = (int) $m[1];
            if (preg_match('/Planned Flight Time:\s*(\d+)\s*min/iu', $log, $m))   $result['flight']['planned_time_min']  = (int) $m[1];
            if (preg_match('/Planned Distance:\s*([\d.]+)\s*NM/iu', $log, $m))    $result['flight']['planned_dist_nm']   = (float) $m[1];
        }

        // Assign OAT/Wind: first entry = takeoff, last = landing
        if (count($oatList) >= 1) {
            $result['takeoff']['oat_c'] = $oatList[0]['oat_c'];
            $result['takeoff']['wind']  = $oatList[0]['wind'];
        }
        if (count($oatList) >= 2) {
            $result['landing']['oat_c'] = $oatList[count($oatList) - 1]['oat_c'];
            $result['landing']['wind']  = $oatList[count($oatList) - 1]['wind'];
        }

        return $result;
    }
}