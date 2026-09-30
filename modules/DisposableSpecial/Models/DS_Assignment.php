<?php

namespace Modules\DisposableSpecial\Models;

use App\Contracts\Model;
use App\Models\Enums\PirepState;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DS_Assignment extends Model
{
    public $table = 'disposable_assignments';

    protected $fillable = [
        'user_id',
        'assignment_year',
        'assignment_month',
        'assignment_order',
        'flight_id',
        'pirep_id',
        'pirep_date',
    ];

    // Validation rules
    public static $rules = [
        'user_id'          => 'required',
        'assignment_year'  => 'required',
        'assignment_month' => 'required',
        'assignment_order' => 'required',
        'flight_id'        => 'required',
        'pirep_id'         => 'nullable',
        'pirep_date'       => 'nullable',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'pirep_date' => 'datetime',
    ];

    // Relationship to Flight
    public function flight(): HasOne
    {
        return $this->hasOne(Flight::class, 'id', 'flight_id');
    }

    // Relationship to User
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    // Relationship to Pirep
    public function pirep(): HasOne
    {
        return $this->hasOne(Pirep::class, 'id', 'pirep_id');
    }

    public function getCompletedAttribute()
    {
        $pirep = Pirep::where([
            'user_id'   => $this->user_id,
            'flight_id' => $this->flight_id,
        ])->whereMonth('created_at', $this->assignment_month)
          ->whereYear('submitted_at', $this->assignment_year)
          ->where('state', PirepState::ACCEPTED)
          ->first();

        return isset($pirep) ? true : false;
    }


    public function getPirepStatusAttribute()
    {
        // Buscar PIREP sin restricción de mes/año para detectar estado
        $pirep = Pirep::where([
            'user_id'   => $this->user_id,
            'flight_id' => $this->flight_id,
        ])->orderBy('created_at', 'desc')
          ->first();

        if (!$pirep) {
            return 'pending';
        }

        // Si está en progreso o pausado, mostrar "In Progress"
        if ($pirep->state == PirepState::IN_PROGRESS || $pirep->state == PirepState::PAUSED) {
            return 'in_progress';
        }

        // Si está aceptado, verificar que sea del mes/año correcto
        if ($pirep->state == PirepState::ACCEPTED) {
            // Usar submitted_at o created_at según esté disponible
            $pirepDate = $pirep->submitted_at ?? $pirep->created_at;
            if ($pirepDate && $pirepDate->month == $this->assignment_month && $pirepDate->year == $this->assignment_year) {
                return 'completed';
            }
        }

        return 'pending';
    }

}
