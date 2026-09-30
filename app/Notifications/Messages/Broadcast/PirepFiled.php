<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Helpers\FlightAnalysisHelper;
use App\Models\Pirep;
use App\Notifications\Channels\Discord\DiscordMessage;
use App\Support\Units\Time;
use Illuminate\Contracts\Queue\ShouldQueue;

class PirepFiled extends Notification implements ShouldQueue
{
    private $pirep;

    /**
     * Create a new notification instance.
     */
    public function __construct(Pirep $pirep)
    {
        parent::__construct();

        $this->pirep = $pirep;
    }

    public function via($notifiable)
    {
        return ['discord_webhook'];
    }

    /**
     * Send a Discord notification
     *
     * @param Pirep $pirep
     */
    public function toDiscordChannel($pirep): ?DiscordMessage
    {
        $title = 'Flight '.$pirep->ident.' Filed';
        $fields = $this->createFields($pirep);

        // User avatar, somehow $pirep->user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = !empty($pirep->user->avatar) ? $pirep->user->avatar->url : url('/images/logo.png');

        $description = $pirep->user->discord_id ? 'Flight by <@'.$pirep->user->discord_id.'>' : '';

        $logData = FlightAnalysisHelper::parseLogData($pirep);
        $score   = $logData['score']['value'] ?? null;
        $bonuses = $logData['bonuses'] ?? [];

        if ($score !== null) {
            $scoreLine = 'Score: '.$score;
            foreach ($bonuses as $b) {
                $scoreLine .= ' | +'.$b['points'].' pts: '.$b['reason'];
            }
            $description .= ($description ? PHP_EOL : '').$scoreLine;
        }

        $dm = new DiscordMessage();

        return $dm->webhook(setting('notifications.discord_public_webhook_url'))
            ->success()
            ->title($title)
            ->description($description)
            ->thumbnail(['url' => $user_avatar])
            ->author([
                'name' => $pirep->user->ident.' - '.$pirep->user->name_private,
                'url'  => route('frontend.profile.show', [$pirep->user_id]),
            ])
            ->fields($fields)
            ->footer('Vholar Virtual Airlines', url('/images/vholar_logoweb.png'));
    }

    public function createFields(Pirep $pirep): array
    {
        $fields = [
            'Dep.Airport' => $pirep->dpt_airport_id,
            'Arr.Airport' => $pirep->arr_airport_id,
            'Equipment'   => $pirep->aircraft->ident,
            'Flight Time' => Time::minutesToTimeString($pirep->flight_time),
        ];

        if ($pirep->distance) {
            $fields['Distance'] = $pirep->distance->local(2).' '.setting('units.distance');
        }

        return $fields;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'pirep_id' => $this->pirep->id,
            'user_id'  => $this->pirep->user_id,
        ];
    }
}
