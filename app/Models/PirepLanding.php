<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analisis del aterrizaje (flare) de un PIREP, enviado por el cliente
 * vmsOpenAcars al filear (`POST /api/pireps/{pirep_id}/landing`).
 *
 * Es informativo: nada del ciclo del PIREP lo lee, asi que un fallo al
 * capturarlo nunca puede afectar a un vuelo. Tampoco es la fuente del score:
 * `pireps.score` y `pireps.landing_rate` siguen siendo los canonicos que
 * puntuan; esto es la medicion CRUDA del cliente para el replay del flare y
 * para comparar entre pilotos.
 *
 * Unidades: todas las distancias (`touchdown_dist_ft`, `centerline_dev_ft` y el
 * `dist_ft` de cada muestra de la traza) son PIES CRUDOS. Deliberadamente NO se
 * usa App\Models\Casts\DistanceCast, que convertiria al sistema de unidades
 * interno de phpVMS y dejaria de ser pies.
 *
 * Convencion de nulos: `null` = sin dato (nunca 0). Por eso `trace` se guarda
 * tal cual llega, sin coercionar los booleanos de cada muestra.
 *
 * @property int         id
 * @property string      pirep_id
 * @property int         schema_version
 * @property string|null source_name
 * @property int|null    landing_rate_fpm
 * @property float|null  g_force
 * @property float|null  touchdown_dist_ft
 * @property float|null  centerline_dev_ft
 * @property int|null    score
 * @property string|null runway_name
 * @property float|null  runway_length_ft
 * @property float|null  runway_true_deg
 * @property float|null  offset_threshold_ft  Umbral desplazado: pies del extremo fisico al umbral legal
 * @property float|null  vref_kt              Velocidad de referencia, para poder juzgar la IAS de la toma
 * @property float|null  landing_weight_lbs
 * @property string|null aircraft_icao
 * @property string|null aircraft_title
 * @property string|null aircraft_model
 * @property string|null flaps_label
 * @property float|null  flaps_pct
 * @property int|null    flaps_index
 * @property float|null  power_cut_sec
 * @property bool|null   flare_capture_armed
 * @property string|null trace_stop_reason    Por que se cerro la captura (stopped:cap y stopped:timeout = truncada)
 * @property string|null landing_metar
 * @property float|null  wind_dir_deg
 * @property float|null  wind_speed_kt
 * @property float|null  wind_gust_kt
 * @property float|null  headwind_kt
 * @property float|null  crosswind_kt
 * @property array|null  trace   Muestras del flare a ~8-10 Hz
 */
class PirepLanding extends Model
{
    public $table = 'pirep_landings';

    protected $fillable = [
        'pirep_id',
        'schema_version',
        'source_name',
        'landing_rate_fpm',
        'g_force',
        'touchdown_dist_ft',
        'centerline_dev_ft',
        'score',
        'runway_name',
        'runway_length_ft',
        'runway_true_deg',
        'offset_threshold_ft',
        'vref_kt',
        'landing_weight_lbs',
        'planned_landing_weight_lbs',
        'aircraft_icao',
        'aircraft_title',
        'aircraft_model',
        'flaps_label',
        'flaps_pct',
        'flaps_index',
        'power_cut_sec',
        'flare_capture_armed',
        'trace_stop_reason',
        'landing_metar',
        'wind_dir_deg',
        'wind_speed_kt',
        'wind_gust_kt',
        'headwind_kt',
        'crosswind_kt',
        'trace',
    ];

    protected $casts = [
        'schema_version'      => 'integer',
        'landing_rate_fpm'    => 'integer',
        'g_force'             => 'float',
        'touchdown_dist_ft'   => 'float',
        'centerline_dev_ft'   => 'float',
        'score'               => 'integer',
        'runway_length_ft'    => 'float',
        'runway_true_deg'     => 'float',
        'offset_threshold_ft' => 'float',
        'vref_kt'             => 'float',
        'planned_landing_weight_lbs' => 'float',
        'landing_weight_lbs'  => 'float',
        'flaps_pct'           => 'float',
        'flaps_index'         => 'integer',
        'power_cut_sec'       => 'float',
        'flare_capture_armed' => 'boolean',
        'wind_dir_deg'        => 'float',
        'wind_speed_kt'       => 'float',
        'wind_gust_kt'        => 'float',
        'headwind_kt'         => 'float',
        'crosswind_kt'        => 'float',
        'trace'               => 'array',
    ];

    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    /**
     * Hay traza que dibujar (el replay del flare en el navegador la necesita).
     */
    public function hasTrace(): bool
    {
        return !empty($this->trace);
    }
}
