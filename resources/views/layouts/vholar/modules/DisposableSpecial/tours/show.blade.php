@extends('app')
@section('title', $tour->tour_name)

@section('content')
@php
  $legs = $tour->legs;
  $totalLegs = $legs->count();

  // Distancias: se leen de la tabla porque el modelo Flight castea distance a Unit y
  // sum()/pluck() devuelven el objeto (y (float) de un objeto es 1).
  $legNmi = \Illuminate\Support\Facades\DB::table('flights')
      ->whereIn('id', $legs->pluck('id')->all())
      ->pluck('distance', 'id');
  $totalNmi = (float) $legNmi->sum();
  $distanceText = number_format($totalNmi).' nm ('.number_format($totalNmi * 1.852).' km)';

  $flownLegs = collect($leg_checks)->filter(fn ($check) => $check === true)->count();
  $flownPct = $totalLegs > 0 ? (int) round($flownLegs * 100 / $totalLegs) : 0;

  // Portada: misma convencion que el listado (imagen opcional dentro del tema).
  $cover = null;
  foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
      if (file_exists(public_path("assets/themes/vholar/tours/{$tour->tour_code}.{$ext}"))) {
          $cover = public_asset("assets/themes/vholar/tours/{$tour->tour_code}.{$ext}");
          break;
      }
  }

  if ($carbon_now > $tour->end_date) {
      $badge = [__('vholar_tours.badge_past'), 'is-past'];
  } elseif ($carbon_now < $tour->start_date) {
      $badge = [__('vholar_tours.badge_next'), 'is-next'];
  } elseif ($tour->end_date->diffInDays($carbon_now) <= 30) {
      $badge = [__('vholar_tours.badge_soon', ['days' => (int) $tour->end_date->diffInDays($carbon_now)]), 'is-soon'];
  } else {
      $badge = [__('vholar_tours.badge_now'), 'is-now'];
  }
@endphp

