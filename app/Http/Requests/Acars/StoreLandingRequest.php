<?php

namespace App\Http\Requests\Acars;

use App\Models\Pirep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacion del analisis del aterrizaje (flare) que envia vmsOpenAcars al
 * filear el PIREP.
 *
 * Dos criterios mandan aqui:
 *
 *  1. `nullable` en TODO, y sin valores por defecto. En el contrato del cliente
 *     `null` es "sin dato" y un 0 se leeria como una medida falsa. Esto incluye
 *     los booleanos de cada muestra (`spoilers`, `on_ground`): un nulo NO es
 *     `false`, asi que se declaran `nullable|boolean` y nunca `boolean` con
 *     default.
 *  2. Los rangos fisicos son GENEROSOS a proposito. La validacion es
 *     todo-o-nada (un 400 descarta el payload entero), asi que un limite
 *     estrecho que rechace un aterrizaje duro legitimo cuesta mas caro que
 *     guardar un valor raro. La proteccion contra abuso esta en el tope de
 *     muestras y en la longitud de los textos, no en los rangos.
 *
 * La PROPIEDAD del PIREP se comprueba en el controlador, no en authorize():
 * devolver false aqui lanza AuthorizationException, que el Handler de la API
 * mapea a 503 y no a 403. Aqui solo se resuelve el PIREP para que un id
 * inexistente de 404 antes de validar el cuerpo (misma pauta que
 * AtcCommsRequest).
 */
class StoreLandingRequest extends FormRequest
{
    /**
     * Tope de muestras por traza. La traza real esta en 60-350 (un flare corto a
     * 10 Hz); 2000 deja sitio a un aterrizaje que se alargue sin abrir la puerta
     * a un payload abusivo.
     */
    public const MAX_TRACE_SAMPLES = 2000;

    public function authorize(): bool
    {
        Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return true;
    }

    public function rules(): array
    {
        return [
            'schema_version' => 'required|integer|between:1,100',
            'source_name'    => 'sometimes|nullable|string|max:64',

            // `present`, no `required`: `required` rechaza un array vacio, y un
            // payload sin metricas pero con traza es recuperable (se guardan las
            // muestras y las metricas quedan null). Preferimos eso a un 400 que
            // descarte el aterrizaje entero. La clave si tiene que venir.
            'metrics' => 'present|nullable|array',
            'trace'   => 'sometimes|nullable|array|max:'.self::MAX_TRACE_SAMPLES,

            // ---- Metricas del aterrizaje ----
            'metrics.landing_rate_fpm'  => 'sometimes|nullable|integer|between:-10000,10000',
            'metrics.g_force'           => 'sometimes|nullable|numeric|between:0,20',
            'metrics.touchdown_dist_ft' => 'sometimes|nullable|numeric|between:-20000,50000',
            'metrics.centerline_dev_ft' => 'sometimes|nullable|numeric|between:-2000,2000',
            'metrics.score'             => 'sometimes|nullable|integer|between:0,100',
            'metrics.runway_name'       => 'sometimes|nullable|string|max:16',
            'metrics.runway_length_ft'  => 'sometimes|nullable|numeric|min:0|max:30000',
            'metrics.runway_true_deg'   => 'sometimes|nullable|numeric|between:0,360',
            // Los dos campos que pedimos al cliente. Opcionales: los vuelos ya
            // subidos no los traen.
            'metrics.offset_threshold_ft' => 'sometimes|nullable|numeric|between:0,20000',
            'metrics.vref_kt'             => 'sometimes|nullable|numeric|between:0,600',
            'metrics.landing_weight_lbs'  => 'sometimes|nullable|numeric|min:0|max:2000000',
            // Peso de PLAN (el del OFP, al que pertenece la Vref). Distinto del medido.
            'metrics.planned_landing_weight_lbs' => 'sometimes|nullable|numeric|min:0|max:2000000',
            'metrics.aircraft_icao'       => 'sometimes|nullable|string|max:8',
            'metrics.aircraft_title'      => 'sometimes|nullable|string|max:191',
            'metrics.aircraft_model'      => 'sometimes|nullable|string|max:64',
            'metrics.flaps_label'         => 'sometimes|nullable|string|max:32',
            'metrics.flaps_pct'           => 'sometimes|nullable|numeric|between:0,100',
            'metrics.flaps_index'         => 'sometimes|nullable|integer|between:0,40',
            'metrics.power_cut_sec'       => 'sometimes|nullable|numeric|between:0,600',
            'metrics.flare_capture_armed' => 'sometimes|nullable|boolean',
            // Motivo del cierre de la captura. NO se limita a una lista cerrada:
            // si el cliente anade un motivo nuevo, se guarda igual.
            'metrics.trace_stop_reason' => 'sometimes|nullable|string|max:32',
            // 500, no 400: los METAR con bloque RMK (EEUU) pasan de 400 sin ser
            // anormales, y el cliente recorta al tope que declaremos. Como la
            // validacion es todo-o-nada, un METAR largo costaria la traza entera
            // del vuelo. La columna es TEXT, asi que 500 no cuesta nada.
            'metrics.landing_metar' => 'sometimes|nullable|string|max:500',
            'metrics.wind_dir_deg'  => 'sometimes|nullable|numeric|between:0,360',
            'metrics.wind_speed_kt' => 'sometimes|nullable|numeric|between:0,300',
            'metrics.wind_gust_kt'  => 'sometimes|nullable|numeric|between:0,300',
            'metrics.headwind_kt'   => 'sometimes|nullable|numeric|between:-300,300',
            'metrics.crosswind_kt'  => 'sometimes|nullable|numeric|between:-300,300',

            // ---- Muestras de la traza (un elemento por muestra) ----
            'trace.*.ts_utc'       => 'required|date',
            'trace.*.dist_ft'      => 'sometimes|nullable|numeric|between:-20000,50000',
            'trace.*.agl_ft'       => 'sometimes|nullable|numeric|between:-2000,60000',
            'trace.*.radar_alt_ft' => 'sometimes|nullable|numeric|between:-2000,60000',
            'trace.*.ias_kt'       => 'sometimes|nullable|numeric|between:0,600',
            'trace.*.vs_fpm'       => 'sometimes|nullable|numeric|between:-20000,20000',
            'trace.*.pitch_deg'    => 'sometimes|nullable|numeric|between:-45,45',
            'trace.*.bank_deg'     => 'sometimes|nullable|numeric|between:-90,90',
            'trace.*.gs_kt'        => 'sometimes|nullable|numeric|between:0,600',
            'trace.*.n1_1_pct'     => 'sometimes|nullable|numeric|between:0,120',
            'trace.*.n1_2_pct'     => 'sometimes|nullable|numeric|between:0,120',
            'trace.*.n2_1_pct'     => 'sometimes|nullable|numeric|between:0,120',
            'trace.*.n2_2_pct'     => 'sometimes|nullable|numeric|between:0,120',
            'trace.*.flaps_pct'    => 'sometimes|nullable|numeric|between:0,100',
            'trace.*.flaps_index'  => 'sometimes|nullable|integer|between:0,40',
            'trace.*.spoilers'     => 'sometimes|nullable|boolean',
            'trace.*.on_ground'    => 'sometimes|nullable|boolean',
        ];
    }
}
