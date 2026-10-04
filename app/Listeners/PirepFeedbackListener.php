<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\PirepFiled;
use App\Jobs\GeneratePirepFeedback;
use Illuminate\Support\Facades\Log;

/**
 * Encola el analisis automatico de un PIREP recien presentado.
 *
 * Deliberadamente NO analiza aqui. Este listener corre dentro del request HTTP
 * del cliente ACARS (App\Services\PirepService::submit, invocado desde
 * Api\PirepController), asi que todo lo que haga se le suma al piloto que esta
 * esperando la respuesta. Lo unico que hace es comparar una setting y encolar:
 * milisegundos.
 *
 * Si el worker de cola no esta corriendo, el job se queda en la tabla `jobs`
 * hasta que el cron lo recoja (cron:queue corre cada minuto).
 */
class PirepFeedbackListener extends Listener
{
    public function handle(PirepFiled $event): void
    {
        if (!setting('general.deepseek_feedback_enabled', false)) {
            return;
        }

        try {
            GeneratePirepFeedback::dispatch($event->pirep);
        } catch (\Throwable $e) {
            // Nunca debe romper la presentacion de un PIREP: la
            // retroalimentacion es un extra, no parte del ciclo de vida.
            Log::error('PirepFeedbackListener: no se pudo encolar el analisis', [
                'pirep_id' => $event->pirep->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
