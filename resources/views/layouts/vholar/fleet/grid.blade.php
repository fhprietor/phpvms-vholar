@extends('app')
@section('title', 'Fleet by Subfleet - VHolar')

@once
@include('vholar::pireps.logbook-styles')
@endonce

@section('content')
<div class="container-fluid px-0">

    {{-- Page header --}}
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:var(--vh-text-muted);">
            <i class="bi bi-grid-3x3-gap-fill me-2"></i>@lang('common.fleet')
        </h4>
        <div style="font-size:0.72rem;color:var(--vh-silver-dim);">
            {{ $subfleets->sum(fn($sf) => $sf->aircraft->count()) }} @lang('common.aircraft') &middot; {{ $subfleets->count() }} @lang('common.subfleet')
        </div>
    </div>

    <div class="fleet-grid">
        @forelse($subfleets as $subfleet)
            <div class="card vholar-logbook-wrap">
                {{-- Card header --}}
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <span style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:1rem;">
                            {{ $subfleet->name }}
                        </span>
                        <a href="{{ route('DBasic.subfleet', $subfleet->type) }}"
                           style="display:block;font-size:0.82rem;color:rgba(255,255,255,0.6);letter-spacing:0.04em;margin-top:2px;text-decoration:none;">
                            {{ $subfleet->type }}
                        </a>
                    </div>
                    <span class="badge bg-white text-primary">{{ $subfleet->aircraft->count() }}</span>
                </div>

                {{-- Aircraft table --}}
                <div class="table-responsive">
                    <table class="table vholar-logbook">
                        <thead>
                            <tr>
                                <th>@lang('common.registration')</th>
                                <th>@lang('common.location')</th>
                                <th>@lang('common.fuel')</th>
                                <th>@lang('common.last_landing')</th>
                                <th class="text-center">@lang('common.status')</th>
                                <th class="text-center">@lang('common.state')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subfleet->aircraft as $ac)
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="{{ route('DBasic.aircraft', $ac->registration) }}" class="lb-fltnum" style="font-size:0.9rem;">
                                            {{ $ac->registration }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        @if($ac->airport_id)
                                            <a href="{{ route('frontend.airports.show', $ac->airport_id) }}"
                                               style="font-family:'Courier New',monospace;font-size:0.88rem;font-weight:700;color:var(--vh-text-muted);text-decoration:none;letter-spacing:0.02em;">
                                                {{ $ac->airport_id }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="lb-time">
                                            {{ function_exists('DB_ConvertWeight') ? DB_ConvertWeight($ac->fuel_onboard, $units['fuel']) : number_format($ac->fuel_onboard) . ' ' . $units['fuel'] }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span style="font-size:0.78rem;color:var(--vh-text-muted);">
                                            {{ $ac->landing_time ? \Carbon\Carbon::parse($ac->landing_time)->diffForHumans() : __('common.never') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $statusClass = match($ac->status) {
                                                'A' => 'bg-success',
                                                'M' => 'bg-warning text-dark',
                                                'S' => 'bg-secondary',
                                                'R' => 'bg-dark',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge lb-state {{ $statusClass }}">
                                            {{ \App\Models\Enums\AircraftStatus::label($ac->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $stateClass = match($ac->state) {
                                                0 => 'bg-success',
                                                1 => 'bg-warning text-dark',
                                                2 => 'bg-info text-dark',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge lb-state {{ $stateClass }}">
                                            {{ \App\Models\Enums\AircraftState::label($ac->state) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info text-center">@lang('flights.none')</div>
            </div>
        @endforelse
    </div>
</div>

<style>
.fleet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
    gap: 1.25rem;
}
@media (max-width: 768px) {
    .fleet-grid { grid-template-columns: 1fr; gap: 1rem; }
    .fleet-grid .vholar-logbook { min-width: 480px; }
}
</style>
@endsection
