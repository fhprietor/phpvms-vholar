<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Http\Resources\SimBrief as SimbriefResource;
use App\Models\Enums\PirepStatus;
use App\Support\Units\Distance;
use App\Support\Units\Fuel;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \App\Models\Pirep
 */
class Pirep extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['ident'] = $this->ident;
        $res['phase'] = $this->status;
        $res['status_text'] = PirepStatus::label($this->status);

        // La nota libre del piloto y las observaciones internas del staff solo se
        // entregan a su autor o a un administrador.
        if (!$this->canViewPrivate()) {
            unset($res['notes']);
        }

        // Set these to the response units
        if (!array_key_exists('distance', $res)) {
            $res['distance'] = 0;
        }

        $distance = Distance::make($res['distance'], config('phpvms.internal_units.distance'));
        $res['distance'] = $distance->getResponseUnits();

        if (!array_key_exists('block_fuel', $res)) {
            $res['block_fuel'] = 0;
        }

        $block_fuel = Fuel::make($res['block_fuel'], config('phpvms.internal_units.fuel'));
        $res['block_fuel'] = $block_fuel->getResponseUnits();

        if (!array_key_exists('fuel_used', $res)) {
            $res['fuel_used'] = 0;
        }

        $fuel_used = Fuel::make($res['fuel_used'], config('phpvms.internal_units.fuel'));
        $res['fuel_used'] = $fuel_used->getResponseUnits();

        if (!array_key_exists('planned_distance', $res)) {
            $res['planned_distance'] = 0;
        }

        $planned_dist = Distance::make($res['planned_distance'], config('phpvms.internal_units.distance'));
        $res['planned_distance'] = $planned_dist->getResponseUnits();

        /*
         * Relationship fields
         */

        if ($this->block_on_time) {
            $res['block_on_time'] = $this->block_on_time->toIso8601ZuluString();
        }

        if ($this->block_off_time) {
            $res['block_off_time'] = $this->block_off_time->toIso8601ZuluString();
        }

        $res['aircraft'] = new Aircraft($this->aircraft);
        $res['airline'] = new Airline($this->airline);
        $res['dpt_airport'] = new Airport($this->dpt_airport);
        $res['arr_airport'] = new Airport($this->arr_airport);

        $res['position'] = Acars::make($this->whenLoaded('position'));
        $res['comments'] = $this->canViewPrivate()
            ? PirepComment::collection($this->whenLoaded('comments'))
            : [];
        $res['user'] = User::make($this->whenLoaded('user'));

        $res['flight'] = Flight::make($this->whenLoaded('flight'));

        // format to kvp
        $res['fields'] = new PirepFieldCollection($this->fields);

        // Simbrief info
        $res['simbrief'] = new SimbriefResource($this->whenLoaded('simbrief'));

        // Revision previa de ruta: si el OFP cruza un area restringida/prohibida, el
        // cliente lo recibe al prefilar y puede corregir el plan antes de despegar.
        $res['route_warning'] = $this->routeWarning();

        return $res;
    }

    /**
     * Aviso de espacio aereo en la ruta, si el prefile lo dejo en los campos.
     *
     * @return array<string, mixed>|null
     */
    private function routeWarning(): ?array
    {
        $fields = collect($this->fields ?? []);
        $area = $fields->firstWhere('name', 'route_airspace')?->value;

        if (empty($area)) {
            return null;
        }

        return [
            'area'   => $area,
            'limits' => $fields->firstWhere('name', 'route_limits')?->value,
            'leg'    => $fields->firstWhere('name', 'route_leg')?->value,
            'action' => 'revisar_ruta',
        ];
    }

    /**
     * El autor del PIREP (o un administrador con permiso de PIREPs) puede ver
     * las notas privadas y las observaciones del staff.
     */
    private function canViewPrivate(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return $this->user_id === $user->id || $user->ability('admin', 'pireps');
    }
}
