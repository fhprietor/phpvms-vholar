<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Bid;
use App\Models\Enums\PirepState;
use App\Models\Enums\UserState;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        try {
            // Usuarios para "Latest Members" (los últimos 7)
            $latestPilots = User::with('home_airport')
                ->where('state', '!=', UserState::DELETED)
                ->orderBy('created_at', 'desc')
                ->take(7)
                ->get();

            // También mantengo los primeros 4 que ya tenías por si se usan en otro lado
            $users = $latestPilots->take(4);

            // Estadísticas del mes
            $totalFlights = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->count();

            $totalDistance = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->sum('distance');

            $totalHours = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->sum('flight_time');

            $totalPilots = User::where('state', UserState::ACTIVE)->count();

            // Bids activos (vuelos despachados)
            $activeBids = Bid::with(['user', 'flight', 'aircraft'])
                ->get();

            // Top 5 scoring últimos 7 días
            $topScoring = Pirep::with('user')
                ->whereNotNull('score')
                ->where('score', '>', 0)
                ->where('submitted_at', '>=', Carbon::now()->subDays(7))
                ->where('state', PirepState::ACCEPTED)
                ->orderBy('score', 'desc')
                ->limit(5)
                ->get();

            // Bottom 5 scoring últimos 7 días
            $bottomScoring = Pirep::with('user')
                ->whereNotNull('score')
                ->where('score', '>', 0)
                ->where('submitted_at', '>=', Carbon::now()->subDays(7))
                ->where('state', PirepState::ACCEPTED)
                ->orderBy('score', 'asc')
                ->limit(5)
                ->get();
            
                        // Totales de todos los tiempos (para el número grande)
            $totalFlightsAllTime = Pirep::where('state', PirepState::ACCEPTED)->count();
            $totalDistanceAllTime = Pirep::where('state', PirepState::ACCEPTED)->sum('distance');
            $totalHoursAllTime = Pirep::where('state', PirepState::ACCEPTED)->sum('flight_time');
            $totalPilotsAllTime = User::where('state', UserState::ACTIVE)->count();

            // Totales de este mes (para el texto pequeño)
            $totalFlightsThisMonth = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->count();
            $totalDistanceThisMonth = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->sum('distance');
            $totalHoursThisMonth = Pirep::where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('state', PirepState::ACCEPTED)
                ->sum('flight_time');
            $totalPilotsActive = Pirep::where('state', PirepState::ACCEPTED)
                ->where('submitted_at', '>=', Carbon::now()->startOfMonth())
                ->distinct()
                ->count('user_id');

        } catch (\PDOException $e) {
            Log::emergency($e);

            return view('system/errors/database_error', [
                'error' => $e->getMessage(),
            ]);
        } catch (QueryException $e) {
            return view('system/errors/not_installed');
        }

        // No users
        if (!$users) {
            return view('system/errors/not_installed');
        }

        return view('home')->with([
            'users' => $users,
            'latestPilots' => $latestPilots,
            'totalFlightsAllTime' => $totalFlightsAllTime,
            'totalDistanceAllTime' => $totalDistanceAllTime,
            'totalHoursAllTime' => $totalHoursAllTime,
            'totalPilotsAllTime' => $totalPilotsAllTime,
            'totalFlightsThisMonth' => $totalFlightsThisMonth,
            'totalDistanceThisMonth' => $totalDistanceThisMonth,
            'totalHoursThisMonth' => $totalHoursThisMonth,
            'totalPilotsActive' => $totalPilotsActive,
            'activeBids' => $activeBids,
            'topScoring' => $topScoring,
            'bottomScoring' => $bottomScoring,
        ]);

    }
}