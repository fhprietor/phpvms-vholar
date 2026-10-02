@once
@include('vholar::pireps.logbook-styles')
<style>
.lb-pilot-ident {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  /* Era #7a6a9a sobre badge violeta: 3.06:1, bajo AA para 10px. */
  color: var(--vh-silver);
  background: var(--vh-primary-soft);
  border-radius: 4px;
  padding: 2px 6px;
  display: inline-block;
  margin-right: 4px;
  vertical-align: middle;
}
.lb-pilot-link {
  font-size: 0.92rem;
  font-weight: 700;
  text-decoration: none;
  color: var(--vh-text) !important;
  letter-spacing: 0.02em;
}
.lb-pilot-link:hover { color: var(--vh-silver) !important; }
.lb-rank {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  background: var(--vh-primary-soft);
  color: var(--vh-text-muted);
  border-radius: 4px;
  padding: 2px 7px;
  vertical-align: middle;
}
.lb-location {
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--vh-text-muted);
  letter-spacing: 0.02em;
}
.lb-location-name {
  display: block;
  font-size: 0.62rem;
  color: var(--vh-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-top: 1px;
}
.lb-stat-val {
  font-size: 1rem;
  font-weight: 800;
  color: var(--vh-text);
  font-variant-numeric: tabular-nums;
}
.lb-stat-label {
  display: block;
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--vh-text-muted);
  margin-top: 1px;
}
</style>
@endonce

<div class="vholar-logbook-wrap">
  <div class="table-responsive">
    <table class="table vholar-logbook">
      <thead>
        <tr>
          <th>@sortablelink('id', trans_choice('common.pilot', 1))</th>
          <th>@sortablelink('airline_id', __('common.airline'))</th>
          <th>@sortablelink('curr_airport_id', __('user.location'))</th>
          <th>@sortablelink('rank_id', __('common.rank'))</th>
          <th class="text-center">@sortablelink('flights', trans_choice('common.flight', 2))</th>
          <th class="text-center">@sortablelink('flight_time', trans_choice('common.hour', 2))</th>
          <th class="text-center">@lang('common.score')</th>
          <th class="text-center">@lang('common.last_landing')</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $user)
          @php
            $ftH = intdiv($user->flight_time, 60);
            $ftM = $user->flight_time % 60;
          @endphp
          <tr>
            {{-- Pilot --}}
            <td>
              <div class="lb-pilot" style="align-items:center;">
                @if($user->avatar)
                  <img src="{{ $user->avatar->url }}" alt=""
                       style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                @else
                  <img src="{{ public_asset('images/logo.png') }}" alt=""
                       style="width:36px;height:36px;border-radius:50%;object-fit:contain;background:var(--vh-surface);padding:3px;flex-shrink:0;">
                @endif
                <div>
                  <div>
                    <span class="lb-pilot-ident">{{ $user->ident }}</span>
                    <a href="{{ route('frontend.users.show.public', [$user->id]) }}" class="lb-pilot-link">
                      {{ $user->name_private }}
                    </a>
                    @if(filled($user->country))
                      <span class="fi fi-{{ $user->country }}" style="font-size:0.85em;margin-left:4px;"
                            title="{{ $country->alpha2($user->country)['name'] ?? $user->country }}"></span>
                    @endif
                  </div>
                </div>
              </div>
            </td>

            {{-- Airline --}}
            <td>
              @if($user->airline)
                <span class="lb-ac-type">{{ $user->airline->icao }}</span>
                <span class="lb-ac-reg">{{ $user->airline->name }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Location --}}
            <td>
              @if($user->curr_airport_id)
                <span class="lb-location">{{ $user->curr_airport_id }}</span>
                @if($user->current_airport)
                  <span class="lb-location-name">{{ $user->current_airport->name }}</span>
                @endif
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Rank --}}
            <td>
              @if($user->rank)
                <span class="lb-rank">{{ $user->rank->name }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Flights --}}
            <td class="text-center">
              <span class="lb-stat-val">{{ $user->flights }}</span>
              <span class="lb-stat-label">{{ trans_choice('common.flight', $user->flights) }}</span>
            </td>

            {{-- Hours --}}
            <td class="text-center lb-blocktime">
              @if($user->flight_time)
                <span class="lb-blocktime-icon">✈</span>
                <span class="lb-blocktime-val">{{ $ftH }}:{{ str_pad($ftM, 2, '0', STR_PAD_LEFT) }}h</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Avg Score --}}
            <td class="text-center">
              @if($user->pireps_avg_score !== null)
                @php $avg = round($user->pireps_avg_score); @endphp
                <span class="lb-score {{ $avg >= 80 ? 'lb-score-good' : ($avg >= 60 ? 'lb-score-ok' : 'lb-score-bad') }}">
                  {{ $avg }}
                </span>
              @else
                <span class="text-muted" style="font-size:.8rem">—</span>
              @endif
            </td>

            {{-- Last Flight --}}
            <td class="text-center">
              @if($user->last_pirep && $user->last_pirep->submitted_at)
                <span class="lb-time">{{ $user->last_pirep->submitted_at->diffForHumans() }}</span>
              @else
                <span class="text-muted" style="font-size:.8rem">—</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
