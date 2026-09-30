@once
@include('vholar::pireps.logbook-styles')
@endonce

<div class="card vholar-logbook-wrap">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table vholar-logbook">
        <thead>
          <tr>
            <th>@sortablelink('submitted_at', __('common.date'))</th>
            <th>@sortablelink('flight_number', trans_choice('common.flight', 1))</th>
            <th>@sortablelink('dpt_airport_id', __('flights.route'))</th>
            <th>@sortablelink('aircraft_id', __('common.aircraft'))</th>
            <th class="text-center">@lang('flights.dep')</th>
            <th class="text-center">@lang('flights.arr')</th>
            <th class="text-center">@sortablelink('flight_time', __('pireps.flighttime'))</th>
            <th class="text-center">@sortablelink('score', __('common.score'))</th>
            <th class="text-center">@sortablelink('status', __('common.state'))</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        @foreach($pireps as $pirep)
          @php
            $bt = $pirep->block_time ?: $pirep->flight_time;
            $btH = $bt ? intdiv($bt, 60) : 0;
            $btM = $bt ? ($bt % 60) : 0;

            $stateColor = 'bg-info';
            $stateLabel = PirepState::label($pirep->state);
            if ($pirep->state === PirepState::PENDING)  { $stateColor = 'bg-warning text-dark'; }
            elseif ($pirep->state === PirepState::ACCEPTED) { $stateColor = 'bg-success'; }
            elseif ($pirep->state === PirepState::REJECTED) { $stateColor = 'bg-danger'; }

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

            {{-- Flight number --}}
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

            {{-- Actions --}}
            <td>
              @if(!$pirep->read_only)
                <a href="{{ route('frontend.pireps.edit', [$pirep->id]) }}"
                   class="btn btn-outline-secondary lb-edit-btn"
                   title="@lang('common.edit')">@lang('common.edit')</a>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>

        {{-- Page totals row --}}
        @php
          $pageMinutes = $pireps->sum(fn($p) => $p->block_time ?: $p->flight_time);
          $pageTH = intdiv($pageMinutes, 60);
          $pageTM = $pageMinutes % 60;
        @endphp
        @if($pageMinutes > 0)
        <tfoot>
          <tr class="lb-totals-row">
            <td colspan="6" class="text-end">
              Page total
            </td>
            <td class="text-center">
              <span class="lb-total-val">{{ $pageTH }}:{{ str_pad($pageTM, 2, '0', STR_PAD_LEFT) }}h</span>
            </td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>

    <div class="px-3 py-3">
      {{ $pireps->withQueryString()->links('pagination.bootstrap-5') }}
    </div>
  </div>
</div>
