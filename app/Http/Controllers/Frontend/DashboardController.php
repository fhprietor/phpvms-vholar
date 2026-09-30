<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Aircraft;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Repositories\PirepRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Class DashboardController
 */
class DashboardController extends Controller
{
    /**
     * DashboardController constructor.
     */
    public function __construct(
        private readonly PirepRepository $pirepRepo
    ) {}

    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        $last_pirep = null;
        // Support retrieval of deleted relationships
        $with_pirep = [
            'aircraft' => function ($query) {
                return $query->withTrashed();
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'comments',
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
        ];

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->loadMissing('journal');

        try {
            $last_pirep = $this->pirepRepo->with($with_pirep)->find($user->last_pirep_id);
        } catch (\Exception $e) {
        }

        $recent_pireps = Pirep::with(['dpt_airport', 'arr_airport', 'aircraft', 'airline'])
            ->where('user_id', $user->id)
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
            ->orderBy('submitted_at', 'desc')
            ->take(5)
            ->get();

        // Get the current airport for the weather
        $current_airport = $user->curr_airport_id ?? $user->home_airport_id;

        $local_aircraft = Aircraft::with('subfleet')
            ->where('airport_id', $current_airport)
            ->orderBy('registration')
            ->get();

        return view('dashboard.index', [
            'user'            => $user,
            'current_airport' => $current_airport,
            'last_pirep'      => $last_pirep,
            'recent_pireps'   => $recent_pireps,
            'local_aircraft'  => $local_aircraft,
        ]);
    }
}
