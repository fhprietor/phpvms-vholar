@php use App\Models\Enums\PirepState; @endphp

@once
@include('vholar::pireps.logbook-styles')
<style>
/* Expandable row */
.lb-toggle-icon {
  display: inline-block;
  font-size: 0.65rem;
  color: #7878a0;
  margin-right: 5px;
  transition: transform 0.18s ease;
  vertical-align: middle;
}
.pirep-toggle[aria-expanded="true"] .lb-toggle-icon { transform: rotate(90deg); }
/* Pilot cell */
.lb-pilot {
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}
.lb-pilot img {
  width: 28px; height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.lb-pilot-name {
  font-size: 0.78rem;
  color: #a0a8c0;
  text-decoration: none;
}
.lb-pilot-name:hover { color: #90aaff; text-decoration: none; }
/* Landing rate coloring */
.lrate-good    { color: #4caf76; }
.lrate-ok      { color: #e6a817; }
.lrate-hard    { color: #e05060; }
/* Secondary collapsed row */
.lb-detail-row td { padding: 0 !important; border: none !important; }
.lb-detail-inner {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding: 8px 20px;
  border-top: 1px solid rgba(255,255,255,0.05);
  background: rgba(0,0,0,0.35);
  font-size: 0.75rem;
  color: #9898b0;
}
.lb-detail-inner a { color: #8898cc; text-decoration: none; }
.lb-detail-inner a:hover { color: #90aaff; }
</style>
@endonce

<div class="vholar-logbook-wrap">
  <div class="table-responsive">
    <table class="table vholar-logbook">
      <thead>
        <tr>
          <th>@sortablelink('submitted_at', __('common.date'))</th>
          <th>@sortablelink('flight_number', __('DBasic::common.flightno'))</th>
          <th>@lang('common.route')</th>
          <th class="text-center">@sortablelink('flight_time', __('DBasic::common.btime'))</th>
          <th>@sortablelink('user.name', __('DBasic::common.pilot'))</th>
          <th class="text-center">@sortablelink('score', __('DBasic::common.score'))</th>
          <th class="text-center">@lang('common.status')</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($pireps as $pirep)
          @php
            $bt  = $pirep->block_time ?: $pirep->flight_time;
            $btH = $bt ? intdiv($bt, 60) : 0;
            $btM = $bt ? ($bt % 60) : 0;

            $stateColor = 'bg-secondary';
            $stateLabel = PirepState::label($pirep->state);
            if ($pirep->state === PirepState::ACCEPTED)     { $stateColor = 'bg-success'; }
            elseif ($pirep->state === PirepState::PENDING)  { $stateColor = 'bg-warning text-dark'; }
            elseif ($pirep->state === PirepState::REJECTED) { $stateColor = 'bg-danger'; }

            $scoreClass = '';
            if ($pirep->score !== null) {
                $scoreClass = $pirep->score >= 80 ? 'lb-score-good' : ($pirep->score >= 60 ? 'lb-score-ok' : 'lb-score-bad');
            }
          @endphp

          {{-- Primary row --}}
          <tr class="pirep-toggle"
              role="button"
              data-bs-toggle="collapse"
              data-bs-target="#detail-{{ $pirep->id }}"
              aria-expanded="false">

            {{-- Date --}}
            <td class="lb-date">
              @if($pirep->submitted_at)
                <span class="lb-day">{{ $pirep->submitted_at->format('d') }}</span>
                <span class="lb-monyear">{{ $pirep->submitted_at->format('M Y') }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Flight number --}}
            <td>
              <span class="lb-toggle-icon">&#9654;</span>
              @ability('admin', 'admin-access')
                <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}"
                   class="me-1"
                   style="color:#5a6a9a;font-size:0.75rem;"
                   title="@lang('common.view')"
                   onclick="event.stopPropagation()">&#9432;</a>
              @endability
              <a href="{{ route('frontend.pireps.show', [$pirep->id]) }}"
                 class="lb-fltnum"
                 onclick="event.stopPropagation()">{{ $pirep->ident }}</a>
              @if($pirep->airline)
                <span class="lb-airline">{{ $pirep->airline->name }}</span>
              @endif
            </td>

            {{-- Route --}}
            <td>
              <div class="lb-route">
                <a href="{{ route('frontend.airports.show', [$pirep->dpt_airport_id]) }}"
                   class="lb-icao" style="color:#dce4ff;text-decoration:none;"
                   onclick="event.stopPropagation()">{{ $pirep->dpt_airport_id }}</a>
                <span class="lb-route-arrow">✈</span>
                <a href="{{ route('frontend.airports.show', [$pirep->arr_airport_id]) }}"
                   class="lb-icao" style="color:#dce4ff;text-decoration:none;"
                   onclick="event.stopPropagation()">{{ $pirep->arr_airport_id }}</a>
              </div>
              <div class="lb-cities">
                <small>{{ optional($pirep->dpt_airport)->name }}</small>
                <small>{{ optional($pirep->arr_airport)->name }}</small>
              </div>
            </td>

            {{-- Block time --}}
            <td class="text-center lb-blocktime">
              @if($bt)
                <span class="lb-blocktime-icon">✈</span>
                <span class="lb-blocktime-val">{{ $btH }}:{{ str_pad($btM, 2, '0', STR_PAD_LEFT) }}h</span>
                @ability('admin', 'admin-access')
                  @if($pirep->flight_time && $pirep->planned_flight_time && ($pirep->flight_time - $pirep->planned_flight_time) > 20)
                    <i class="bi bi-clock text-danger ms-1" title="Revisar tiempo de vuelo" style="font-size:0.7rem;"></i>
                  @endif
                @endability
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Pilot --}}
            <td>
              <div class="lb-pilot">
                @if(optional($pirep->user)->avatar)
                  <img src="{{ $pirep->user->avatar->url }}" alt="">
                @else
                  <img src="{{ public_asset('images/logo.png') }}"
                       style="object-fit:contain;background:#1f1c27;padding:2px;" alt="">
                @endif
                <a href="{{ route('frontend.users.show.public', [$pirep->user_id]) }}"
                   class="lb-pilot-name"
                   onclick="event.stopPropagation()">
                  @if(Theme::getSetting('roster_ident')){{ optional($pirep->user)->ident.' – ' }}@endif
                  {{ optional($pirep->user)->name_private }}
                </a>
              </div>
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
              @ability('admin', 'admin-access')
                @if($pirep->comments_count > 0 || filled($pirep->notes))
                  <i class="bi bi-chat-text text-secondary ms-1" title="Tiene comentarios / notas" style="font-size:0.7rem;"></i>
                @endif
              @endability
            </td>
          </tr>

          {{-- Secondary collapsed row --}}
          <tr class="lb-detail-row">
            <td colspan="7">
              <div class="collapse" id="detail-{{ $pirep->id }}">
                <div class="lb-detail-inner">
                  @if(!isset($ac_page))
                    <span>
                      <span style="color:#7878a0;margin-right:3px;">&#9992;</span>
                      @if(optional($pirep->aircraft)->registration)
                        <a href="{{ route('DBasic.aircraft', [$pirep->aircraft->registration]) }}"
                           onclick="event.stopPropagation()">{{ optional($pirep->aircraft)->ident }}</a>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </span>
                  @endif

                  <span>
                    <span style="color:#7878a0;margin-right:3px;">&#9981;</span>
                    {{ DB_ConvertWeight($pirep->fuel_used, $units['fuel']) }}
                    @ability('admin', 'admin-access')
                      @if(filled($pirep->simbrief) && ($pirep->fuel_used->local() - ($pirep->simbrief->xml->fuel->enroute_burn + ($pirep->simbrief->xml->fuel->contingency * 1.15) + ($pirep->simbrief->xml->fuel->taxi * 2)) > 100))
                        <i class="bi bi-exclamation-triangle text-danger ms-1" title="Revisar combustible usado"></i>
                      @endif
                    @endability
                  </span>

                  @ability('admin', 'admin-access')
                    @if($pirep->landing_rate)
                      @php
                        $lrClass = $pirep->landing_rate < -300 ? 'lrate-hard' : ($pirep->landing_rate < -200 ? 'lrate-ok' : 'lrate-good');
                      @endphp
                      <span class="{{ $lrClass }}">
                        {{ $pirep->landing_rate }}<span style="color:#7878a0;margin-left:2px;">ft/m</span>
                      </span>
                    @endif
                  @endability

                  @if(DB_Setting('dbasic.networkcheck', false))
                    <span>{!! DB_NetworkPresence($pirep, 'badge') !!}</span>
                  @endif

                  @if(Theme::getSetting('gen_stable_approach'))
                    @ability('admin', 'admin-access')
                      <span>@widget('DBasic::StableApproach', ['pirep' => $pirep])</span>
                    @endability
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@once
  @push('scripts')
  <script>
    document.querySelectorAll('.pirep-toggle').forEach(function(row) {
      row.addEventListener('click', function() {
        const icon = this.querySelector('.lb-toggle-icon');
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !isExpanded);
        if (icon) icon.style.transform = isExpanded ? '' : 'rotate(90deg)';
      });
    });
  </script>
  @endpush
@endonce
