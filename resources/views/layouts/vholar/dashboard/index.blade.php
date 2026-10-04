@extends('app')
@section('title', __('common.dashboard'))

@php
    use App\Models\Enums\PirepState;
    use App\Models\Enums\AircraftState;
    use App\Models\Enums\AircraftStatus;
@endphp

@section('content')
    <div class="row">
        <div class="col">
            @if (Auth::user()->state === \App\Models\Enums\UserState::ON_LEAVE)
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning" role="alert">
                            You are on leave! File a PIREP to set your status to active!
                        </div>
                    </div>
                </div>
            @endif

            {{-- TOP BAR WITH BOXES --}}
            <div class="row mb-4">
                <div class="col-6 col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center d-flex flex-center flex-column m-auto">
                            <h3 class="header">{{ $user->flights }}</h3>
                            <h5 class="description">{{ trans_choice('common.flight', $user->flights) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">@minutestotime($user->flight_time)</h3>
                            <h5 class="description">@lang('dashboard.totalhours')</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">US$ {{ number_format(intval(optional($user->journal)->balance?->getValue() ?? 0)) }}</h3>
                            <h5 class="description">@lang('dashboard.yourbalance')</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-primary text-white dashboard-box">
                        <div class="card-body text-center">
                            <h3 class="header">{{ $current_airport }}</h3>
                            <h5 class="description">@lang('airports.current')</h5>
                        </div>
                    </div>
                </div>
            </div>

            {{-- INFORME DEL INSTRUCTOR (IA) DEL ULTIMO VUELO ANALIZADO --}}
            @php
                // El ultimo vuelo del piloto que tiene analisis. Se ordena por la fecha del
                // VUELO, no por la del analisis: el comando de retroanalisis genera analisis
                // de vuelos antiguos y desordenaria cual es "el ultimo".
                $aiFeedback = \App\Models\PirepAiFeedback::query()
                    ->join('pireps', 'pireps.id', '=', 'pirep_ai_feedback.pirep_id')
                    ->where('pireps.user_id', $user->id)
                    ->orderByDesc('pireps.submitted_at')
                    ->select('pirep_ai_feedback.*')
                    ->with('pirep')
                    ->first();
            @endphp

            @if($aiFeedback && $aiFeedback->pirep)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1"
                     style="font-size: 0.75rem; color: var(--vh-text-muted);">
                    <span style="font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;">
                        <i class="bi bi-robot"></i> Último vuelo analizado
                    </span>
                    <a href="{{ route('frontend.pireps.show', [$aiFeedback->pirep_id]) }}">
                        {{ $aiFeedback->pirep->ident }}
                        · {{ $aiFeedback->pirep->dpt_airport_id }} → {{ $aiFeedback->pirep->arr_airport_id }}
                        · {{ optional($aiFeedback->pirep->submitted_at)->format('d M Y H:i') }}
                    </a>
                </div>
            @endif

            @include('components.pirep-ai-feedback', ['aiFeedback' => $aiFeedback])

            {{-- MI ECONOMIA: ingresos y costes de los vuelos del propio piloto --}}
            @php $myEco = app(\App\Services\ProfitabilityService::class)->series($user->id); @endphp
            <div class="card vholar-card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <h5 class="mb-0"><i class="bi bi-wallet2"></i> Mi economía</h5>
                    <span class="ms-auto" style="font-size:0.72rem;color:var(--vh-text-muted);">
                        ingresos por tarifas menos costes de mis vuelos
                    </span>
                </div>
                <div class="card-body">
                    <div class="row text-center g-2 mb-3">
                        <div class="col-4">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:var(--vh-text-muted);">Año {{ $myEco['year'] }}</div>
                            <div class="fs-5 fw-bold {{ $myEco['totals']['year']['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($myEco['totals']['year']['profit']) }} USD
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:var(--vh-text-muted);">Mes {{ $myEco['month'] }}</div>
                            <div class="fs-5 fw-bold {{ $myEco['totals']['month']['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($myEco['totals']['month']['profit']) }} USD
                            </div>
                        </div>
                        <div class="col-4">
                            <div style="font-size:0.7rem;text-transform:uppercase;color:var(--vh-text-muted);">Ingresos/Costes del año</div>
                            <div style="font-size:0.85rem;">
                                {{ number_format($myEco['totals']['year']['income']) }} / {{ number_format($myEco['totals']['year']['cost']) }}
                            </div>
                        </div>
                    </div>
                    <canvas id="chartMyMonthly" height="90"></canvas>
                </div>
            </div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script type="text/javascript">
              document.addEventListener('DOMContentLoaded', function () {
                var rows = @json($myEco['monthly']);
                var el = document.getElementById('chartMyMonthly');
                if (!el || !rows.length || typeof Chart === 'undefined') return;
                new Chart(el, {
                  type: 'bar',
                  data: {
                    labels: rows.map(function (r) { return r.label.slice(5); }),
                    datasets: [{label: 'Utilidad', data: rows.map(function (r) { return r.profit; }), backgroundColor: 'rgba(74,144,217,0.9)'}]
                  },
                  options: {responsive: true, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function (c) { return c.parsed.y.toLocaleString() + ' USD'; }}}}}
                });
              });
            </script>
            @endpush

            {{-- MIS ÚLTIMOS 5 VUELOS --}}
            @once
            @include('vholar::pireps.logbook-styles')
            @endonce
            <div class="card vholar-logbook-wrap mb-3">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <span style="font-weight:800; letter-spacing:0.04em; text-transform:uppercase; font-size:0.8rem;">
                            ✈ &nbsp;@lang('dashboard.yourlastreport')
                        </span>
                    </div>
                    <a href="{{ route('frontend.pireps.index') }}" class="btn btn-sm btn-outline-light"
                       style="font-size:0.7rem; letter-spacing:0.06em; text-transform:uppercase; font-weight:700;">
                        @lang('dashboard.viewall')
                    </a>
                </div>
                @if ($recent_pireps->isEmpty())
                    <div class="card-body text-center text-muted">
                        @lang('dashboard.noreportsyet') <a href="{{ route('frontend.pireps.create') }}">@lang('dashboard.fileonenow')</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table vholar-logbook">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Flight</th>
                                    <th>Route</th>
                                    <th>Aircraft</th>
                                    <th class="text-center">DEP</th>
                                    <th class="text-center">ARR</th>
                                    <th class="text-center">Block Time</th>
                                    <th class="text-center">Score</th>
                                    <th class="text-center">State</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent_pireps as $pirep)
                                    @php
                                        $bt = $pirep->block_time ?: $pirep->flight_time;
                                        $btH = $bt ? intdiv($bt, 60) : 0;
                                        $btM = $bt ? ($bt % 60) : 0;

                                        $stateColor = 'bg-info';
                                        $stateLabel = PirepState::label($pirep->state);
                                        if ($pirep->state === PirepState::PENDING)       { $stateColor = 'bg-warning text-dark'; }
                                        elseif ($pirep->state === PirepState::ACCEPTED)  { $stateColor = 'bg-success'; }
                                        elseif ($pirep->state === PirepState::REJECTED)  { $stateColor = 'bg-danger'; }

                                        $scoreClass = '';
                                        if ($pirep->score !== null) {
                                            $scoreClass = $pirep->score >= 80 ? 'lb-score-good' : ($pirep->score >= 60 ? 'lb-score-ok' : 'lb-score-bad');
                                        }
                                    @endphp
                                    <tr>
                                        {{-- Date --}}
                                        <td class="lb-date">
                                            @if($pirep->submitted_at)
                                                <span class="lb-day">{{ $pirep->submitted_at->format('d') }}</span>
                                                <span class="lb-monyear">{{ $pirep->submitted_at->format('M Y') }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Flight --}}
                                        <td>
                                            <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}" class="lb-fltnum">
                                                {{ $pirep->ident }}
                                            </a>
                                            @if($pirep->airline)
                                                <span class="lb-airline">{{ $pirep->airline->name }}</span>
                                            @endif
                                        </td>

                                        {{-- Route --}}
                                        <td>
                                            <div class="lb-route">
                                                <span class="lb-icao">{{ $pirep->dpt_airport_id }}</span>
                                                <span class="lb-route-arrow">✈</span>
                                                <span class="lb-icao">{{ $pirep->arr_airport_id }}</span>
                                            </div>
                                            <div class="lb-cities">
                                                <small>{{ optional($pirep->dpt_airport)->name }}</small>
                                                <small>{{ optional($pirep->arr_airport)->name }}</small>
                                            </div>
                                        </td>

                                        {{-- Aircraft --}}
                                        <td>
                                            @if($pirep->aircraft)
                                                @if($pirep->aircraft->icao)
                                                    <span class="lb-ac-type">{{ $pirep->aircraft->icao }}</span>
                                                @endif
                                                <span class="lb-ac-reg">{{ $pirep->aircraft->registration }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Dep time --}}
                                        <td class="text-center">
                                            @if($pirep->block_off_time)
                                                <span class="lb-time">{{ $pirep->block_off_time->format('H:i') }}</span>
                                            @else
                                                <span class="text-muted" style="font-size:.8rem">—</span>
                                            @endif
                                        </td>

                                        {{-- Arr time --}}
                                        <td class="text-center">
                                            @if($pirep->block_on_time)
                                                <span class="lb-time">{{ $pirep->block_on_time->format('H:i') }}</span>
                                            @else
                                                <span class="text-muted" style="font-size:.8rem">—</span>
                                            @endif
                                        </td>

                                        {{-- Block time --}}
                                        <td class="text-center lb-blocktime">
                                            @if($bt)
                                                <span class="lb-blocktime-icon">✈</span>
                                                <span class="lb-blocktime-val">{{ $btH }}:{{ str_pad($btM, 2, '0', STR_PAD_LEFT) }}h</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Score --}}
                                        <td class="text-center">
                                            @if($pirep->score !== null)
                                                <span class="lb-score {{ $scoreClass }}">{{ $pirep->score }}</span>
                                            @else
                                                <span class="text-muted" style="font-size:.8rem">—</span>
                                            @endif
                                        </td>

                                        {{-- State --}}
                                        <td class="text-center">
                                            <span class="badge lb-state {{ $stateColor }}">{{ $stateLabel }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- AERONAVES EN AEROPUERTO ACTUAL --}}
            <div class="card vholar-logbook-wrap mb-3">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.8rem;">
                        <i class="bi bi-airplane-engines me-1"></i>@lang('dashboard.aircraftat', ['airport' => $current_airport])
                    </span>
                    <span class="badge bg-white text-primary">{{ $local_aircraft->count() }}</span>
                </div>
                @if ($local_aircraft->isEmpty())
                    <div class="card-body text-center text-muted small">
                        @lang('dashboard.noaircraftat', ['airport' => $current_airport])
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table vholar-logbook">
                            <thead>
                                <tr>
                                    <th>@lang('common.registration')</th>
                                    <th>@lang('common.type')</th>
                                    <th class="text-end">@lang('common.fuel')</th>
                                    <th class="text-center">@lang('common.status')</th>
                                    <th class="text-center">@lang('common.state')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($local_aircraft as $ac)
                                    @php
                                        $statusClass = match($ac->status) {
                                            AircraftStatus::ACTIVE      => 'bg-success',
                                            AircraftStatus::MAINTENANCE => 'bg-warning text-dark',
                                            AircraftStatus::STORED      => 'bg-secondary',
                                            default                     => 'bg-danger',
                                        };
                                        $stateClass = match($ac->state) {
                                            AircraftState::PARKED => 'bg-success',
                                            AircraftState::IN_USE => 'bg-warning text-dark',
                                            AircraftState::IN_AIR => 'bg-info text-dark',
                                            default               => 'bg-secondary',
                                        };
                                        $fuelUnit = config('phpvms.internal_units.fuel', 'lbs');
                                        $fuelVal  = $ac->fuel_onboard
                                            ? number_format((int) $ac->fuel_onboard->toUnit($fuelUnit))
                                            : '—';
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap">
                                            <a href="{{ url('/daircraft/'.$ac->registration) }}" class="lb-fltnum" style="font-size:0.9rem;">{{ $ac->registration }}</a>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="lb-ac-type">{{ optional($ac->subfleet)->type ?? '—' }}</span>
                                            @if(optional($ac->subfleet)->name)
                                                <span class="lb-airline">{{ $ac->subfleet->name }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <span class="lb-time">{{ $fuelVal }}</span>
                                            <span class="text-muted" style="font-size:0.72rem;">{{ $fuelUnit }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge lb-state {{ $statusClass }}">
                                                {{ __($ac->status === AircraftStatus::ACTIVE ? 'aircraft.status.active' : ($ac->status === AircraftStatus::MAINTENANCE ? 'aircraft.status.maintenance' : ($ac->status === AircraftStatus::STORED ? 'aircraft.status.stored' : 'aircraft.status.retired'))) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge lb-state {{ $stateClass }}">
                                                {{ AircraftState::label($ac->state) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ Widget::latestNews(['count' => 5]) }}
        </div>

        {{-- Sidebar --}}
        <div class="col-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    @lang('dashboard.weatherat', ['ICAO' => $current_airport])
                </div>
                <div class="card-body p-2">
                    {{ Widget::Weather(['icao' => $current_airport]) }}
                </div>
            </div>

            {{-- Recent Reports (aerolinea) --}}
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    @lang('dashboard.recentreports')
                </div>
                <div class="card-body p-0">
                    {{ Widget::latestPireps(['count' => 5]) }}
                </div>
            </div>

            <div class="card vholar-logbook-wrap mt-4">
                <div class="card-header bg-primary text-white">
                    <span style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.8rem;">
                        <i class="bi bi-person-lines-fill me-1"></i>@lang('common.newestpilots')
                    </span>
                </div>
                {{ Widget::latestPilots(['count' => 5]) }}
            </div>
        </div>
    </div>
@endsection