<style>
.vh-tour-hero { overflow: hidden; }
.vh-tour-hero-cover {
  position: relative;
  height: 210px;
  background-color: #2b2333;
  background-image: linear-gradient(135deg, #412C4D 0%, #563A63 45%, #7E57C2 100%);
  background-size: cover;
  background-position: center;
}
.vh-tour-cover-code {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.4rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  color: rgba(255, 255, 255, 0.22);
}
.vh-tour-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 3px 8px;
  border-radius: 20px;
  color: #fff;
  background: rgba(0, 0, 0, 0.45);
}
.vh-tour-badge.is-now { background: #4CAF76; }
.vh-tour-badge.is-soon { background: #EF6C00; }
.vh-tour-badge.is-next { background: #4A90D9; }
.vh-tour-badge.is-past { background: #607D8B; }
.vh-tour-section {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.95rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--vh-text-muted, #9898b0);
  margin: 0 0 12px;
}
.vh-tour-section .badge {
  background: rgba(255, 255, 255, 0.08);
  color: var(--vh-text-muted, #9898b0);
  font-size: 0.68rem;
  font-weight: 600;
}
.vh-tour-meta { font-size: 0.75rem; color: var(--vh-text-muted, #9898b0); }
.vh-tour-map { height: 380px; width: 100%; }
.vh-tour-legs .accordion-item {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  overflow: hidden;
}
.vh-tour-legs .accordion-button {
  background: rgba(255, 255, 255, 0.03);
  color: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  padding: 10px 14px;
}
.vh-tour-legs .accordion-button:not(.collapsed) {
  background: rgba(100, 80, 160, 0.22);
  color: inherit;
  box-shadow: none;
}
.vh-tour-legs .accordion-button:focus { box-shadow: none; border-color: transparent; }
.vh-tour-legs .accordion-button::after { filter: invert(1) opacity(0.6); }
.vh-tour-legs .accordion-body { font-size: 0.82rem; padding: 14px; }
</style>

{{-- ===== Sobre este tour ===== --}}
<div class="card vh-tour-hero mb-4">
  <div class="vh-tour-hero-cover" @if($cover) style="background-image: url('{{ $cover }}');" @endif>
    @unless($cover)
      <span class="vh-tour-cover-code">{{ $tour->tour_code }}</span>
    @endunless
    <span class="vh-tour-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
  </div>
  <div class="card-body">
    <div class="vh-tour-section" style="margin-bottom: 6px;">
      <i class="bi bi-info-circle"></i> @lang('vholar_tours.about')
    </div>

    <h3 class="mb-1">{{ $tour->tour_name }}</h3>

    <div class="vh-tour-meta mb-3">
      @if($tour->airline)
        <img class="me-1" src="{{ $tour->airline->logo }}" alt="{{ $tour->airline->name }}" style="max-height:18px;">
      @endif
      <i class="bi {{ $tour->airline ? 'bi-building' : 'bi-globe2' }}"></i>
      {{ $tour->airline ? __('DSpecial::tours.tairline') : __('DSpecial::tours.topen') }}
      &nbsp;·&nbsp; <i class="bi bi-hash"></i> {{ $tour->tour_code }}
      &nbsp;·&nbsp; <i class="bi bi-calendar3"></i>
      {{ $tour->start_date->format('d M Y') }} — {{ $tour->end_date->format('d M Y') }}
    </div>

    @if(filled($tour->tour_desc))
      <div class="mb-3" style="font-size: 0.9rem;">{!! $tour->tour_desc !!}</div>
    @endif

    <p class="mb-2" style="font-size: 0.9rem;">
      {!! trans_choice('vholar_tours.summary', $totalLegs, ['distance' => '<b>'.$distanceText.'</b>']) !!}
    </p>

    @if($tour->tour_token > 0)
      <p class="mb-2 vh-tour-meta">
        <i class="bi bi-bag"></i> @lang('vholar_tours.requires_token')
        <a href="{{ route('DSpecial.market').'?cat='.$market_cat }}">{{ optional($tour->token)->name }}</a>
      </p>
    @endif

    @auth
      <div class="mt-3">
        <div class="d-flex justify-content-between vh-tour-meta mb-1">
          <span>@lang('vholar_tours.progress', ['flown' => $flownLegs, 'total' => $totalLegs])</span>
          <span>{{ $flownPct }}%</span>
        </div>
        <div class="progress" style="height: 6px;">
          <div class="progress-bar bg-success" role="progressbar" style="width: {{ $flownPct }}%"
               aria-valuenow="{{ $flownPct }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    @endauth
  </div>
</div>

{{-- ===== Mapa ===== --}}
@if($totalLegs > 0)
  <h5 class="vh-tour-section"><i class="bi bi-map"></i> @lang('vholar_tours.section_map')</h5>
  <div class="card mb-4">
    <div id="tourmap" class="vh-tour-map"></div>
  </div>
@endif

{{-- ===== Tramos ===== --}}
<h5 class="vh-tour-section">
  <i class="bi bi-signpost-2"></i> @lang('vholar_tours.section_legs')
  <span class="badge">{{ $totalLegs }}</span>
</h5>

<div class="accordion vh-tour-legs mb-4" id="tourLegs">
  @foreach($legs as $leg)
    @php
      $legNo = $leg->pivot->leg ?? $leg->route_leg;
      $isFlown = ($leg_checks[$legNo] ?? false) === true;
      $nmi = (float) ($legNmi[$leg->id] ?? 0);
      $subfleets = $leg->subfleets;
      $previousFlown = $legNo == 1 || (($leg_checks[$legNo - 1] ?? false) === true);
      $atCurrentAirport = !setting('pilots.only_flights_from_current') || $leg->dpt_airport_id == optional($user)->curr_airport_id;
    @endphp

    <div class="accordion-item mb-2">
      <h2 class="accordion-header" id="leg-head-{{ $legNo }}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#leg-body-{{ $legNo }}" aria-expanded="false"
                aria-controls="leg-body-{{ $legNo }}">
          <span>@lang('vholar_tours.leg', ['number' => $legNo]) :
            <b>{{ $leg->dpt_airport_id }} - {{ $leg->arr_airport_id }}</b></span>
          {{-- Numero del vuelo real de la aerolinea que hay que volar en este tramo --}}
          <span class="ms-2 badge text-bg-secondary p-1" title="@lang('vholar_tours.view_flight')">{{ $leg->flight_number }}</span>
          <span class="ms-auto me-2 d-flex align-items-center gap-2 vh-tour-meta">
            @if($nmi > 0) <span>{{ number_format($nmi) }} nm</span> @endif
            @if($isFlown)
              <i class="bi bi-check-circle-fill text-success" title="@lang('vholar_tours.status_flown')"></i>
            @else
              <i class="bi bi-circle text-secondary" title="@lang('vholar_tours.status_not_flown')"></i>
            @endif
          </span>
        </button>
      </h2>

      <div id="leg-body-{{ $legNo }}" class="accordion-collapse collapse"
           aria-labelledby="leg-head-{{ $legNo }}" data-bs-parent="#tourLegs">
        <div class="accordion-body">
          <p class="mb-2">
            @lang('vholar_tours.distance'): <b>{{ number_format($nmi) }} nm ({{ number_format($nmi * 1.852) }} km)</b>
          </p>

          <ul class="list-unstyled vh-tour-meta mb-3">
            <li class="mb-1">
              <i class="bi bi-airplane-departure"></i>
              <a href="{{ route('frontend.airports.show', [$leg->dpt_airport_id]) }}">
                {{ optional($leg->dpt_airport)->full_name ?? $leg->dpt_airport_id }}
              </a>
            </li>
            <li class="mb-1">
              <i class="bi bi-airplane-arrival"></i>
              <a href="{{ route('frontend.airports.show', [$leg->arr_airport_id]) }}">
                {{ optional($leg->arr_airport)->full_name ?? $leg->arr_airport_id }}
              </a>
            </li>
            @if($leg->flight_time > 0)
              <li class="mb-1"><i class="bi bi-clock"></i> @lang('vholar_tours.block_time'): @minutestotime($leg->flight_time)</li>
            @endif
            <li class="mb-1">
              <i class="bi bi-calendar-range"></i> @lang('vholar_tours.valid_between'):
              @if($leg->start_date && $leg->end_date)
                {{ $leg->start_date->format('d M Y H:i') }} - {{ $leg->end_date->format('d M Y H:i') }} UTC
              @else
                {{ $tour->start_date->format('d M Y H:i') }} - {{ $tour->end_date->format('d M Y H:i') }} UTC
              @endif
            </li>
            <li>
              <i class="bi bi-airplane-engines"></i> @lang('vholar_tours.eligible_aircraft'):
              @if($subfleets->count() > 0)
                @foreach($subfleets as $subfleet)
                  <span class="badge text-bg-dark p-1 ms-1" title="{{ $subfleet->name }}">{{ $subfleet->type }}</span>
                @endforeach
              @else
                @lang('vholar_tours.any_aircraft')
              @endif
            </li>
          </ul>

          <div class="d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('frontend.flights.show', [$leg->id]) }}">
              <i class="bi bi-info-circle"></i> @lang('vholar_tours.view_flight')
            </a>

            @auth
              @if(!$isFlown && $atCurrentAirport && $previousFlown)
                {{-- Bid (mismo comportamiento que la tabla de tramos del modulo) --}}
                @if(setting('bids.allow_multiple_bids') === true || (setting('bids.allow_multiple_bids') === false && count($saved) === 0))
                  <button class="btn btn-sm save_flight {{ isset($saved[$leg->id]) ? 'btn-danger' : 'btn-success' }}"
                          x-id="{{ $leg->id }}" x-saved-class="btn-danger" type="button" title="@lang('flights.addremovebid')">
                    <i class="bi bi-bookmark-plus"></i> @lang('flights.addremovebid')
                  </button>
                @endif
                {{-- SimBrief --}}
                @if($simbrief && ($simbrief_bids === false || ($simbrief_bids === true && isset($saved[$leg->id]))))
                  @php
                    $aircraft_id = isset($saved[$leg->id]) ? optional($user->bids->firstWhere('flight_id', $leg->id))->aircraft_id : null;
                  @endphp
                  <a class="btn btn-sm {{ isset($saved[$leg->id]) ? 'btn-success' : 'btn-primary' }}"
                     href="{{ route('frontend.simbrief.generate') }}?flight_id={{ $leg->id }}@if($aircraft_id)&aircraft_id={{ $aircraft_id }}@endif">
                    <i class="bi bi-file-earmark-pdf"></i> SimBrief
                  </a>
                @endif
              @endif
            @endauth

            @if($isFlown)
              <span class="vh-tour-meta"><i class="bi bi-check-circle-fill text-success"></i> @lang('vholar_tours.status_flown')</span>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>

@auth
  @if($totalLegs > 0)
    @if(setting('bids.block_aircraft', false))
      @include('flights.bids_aircraft')
    @endif
    @include('flights.scripts')
  @endif
@endauth

{{-- ===== Reglas del tour ===== --}}
@if(filled($tour->tour_rules))
  <h5 class="vh-tour-section"><i class="bi bi-book"></i> @lang('DSpecial::tours.trules')</h5>
  <div class="card mb-4">
    <div class="card-body" style="font-size: 0.85rem;">{!! $tour->tour_rules !!}</div>
  </div>
@endif

{{-- ===== Galardonados ===== --}}
@if(filled($tour_awards))
  <h5 class="vh-tour-section"><i class="bi bi-trophy"></i> @lang('vholar_tours.section_awards')</h5>
  <div class="card mb-4">
    <div class="card-body table-responsive p-0">
      <table class="table table-sm table-borderless table-striped text-start text-nowrap mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>@lang('vholar_tours.awards_pilot')</th>
            <th class="text-end">@lang('vholar_tours.awards_finished')</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tour_awards as $tour_award)
            @if(filled($tour_award->user))
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                  <a href="{{ route('frontend.profile.show', [$tour_award->user->id]) }}">
                    @if(Theme::getSetting('roster_ident')) {{ $tour_award->user->ident.' - ' }} @endif
                    {{ $tour_award->user->name_private }}
                  </a>
                </td>
                <td class="text-end">{{ $tour_award->created_at->format('d M Y H:i') }}</td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer vh-tour-meta p-2 text-end">
      @lang('vholar_tours.awards_footer', ['tour' => $tour->tour_name])
    </div>
  </div>
@endif

{{-- ===== Informe (admin) ===== --}}
@ability('admin', 'admin-access')
  <h5 class="vh-tour-section"><i class="bi bi-file-earmark-text"></i> @lang('vholar_tours.section_report')</h5>
  <div class="card mb-4">
    <div class="card-body table-responsive p-0">
      @include('DSpecial::tours.report_table')
    </div>
  </div>
@endability
@endsection

@push('scripts')
  @if($totalLegs > 0)
    {{-- El mapa es inline (no modal) y usa capas planas: la version del modulo llamaba
         a L.tileLayer.provider(), de un plugin que el tema no carga. --}}
    <script src="https://unpkg.com/leaflet-geodesic@1.0.0/leaflet.geodesic.js"></script>
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', function () {
        var vmsIcon = new L.Icon({!! $mapIcons['vmsIcon'] !!});
        var RedIcon = new L.Icon({!! $mapIcons['RedIcon'] !!});
        var GreenIcon = new L.Icon({!! $mapIcons['GreenIcon'] !!});
        var BlueIcon = new L.Icon({!! $mapIcons['BlueIcon'] !!});
        var YellowIcon = new L.Icon({!! $mapIcons['YellowIcon'] !!});
        // Colores de las lineas (los nombres los usan las capas de abajo)
        var Flown = 'darkgreen';
        var NotFlown = 'darkred';
        var CheckDisabled = 'crimson';

        var mBoundary = L.featureGroup();
        var mAirports = L.layerGroup();
        @foreach ($mapAirports as $airport)
          var APT_{{ $airport['id'] }} = L.marker([{{ $airport['loc'] }}], {icon: {{ $airport['icon'] }}, opacity: 0.8}).bindPopup({!! "'".$airport['pop']."'" !!}).addTo(mAirports).addTo(mBoundary);
        @endforeach

        var mFlights = L.layerGroup();
        @foreach ($mapFlights as $flight)
          var FLT_{{ $flight['id'] }} = L.geodesic({{ $flight['geod'] }}, {weight: 2, opacity: 0.8, steps: 5, color: {{ $flight['geoc'] }}}).bindPopup({!! "'".$flight['pop']."'" !!}).addTo(mFlights);
        @endforeach

        // Capas base sin plugin de proveedores. CARTO lleva la ApiKey del ajuste.
        var DarkMatter = L.tileLayer('{!! carto_tile_url('dark_all') !!}', {maxZoom: 19, attribution: '&copy; OpenStreetMap &copy; CARTO'});
        var NatGeo = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/NatGeo_World_Map/MapServer/tile/{z}/{y}/{x}', {maxZoom: 16, attribution: '&copy; Esri'});
        var OpenSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'});
        var OpenTopo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {maxZoom: 17, attribution: '&copy; OpenTopoMap'});
        var WorldTopo = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {maxZoom: 19, attribution: '&copy; Esri'});

        var BaseLayers = {"Dark Matter": DarkMatter, "NatGEO World": NatGeo, "OpenSM Mapnik": OpenSM, "Open Topo": OpenTopo, "World Topo": WorldTopo};
        var Overlays = {"Tour Airports": mAirports, "Tour Legs": mFlights};

        var TourMap = L.map('tourmap', {center: {{ $mapCenter }}, layers: [DarkMatter, mAirports, mFlights]}).fitBounds(mBoundary.getBounds().pad(0.2));
        L.control.layers(BaseLayers, Overlays).addTo(TourMap);
        setTimeout(function () { TourMap.invalidateSize().fitBounds(mBoundary.getBounds().pad(0.2)); }, 300);
      });
    </script>
  @endif
@endpush
