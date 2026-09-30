@php use App\Models\Enums\PirepState; @endphp

@once
@include('vholar::pireps.logbook-styles')
<style>
.lb-widget-list { list-style: none; margin: 0; padding: 0; }
.lb-widget-entry {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.045);
  transition: background 0.12s;
}
.lb-widget-entry:last-child { border-bottom: none; }
.lb-widget-entry:hover { background: rgba(255,255,255,0.03); }
.lb-widget-avatar img {
  width: 34px; height: 34px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.lb-widget-body { flex: 1; min-width: 0; }
.lb-widget-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
}
.lb-widget-fltnum {
  font-size: 0.88rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-decoration: none;
  color: #c0c8e8 !important;
  white-space: nowrap;
}
.lb-widget-fltnum:hover { color: #90aaff !important; }
.lb-widget-btime {
  font-size: 0.82rem;
  font-weight: 700;
  color: #c8d8ff;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.lb-widget-btime-icon { color: #7080b8; font-size: 0.65rem; margin-right: 2px; }
.lb-widget-route {
  font-family: 'Courier New', monospace;
  font-size: 0.82rem;
  font-weight: 700;
  color: #8898c8;
  letter-spacing: 0.02em;
  margin-top: 1px;
}
.lb-widget-route-arrow { color: #3a3a52; margin: 0 3px; font-size: 0.68rem; }
.lb-widget-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 3px;
}
.lb-widget-pilot {
  font-size: 0.62rem;
  color: #9090a8;
  letter-spacing: 0.03em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 110px;
}
.lb-widget-state {
  font-size: 0.58rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 2px 7px;
  border-radius: 20px;
  flex-shrink: 0;
}
</style>
@endonce

<ul class="lb-widget-list">
    @forelse ($pireps as $p)
        @php
            $bt  = $p->block_time ?: $p->flight_time;
            $btH = $bt ? intdiv($bt, 60) : 0;
            $btM = $bt ? ($bt % 60) : 0;

            $stateColor = 'bg-secondary';
            if ($p->state === PirepState::ACCEPTED)     { $stateColor = 'bg-success'; }
            elseif ($p->state === PirepState::PENDING)  { $stateColor = 'bg-warning text-dark'; }
            elseif ($p->state === PirepState::REJECTED) { $stateColor = 'bg-danger'; }
        @endphp
        <li class="lb-widget-entry">
            <div class="lb-widget-avatar">
                @if(optional($p->user)->avatar)
                    <img src="{{ $p->user->avatar->url }}" alt="">
                @else
                    <img src="{{ public_asset('images/logo.png') }}"
                         style="object-fit:contain;background:#1f1c27;padding:3px;" alt="">
                @endif
            </div>
            <div class="lb-widget-body">
                <div class="lb-widget-top">
                    <a href="{{ route('frontend.pireps.show', [$p->id]) }}" class="lb-widget-fltnum">
                        {{ $p->ident }}
                    </a>
                    @if($bt)
                        <span class="lb-widget-btime">
                            <span class="lb-widget-btime-icon">✈</span>{{ $btH }}:{{ str_pad($btM, 2, '0', STR_PAD_LEFT) }}h
                        </span>
                    @endif
                </div>
                <div class="lb-widget-route">
                    {{ $p->dpt_airport_id }}
                    <span class="lb-widget-route-arrow">✈</span>
                    {{ $p->arr_airport_id }}
                </div>
                <div class="lb-widget-bottom">
                    <span class="lb-widget-pilot">{{ optional($p->user)->ident }}</span>
                    <div style="display:flex;align-items:center;gap:5px;flex-shrink:0;">
                        @if($p->score !== null)
                            @php $sc = $p->score >= 80 ? 'lb-score-good' : ($p->score >= 60 ? 'lb-score-ok' : 'lb-score-bad'); @endphp
                            <span class="lb-score {{ $sc }}" style="font-size:0.72rem;">{{ $p->score }}</span>
                        @endif
                        <span class="badge lb-widget-state {{ $stateColor }}">{{ PirepState::label($p->state) }}</span>
                    </div>
                </div>
            </div>
        </li>
    @empty
        <li class="text-center text-muted py-3" style="font-size:0.8rem;">@lang('dashboard.noreportsyet')</li>
    @endforelse
</ul>
