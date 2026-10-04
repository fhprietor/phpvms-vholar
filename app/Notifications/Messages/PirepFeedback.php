<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Helpers\FlightAnalysisHelper;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Retroalimentacion del analisis automatico, por email al piloto.
 *
 * Es un mensaje propio, no va dentro del email de PIREP presentado: aquel es
 * un acuse de recibo y este es el comentario tecnico del vuelo. Ademas, el
 * piloto puede querer guardarlo o reenviarlo, y mezclado con el acuse se
 * pierde.
 */
class PirepFeedback extends Notification implements ShouldQueue
{
    use MailChannel;

    public function __construct(
        private readonly PirepAiFeedback $feedback,
        private readonly Pirep $pirep
    ) {
        parent::__construct();

        $this->setMailable(
            'Análisis de tu vuelo '.$this->pirep->ident,
            'notifications.mail.pirep.feedback',
            [
                'pirep'    => $this->pirep,
                'feedback' => $this->feedback,
                // Los numeros del aterrizaje, para que el email muestre la
                // misma evidencia concreta que el mensaje de Discord.
                'metrics' => $this->metrics(),
            ]
        );
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * @return array<string, string>
     */
    private function metrics(): array
    {
        $landing = FlightAnalysisHelper::parseLogData($this->pirep)['landing'] ?? [];
        $metrics = [];

        if (isset($landing['vs_fpm'])) {
            $metrics['Tasa de descenso'] = $landing['vs_fpm'].' fpm';
        }

        if (isset($landing['gforce'])) {
            $metrics['Fuerza G'] = number_format((float) $landing['gforce'], 2).' g';
        }

        if (isset($landing['td_ft'])) {
            $metrics['Zona de contacto'] = $landing['td_ft'].' ft desde el umbral';
        }

        if (isset($landing['centerline_ft'])) {
            $metrics['Desvío del eje'] = $landing['centerline_ft'].' ft';
        }

        if (isset($landing['bank_deg'])) {
            $metrics['Alabeo al contacto'] = number_format((float) $landing['bank_deg'], 1).'°';
        }

        return $metrics;
    }

    public function toArray($notifiable)
    {
        return [
            'pirep_id' => $this->pirep->id,
            'severity' => $this->feedback->severity,
        ];
    }
}
