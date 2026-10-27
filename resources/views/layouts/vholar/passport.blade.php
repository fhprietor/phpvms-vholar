@extends('app')

@section('title', __('vholar_passport.title'))

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-passport"></i> @lang('vholar_passport.title')</h4>
    <div style="font-size: 0.75rem; color: var(--vh-text-muted);">@lang('vholar_passport.subtitle')</div>
  </div>

  {{-- Totales --}}
  <div class="row g-3 mb-3">
    <div class="col-4">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ $passport['countries_visited'] }}/{{ $passport['countries_total'] }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.countries')</div>
      </div></div>
    </div>
    <div class="col-4">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ $passport['visited_airports'] }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.airports')</div>
      </div></div>
    </div>
    <div class="col-4">
      <div class="card h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ count($passport['tours']) }}</div>
        <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.tours')</div>
      </div></div>
    </div>
  </div>

  <style>
    .vh-passport details > summary { cursor: pointer; list-style: none; }
    .vh-passport details > summary::-webkit-details-marker { display: none; }
    .vh-passport details[open] > summary { border-bottom: 1px solid var(--vh-border); }
    .vh-passport details > summary .vh-caret { transition: transform 0.15s ease; }
    .vh-passport details[open] > summary .vh-caret { transform: rotate(90deg); }
    .vh-flag { font-size: 1.15rem; line-height: 1; }
    .vh-chip {
      display: inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.72rem;
      background: var(--vh-surface-2, rgba(255, 255, 255, 0.06)); color: var(--vh-text);
    }
  </style>

  {{-- Países visitados --}}
  @if($passport['countries_visited'] === 0)
    <div class="card mb-3"><div class="card-body text-center p-4">
      <div class="fs-5 fw-semibold mb-1">@lang('vholar_passport.no_stamps')</div>
      <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.no_stamps_hint')</div>
    </div></div>
  @else
    <div class="row g-3 vh-passport">
      @foreach($passport['countries'] as $country)
        <div class="col-lg-6">
          <details class="card h-100">
            <summary class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-chevron-right vh-caret" style="font-size: 0.7rem;"></i>
              <span class="vh-flag"><span class="fi fi-{{ strtolower($country['country']) }}"></span></span>
              <span class="fw-semibold">{{ $country['name'] }}</span>
              <span class="small ms-auto" style="color: var(--vh-text-muted);">
                {{ count($country['airports']) }} @lang('vholar_passport.airports')
                · {{ $country['total_flights'] }} @lang('vholar_passport.flights')
              </span>
            </summary>
            <div class="card-body">
              @if($country['first_seen'])
                <p class="small mb-2" style="color: var(--vh-text-muted);">
                  @lang('vholar_passport.first_visit'): {{ \Illuminate\Support\Carbon::parse($country['first_seen'])->format('d M Y') }}
                </p>
              @endif

              <p class="small fw-semibold mb-1">@lang('vholar_passport.airports')</p>
              <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach($country['airports'] as $airport)
                  <span class="vh-chip" title="{{ $airport['name'] }}">
                    <strong>{{ $airport['iata'] }}</strong> {{ \Illuminate\Support\Str::limit($airport['name'], 22) }}
                  </span>
                @endforeach
              </div>

              <p class="small fw-semibold mb-1">@lang('vholar_passport.aircraft_types')</p>
              <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach($country['types'] as $type => $count)
                  <span class="vh-chip"><strong>{{ $type }}</strong> ×{{ $count }}</span>
                @endforeach
              </div>

              <p class="small fw-semibold mb-1">@lang('vholar_passport.flights')</p>
              <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size: 0.78rem;">
                  <thead>
                    <tr>
                      <th>@lang('vholar_passport.flight')</th>
                      <th>@lang('vholar_passport.route')</th>
                      <th class="text-nowrap">@lang('vholar_passport.date')</th>
                      <th>@lang('vholar_passport.aircraft')</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($country['flights'] as $flight)
                      <tr>
                        <td class="fw-semibold">{{ $flight['number'] }}</td>
                        <td>{{ $flight['dpt'] }} → {{ $flight['arr'] }}</td>
                        <td class="text-nowrap" style="color: var(--vh-text-muted);">
                          {{ \Illuminate\Support\Carbon::parse($flight['date'])->format('d M Y') }}
                        </td>
                        <td>
                          {{ $flight['type'] }}
                          @if($flight['reg'])<span style="color: var(--vh-text-muted);">· {{ $flight['reg'] }}</span>@endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </details>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Sellos de tour --}}
  @if(count($passport['tours']))
    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-award"></i> @lang('vholar_passport.tours')</div>
      <div class="card-body d-flex flex-wrap gap-2">
        @foreach($passport['tours'] as $tour)
          <div class="vh-chip" style="border: 1px solid var(--vh-warning, #d4a24c); color: var(--vh-warning, #d4a24c);">
            <i class="bi bi-award"></i> <strong>{{ $tour['code'] }}</strong> · {{ $tour['legs'] }} @lang('vholar_passport.legs')
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Países pendientes --}}
  @if(count($passport['missing']))
    <details class="card mt-3">
      <summary class="card-header" style="cursor: pointer;">
        <i class="bi bi-lock"></i> @lang('vholar_passport.missing_countries')
        <span class="small" style="color: var(--vh-text-muted);">({{ count($passport['missing']) }})</span>
      </summary>
      <div class="card-body">
        <p class="small" style="color: var(--vh-text-muted);">@lang('vholar_passport.missing_countries_hint')</p>
        <div class="d-flex flex-wrap gap-2">
          @foreach($passport['missing'] as $iso => $name)
            <span class="vh-chip" style="opacity: 0.75;">
              <span class="fi fi-{{ strtolower($iso) }}"></span> {{ $name }}
            </span>
          @endforeach
        </div>
      </div>
    </details>
  @endif
@endsection
