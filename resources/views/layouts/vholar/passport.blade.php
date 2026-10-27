@extends('app')

@section('title', __('vholar_passport.title'))

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-passport"></i> @lang('vholar_passport.title')</h4>
    <div style="font-size: 0.75rem; color: var(--vh-text-muted);">@lang('vholar_passport.subtitle')</div>
  </div>

  {{-- Totales --}}
  <div class="row g-3 mb-3">
    <div class="col-4 col-md-3">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ $passport['visited'] }}/{{ $passport['total'] }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.stamps')</div>
      </div></div>
    </div>
    <div class="col-4 col-md-3">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ $passport['countries'] }}/{{ $passport['countries_total'] }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.countries')</div>
      </div></div>
    </div>
    <div class="col-4 col-md-3">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ count($passport['tours']) }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.tours')</div>
      </div></div>
    </div>
  </div>

  <style>
    .vh-stamp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 0.9rem; }
    .vh-stamp {
      position: relative; border: 2px dashed var(--vh-border, #3a3344); border-radius: 10px;
      padding: 0.9rem 0.55rem; text-align: center; background: var(--vh-surface, rgba(255, 255, 255, 0.02));
      transform: rotate(-1.2deg); transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .vh-stamp:nth-child(3n) { transform: rotate(1.4deg); }
    .vh-stamp:hover { transform: rotate(0deg) scale(1.03); }
    .vh-stamp .vh-flag { font-size: 1.6rem; line-height: 1; }
    .vh-stamp-iata { font-weight: 800; letter-spacing: 0.08em; font-size: 1.05rem; margin-top: 0.35rem; }
    .vh-stamp-name { font-size: 0.72rem; color: var(--vh-text-muted); }
    .vh-stamp-date { font-size: 0.68rem; color: var(--vh-text-muted); margin-top: 0.25rem; }
    .vh-stamp.is-visited { border-color: var(--vh-accent, #b39ddb); }
    .vh-stamp.is-visited .vh-stamp-iata { color: var(--vh-accent-lite, #DFC8F5); }
    .vh-stamp.is-locked { opacity: 0.45; filter: grayscale(1); }
  </style>

  {{-- Sellos ganados --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-stamp"></i> @lang('vholar_passport.stamps')</span>
      <span class="small" style="color: var(--vh-text-muted);">{{ $passport['visited'] }}</span>
    </div>
    <div class="card-body">
      @if($passport['visited'] === 0)
        <div class="text-center p-4">
          <div class="fs-5 fw-semibold mb-1">@lang('vholar_passport.no_stamps')</div>
          <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.no_stamps_hint')</div>
        </div>
      @else
        <div class="vh-stamp-grid">
          @foreach($passport['stamps'] as $stamp)
            <div class="vh-stamp is-visited" title="{{ $stamp['name'] }}">
              <div class="vh-flag"><span class="fi fi-{{ strtolower($stamp['country']) }}"></span></div>
              <div class="vh-stamp-iata">{{ $stamp['iata'] }}</div>
              <div class="vh-stamp-name">{{ \Illuminate\Support\Str::limit($stamp['name'], 24) }}</div>
              <div class="vh-stamp-date">
                @if($stamp['first_seen'])
                  {{ \Illuminate\Support\Carbon::parse($stamp['first_seen'])->format('d M Y') }}
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- Sellos de tour --}}
  @if(count($passport['tours']))
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-award"></i> @lang('vholar_passport.tours')</div>
      <div class="card-body">
        <div class="vh-stamp-grid">
          @foreach($passport['tours'] as $tour)
            <div class="vh-stamp is-visited" style="border-color: var(--vh-warning, #d4a24c)">
              <div class="vh-flag"><i class="bi bi-award" style="color: var(--vh-warning, #d4a24c)"></i></div>
              <div class="vh-stamp-iata" style="color: var(--vh-warning, #d4a24c)">{{ $tour['code'] }}</div>
              <div class="vh-stamp-name">@lang('vholar_passport.tour_stamp')</div>
              <div class="vh-stamp-date">{{ $tour['legs'] }} {{ trans_choice('common.legs', $tour['legs']) }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Pendientes, plegados --}}
  @if(count($passport['missing']))
    <details class="card">
      <summary class="card-header" style="cursor: pointer;">
        <i class="bi bi-lock"></i> @lang('vholar_passport.missing_title')
        <span class="small" style="color: var(--vh-text-muted);">({{ count($passport['missing']) }})</span>
      </summary>
      <div class="card-body">
        <p class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.missing_hint')</p>
        <div class="vh-stamp-grid">
          @foreach($passport['missing'] as $stamp)
            <div class="vh-stamp is-locked" title="{{ $stamp['name'] }}">
              <div class="vh-flag"><span class="fi fi-{{ strtolower($stamp['country']) }}"></span></div>
              <div class="vh-stamp-iata">{{ $stamp['iata'] }}</div>
              <div class="vh-stamp-name">{{ \Illuminate\Support\Str::limit($stamp['name'], 24) }}</div>
              <div class="vh-stamp-date">@lang('vholar_passport.not_visited')</div>
            </div>
          @endforeach
        </div>
      </div>
    </details>
  @endif
@endsection
