<?php

namespace App\Jobs;

use App\Exceptions\PirepFeedbackException;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Notifications\Messages\Broadcast\PirepFeedback as DiscordFeedback;
use App\Notifications\Messages\PirepFeedback as MailFeedback;
use App\Services\PirepFeedbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Genera la retroalimentacion automatica de un PIREP y la reparte por Discord
 * y email.
 *
 * Corre en cola a proposito. `PirepService::submit()` se invoca dentro del
 * request HTTP del cliente ACARS (Api\PirepController), y el analisis tarda
 * 12-25 s: hacerlo en linea anadiria esa espera a cada entrega de PIREP y
 * convertiria una caida de DeepSeek en un fallo al reportar un vuelo.
 *
 * El aviso del analisis va en un mensaje aparte del de "PIREP filed": lleva
 * datos concretos (zona de contacto, G, banco, accion) que no caben ni encajan
 * en el mensaje de radario del vuelo, y ademas asi un fallo del analisis nunca
 * retrasa el aviso normal.
 */
class GeneratePirepFeedback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * El servicio ya reintenta una vez contra la API, asi que un segundo
     * intento del job solo duplicaria el gasto: si falla, se registra y se
     * deja pasar.
     */
    public $tries = 1;

    /**
     * Debe superar el timeout HTTP del servicio (180 s), o el worker mataria
     * el job con la peticion todavia en vuelo.
     */
    public $timeout = 300;

    /**
     * `$afterCommit` NO se redeclara como propiedad: Illuminate\Bus\Queueable
     * ya la define y redeclararla con otro valor es un fatal error de PHP
     * ("define the same property... definition differs"). Se activa con el
     * metodo que expone el trait.
     *
     * El PIREP ya existia antes de despachar, pero su estado final se escribe
     * despues del evento: si hay una transaccion abierta, esperamos a que
     * confirme para no leer una fila a medias.
     */
    public function __construct(
        public readonly Pirep $pirep
    ) {
        $this->afterCommit();
    }

    public function handle(PirepFeedbackService $service): void
    {
        // La funcion pudo apagarse desde Admin mientras el job esperaba turno.
        if (!$service->isEnabled()) {
            return;
        }

        if (!$service->isConfigured()) {
            Log::warning('GeneratePirepFeedback: sin clave de DeepSeek, se omite el analisis', [
                'pirep_id' => $this->pirep->id,
            ]);

            return;
        }

        // La mayoria de PIREPs no traen telemetria: sin ella no hay nada que
        // analizar y no se notifica nada.
        if ($service->buildPayload($this->pirep) === null) {
            return;
        }

        try {
            $feedback = $service->analyseAndStore($this->pirep);
        } catch (PirepFeedbackException $e) {
            Log::warning('GeneratePirepFeedback: analisis fallido', [
                'pirep_id' => $this->pirep->id,
                'error'    => $e->getMessage(),
            ]);

            return;
        }

        $this->notify($feedback);
    }

    /**
     * Reparte el analisis. Cada canal se aisla por separado: que Discord no
     * responda no debe impedir el email, ni al reves.
     *
     * Se registra una linea con el resultado de cada canal. Sin ella solo
     * quedaba rastro de los fallos, y no habia forma de auditar si un analisis
     * llego a salir por Discord o por email.
     */
    private function notify(PirepAiFeedback $feedback): void
    {
        $discord = 'omitido';
        $mail = 'omitido';

        if (setting('notifications.discord_pirep_feedback', true)) {
            try {
                // sendNow: ya estamos en un job en cola, no hace falta otro salto.
                Notification::sendNow([$this->pirep], new DiscordFeedback($feedback, $this->pirep));
                $discord = 'enviado';
            } catch (Throwable $e) {
                $discord = 'fallo';
                Log::error('GeneratePirepFeedback: fallo el aviso de Discord', [
                    'pirep_id' => $this->pirep->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        if ($this->pirep->user === null) {
            $mail = 'sin destinatario';
        } elseif (setting('notifications.mail_pirep_feedback', true)) {
            try {
                $this->pirep->user->notifyNow(new MailFeedback($feedback, $this->pirep));
                $mail = 'enviado';
            } catch (Throwable $e) {
                $mail = 'fallo';
                Log::error('GeneratePirepFeedback: fallo el email de retroalimentacion', [
                    'pirep_id' => $this->pirep->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        Log::info('GeneratePirepFeedback: analisis generado', [
            'pirep_id' => $this->pirep->id,
            'severity' => $feedback->severity,
            'tokens'   => $feedback->total_tokens,
            'discord'  => $discord,
            'mail'     => $mail,
        ]);
    }

    /**
     * Nunca debe propagar: el PIREP ya esta registrado y la retroalimentacion
     * es un extra.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GeneratePirepFeedback: job fallido', [
            'pirep_id' => $this->pirep->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}
