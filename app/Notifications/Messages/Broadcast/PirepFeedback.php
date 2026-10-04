<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Helpers\FlightAnalysisHelper;
use App\Models\Pirep;
use App\Models\PirepAiFeedback;
use App\Notifications\Channels\Discord\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Aviso del analisis automatico del vuelo, en Discord.
 *
 * Va en un mensaje SEPARADO del de "Pirep Filed" a proposito: aquel es el
 * radario del vuelo (ruta, equipo, tiempo) y este lleva los datos concretos
 * del aterrizaje que el analisis si conoce (zona de contacto, G, banco, eje) y
 * la accion para el proximo vuelo. Mezclarlos convertiria el aviso de vuelo en
 * un muro de texto y, sobre todo, un fallo del analisis retrasaria el aviso
 * normal.
 */
class PirepFeedback extends Notification implements ShouldQueue
{
    /**
     * Limite de Discord para el cuerpo del embed.
     */
    private const DESCRIPTION_LIMIT = 4096;

    public function __construct(
        private readonly PirepAiFeedback $feedback,
        private readonly Pirep $pirep
    ) {
        parent::__construct();
    }

    public function via($notifiable)
    {
        return ['discord_webhook'];
    }

    public function toDiscordChannel($notifiable): ?DiscordMessage
    {
        $user_avatar = !empty($this->pirep->user->avatar)
            ? $this->pirep->user->avatar->url
            : url('/images/logo.png');

        $message = new DiscordMessage();

        $message->webhook(setting('notifications.discord_public_webhook_url'))
            ->title('Análisis de vuelo · '.$this->pirep->ident)
            ->url(route('frontend.pirep.show.public', [$this->pirep->id]))
            ->description($this->buildDescription())
            ->author([
                'name' => $this->pirep->user->ident.' - '.$this->pirep->user->name_private,
                'url'  => route('frontend.profile.show', [$this->pirep->user_id]),
            ])
            ->thumbnail(['url' => $user_avatar])
            ->fields($this->metricFields())
            ->footer('Vholar Virtual Airlines', url('/images/vholar_logoweb.png'));

        // El color resume la gravedad de un vistazo en el canal.
        match ($this->feedback->severity) {
            PirepAiFeedback::SEVERITY_OK         => $message->success(),
            PirepAiFeedback::SEVERITY_IMPROVABLE => $message->warning(),
            default                              => $message->error(),
        };

        return $message;
    }

    /**
     * Cuerpo del mensaje: veredicto, lo bueno, lo corregible y la accion.
     */
    private function buildDescription(): string
    {
        $lines = [];

        if (!empty($this->feedback->verdict)) {
            $lines[] = '**'.$this->feedback->verdict.'**';
        }

        if (!empty($this->feedback->good_points)) {
            $lines[] = '';
            $lines[] = '✅ **Puntos fuertes**';
            foreach ($this->feedback->good_points as $point) {
                $lines[] = '• '.$point;
            }
        }

        if (!empty($this->feedback->errors)) {
            $lines[] = '';
            $lines[] = '⚠️ **A revisar**';
            foreach ($this->feedback->errors as $error) {
                $lines[] = '• '.$error;
            }
        }

        if (!empty($this->feedback->areas)) {
            $lines[] = '';
            $lines[] = '📋 **Valoración por área**';
            foreach ($this->feedback->areas as $area) {
                $rating = (int) ($area['valoracion'] ?? 1);
                $icon = $rating === 0 ? '🟢' : ($rating === 1 ? '🟠' : '🔴');
                $lines[] = $icon.' **'.ucfirst((string) ($area['area'] ?? '')).'** — '.($area['nota'] ?? '');
            }
        }

        if (!empty($this->feedback->action)) {
            $lines[] = '';
            $lines[] = '🎯 **Próximo vuelo**';
            $lines[] = $this->feedback->action;
        }

        return mb_substr(implode(PHP_EOL, $lines), 0, self::DESCRIPTION_LIMIT);
    }

    /**
     * Los numeros del aterrizaje, que es lo que este mensaje aporta sobre el
     * aviso de PIREP. Se omiten si el log no los trae.
     */
    private function metricFields(): array
    {
        $landing = FlightAnalysisHelper::parseLogData($this->pirep)['landing'] ?? [];
        $fields = [];

        if (isset($landing['vs_fpm'])) {
            $fields['Tasa'] = $landing['vs_fpm'].' fpm';
        }

        if (isset($landing['gforce'])) {
            $fields['Fuerza G'] = number_format((float) $landing['gforce'], 2).' g';
        }

        if (isset($landing['td_ft'])) {
            $fields['Zona TD'] = $landing['td_ft'].' ft';
        }

        if (isset($landing['centerline_ft'])) {
            $fields['Eje'] = $landing['centerline_ft'].' ft';
        }

        if (isset($landing['bank_deg'])) {
            $fields['Alabeo'] = number_format((float) $landing['bank_deg'], 1).'°';
        }

        return $fields;
    }

    public function toArray($notifiable)
    {
        return [
            'pirep_id' => $this->pirep->id,
            'severity' => $this->feedback->severity,
        ];
    }
}
