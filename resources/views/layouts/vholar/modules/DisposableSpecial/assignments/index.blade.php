@extends('app')
@section('title', __('common.flight_assignments'))

@section('content')

@once
@include('vholar::pireps.logbook-styles')
<style>
/* Assignment-specific overrides */
.lb-order-badge {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 700;
  color: #7a6a9a;
  background: rgba(100,70,140,0.18);
  border-radius: 4px;
  padding: 2px 6px;
  letter-spacing: 0.04em;
  vertical-align: middle;
  margin-right: 4px;
}
.lb-sched-time {
  font-family: 'Courier New', monospace;
  font-size: 0.82rem;
  font-weight: 600;
  color: #7a8aaa;
  letter-spacing: 0.03em;
}
.lb-as-status {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 3px 9px;
  border-radius: 20px;
}
.lb-actions { white-space: nowrap; }
.lb-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.68rem;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 6px;
  text-decoration: none;
  transition: background 0.15s;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.04);
  color: #a0a8c0 !important;
  vertical-align: middle;
}
.lb-action-btn:hover { background: rgba(100,80,140,0.25); color: #dce4ff !important; }
.lb-action-btn.btn-bid-add  { border-color: rgba(40,167,69,0.4); color: #4caf76 !important; }
.lb-action-btn.btn-bid-rem  { border-color: rgba(220,53,69,0.4); color: #e05060 !important; }
/* Always-visible secondary row (aircraft + actions) */
.lb-as-sub td { padding: 0 !important; border: none !important; }
.lb-as-sub-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 14px 9px;
}
/* Collapsible details row */
.lb-detail-row td { padding: 0 !important; border: none !important; }
.lb-detail-inner {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  padding: 8px 20px;
  border-top: 1px solid rgba(255,255,255,0.05);
  background: rgba(0,0,0,0.35);
  font-size: 0.75rem;
  color: #9898b0;
}
.lb-detail-inner a { color: #8898cc; text-decoration: none; }
.lb-detail-inner a:hover { color: #90aaff; }
/* Full map button overlaid top-right on the inline map */
.vholar-fullmap-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 999;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  padding: 4px 11px;
  border-radius: 6px;
  border: none;
  background: rgba(200, 40, 50, 0.85);
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(4px);
  transition: background 0.15s;
}
.vholar-fullmap-btn:hover { background: rgba(220, 50, 60, 1); }
.lb-detail-label {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #9090a8;
  margin-bottom: 2px;
}
.lb-detail-val {
  font-size: 0.78rem;
  color: #a0a8c0;
  font-weight: 600;
}
/* Month section header */
.lb-month-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: rgba(65,44,77,0.40);
  border-bottom: 1px solid rgba(255,255,255,0.09);
}
.lb-month-title {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #9080b0;
}
</style>
@endonce

      <div class="row g-3 align-items-start">

      {{-- Map: order-1 mobile · col-lg-8 + order-lg-1 desktop --}}
      @if(count($assignments) > 0)
      <div class="col-12 col-lg-8 order-1">
      <div class="card mb-3" style="border-radius:10px;overflow:hidden;position:relative;">
        <div class="card-body p-1">
          <div id="assignmentsMap" style="height:260px;width:100%;border-radius:8px;"></div>
        </div>
        <button type="button" class="vholar-fullmap-btn"
                data-bs-toggle="modal" data-bs-target="#vholarFullMapModal">
          <i class="bi bi-arrows-fullscreen"></i> @lang('common.open_full_map')
        </button>
      </div>

      <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
      <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
      <script src="https://unpkg.com/leaflet-geodesic@1.0.0/leaflet.geodesic.js"></script>
      <script>
      (function() {
        function initMap() {
          var el = document.getElementById('assignmentsMap');
          if (!el || window.assignmentsMapInitialized || typeof L === 'undefined') {
            if (typeof L === 'undefined') setTimeout(initMap, 200);
            return;
          }
          window.assignmentsMapInitialized = true;
          var map = L.map('assignmentsMap').setView([4.5709, -74.2973], 5);
          L.tileLayer('{{ carto_tile_url('light_all') }}', {
            attribution: '&copy; OSM &copy; CartoDB', subdomains: 'abcd', minZoom: 2, maxZoom: 18
          }).addTo(map);
          var airports = {}, routes = [], bounds = [];
          @foreach($assignments as $group => $tas)
            @foreach($tas as $as)
              @if($as->flight && $as->flight->dpt_airport && $as->flight->arr_airport)
                @php $dpt = $as->flight->dpt_airport; $arr = $as->flight->arr_airport; @endphp
                @if($dpt && $dpt->lat && $dpt->lon)
                  if (!airports['{{ $dpt->id }}']) {
                    airports['{{ $dpt->id }}'] = { lat:{{ $dpt->lat }}, lon:{{ $dpt->lon }}, name:'{{ addslashes($dpt->name) }}', id:'{{ $dpt->id }}', iata:'{{ $dpt->iata ?? "" }}' };
                    bounds.push([{{ $dpt->lat }}, {{ $dpt->lon }}]);
                  }
                @endif
                @if($arr && $arr->lat && $arr->lon)
                  if (!airports['{{ $arr->id }}']) {
                    airports['{{ $arr->id }}'] = { lat:{{ $arr->lat }}, lon:{{ $arr->lon }}, name:'{{ addslashes($arr->name) }}', id:'{{ $arr->id }}', iata:'{{ $arr->iata ?? "" }}' };
                    bounds.push([{{ $arr->lat }}, {{ $arr->lon }}]);
                  }
                @endif
                @if($dpt && $dpt->lat && $dpt->lon && $arr && $arr->lat && $arr->lon)
                  routes.push({ from:'{{ $dpt->id }}', to:'{{ $arr->id }}', latlngs:[[{{ $dpt->lat }},{{ $dpt->lon }}],[{{ $arr->lat }},{{ $arr->lon }}]], ident:'{{ $as->flight->ident }}', order:{{ $as->assignment_order }} });
                @endif
              @endif
            @endforeach
          @endforeach
          for (var id in airports) {
            var apt = airports[id];
            var aptLabel = apt.iata ? apt.id + '/' + apt.iata : apt.id;
            var aptW = Math.max(72, aptLabel.length * 8 + 20);
            L.marker([apt.lat, apt.lon], {
              icon: L.divIcon({
                className: '',
                html: '<div style="width:'+aptW+'px;text-align:center;">' +
                  '<span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.62rem;font-weight:800;color:#0d0b18;background:#c8d8ff;border:1.5px solid rgba(80,100,200,0.5);border-radius:4px;padding:1px 5px;box-shadow:0 2px 4px rgba(0,0,0,0.6);letter-spacing:0.03em;">&#9992; ' + aptLabel + '</span>' +
                '</div>',
                iconSize: [aptW, 20],
                iconAnchor: [aptW/2, 10]
              })
            }).bindPopup('<strong>'+aptLabel+'</strong><br>'+(apt.name||'')).addTo(map);
          }
          var colors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#2c3e50'];
          var midCounts = {}, midIdx = {};
          routes.forEach(function(r) {
            var k = ((r.latlngs[0][0]+r.latlngs[1][0])/2).toFixed(3)+','+((r.latlngs[0][1]+r.latlngs[1][1])/2).toFixed(3);
            midCounts[k] = (midCounts[k] || 0) + 1;
          });
          routes.forEach(function(r) {
            var color = colors[(r.order-1) % colors.length];
            L.geodesic(r.latlngs, {weight:2.5, opacity:0.75, color:color, steps:10})
             .bindPopup('<strong>'+r.ident+'</strong><br>'+r.from+' → '+r.to).addTo(map);
            var midLat = (r.latlngs[0][0] + r.latlngs[1][0]) / 2;
            var midLon = (r.latlngs[0][1] + r.latlngs[1][1]) / 2;
            var k = midLat.toFixed(3)+','+midLon.toFixed(3);
            var idx = midIdx[k] || 0;
            midIdx[k] = idx + 1;
            var total = midCounts[k] || 1;
            var dlat = r.latlngs[1][0] - r.latlngs[0][0];
            var dlon = r.latlngs[1][1] - r.latlngs[0][1];
            var len  = Math.sqrt(dlat*dlat + dlon*dlon) || 1;
            var spread = Math.max(len * 0.12, 0.25);
            var shift  = (idx - (total - 1) / 2) * spread;
            L.marker([midLat + (-dlon/len)*shift, midLon + (dlat/len)*shift], {
              icon: L.divIcon({
                className: '',
                html: '<div style="width:120px;text-align:center;"><span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.65rem;font-weight:700;color:#0d0b14;background:'+color+';border-radius:4px;padding:2px 8px;box-shadow:0 2px 5px rgba(0,0,0,0.65);">'+r.ident+'/'+r.order+'</span></div>',
                iconSize: [120, 22],
                iconAnchor: [60, 11]
              })
            }).addTo(map);
          });
          if (bounds.length) map.fitBounds(bounds);
          setTimeout(function(){ map.invalidateSize(); }, 100);
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function(){ setTimeout(initMap, 300); });
        } else { setTimeout(initMap, 300); }
      })();
      </script>

      {{-- Full-screen map modal (native BS5, independent of DBasic widget) --}}
      <div class="modal fade" id="vholarFullMapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
          <div class="modal-content" style="background:#0d0b14;">
            <div class="modal-header" style="background:#1a1828;border-bottom:1px solid rgba(120,100,180,0.2);padding:10px 16px;">
              <h5 class="modal-title" style="color:#c0c8e8;font-size:0.85rem;font-weight:800;letter-spacing:0.07em;text-transform:uppercase;">
                <i class="bi bi-map me-2"></i>@lang('common.full_assignments_map')
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow:hidden;">
              <div id="vholarFullMap" style="width:100%;height:85vh;"></div>
            </div>
          </div>
        </div>
      </div>
      </div>{{-- /col-lg-8 map --}}
      @endif

      {{-- Assignments: order-2 mobile · col-12 order-lg-3 desktop (full-width below) --}}
      <div class="col-12 order-2 order-lg-3">

      {{-- Page header --}}
      <div class="mb-3 d-flex align-items-center justify-content-between">
        <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:#9898b0;">
          ✈ &nbsp;@lang('common.flight_assignments')
        </h4>
      </div>

      {{-- Monthly groups --}}
      @foreach ($assignments as $group => $tas)
        @php
          $firstAs = $tas->first();
          $year    = $firstAs ? $firstAs->assignment_year : date('Y');
          $monthLabel = Carbon::createFromDate($year, $group, 1)->format('F Y');
          $completedCount = $tas->where('completed', true)->count();
        @endphp

        <div class="card vholar-logbook-wrap mb-4">
          <div class="lb-month-header">
            <span class="lb-month-title">{{ $monthLabel }}</span>
            <span style="font-size:0.68rem;color:#9898b2;letter-spacing:0.05em;">
              {{ $completedCount }}/{{ $tas->count() }} @lang('common.completed')
            </span>
          </div>

          <div class="table-responsive">
            <table class="table vholar-logbook">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ trans_choice('common.flight', 1) }}</th>
                  <th>@lang('flights.route')</th>
                  <th class="text-center">@lang('flights.dep')</th>
                  <th class="text-center">@lang('flights.arr')</th>
                  <th class="text-center">@lang('common.block_time')</th>
                  <th class="text-center">@lang('common.score')</th>
                  <th class="text-center">@lang('common.status')</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($tas->sortBy('assignment_order', SORT_NATURAL) as $as)
                  @php
                    $flight      = $as->flight;
                    $isCompleted = $as->completed;
                    $pirepStatus = $as->pirep_status;
                    $isCurrentMonth = $as->assignment_month == $curr_month;
                    $isBid = isset($saved[$flight->id]) ? true : false;
                    $canBid = ($flight && !$isCompleted && $pirepStatus !== 'in_progress' &&
                      $isCurrentMonth && (!setting('pilots.only_flights_from_current') ||
                      $flight->dpt_airport_id == optional($user->current_airport)->icao));

                    // Linked pirep
                    $linkedPirep = null;
                    if ($isCompleted) {
                      $linkedPirep = \App\Models\Pirep::with('aircraft')
                        ->where(['user_id' => auth()->id(), 'flight_id' => $as->flight_id])
                        ->whereMonth('submitted_at', $as->assignment_month)
                        ->whereYear('submitted_at', $as->assignment_year)
                        ->where('state', \App\Models\Enums\PirepState::ACCEPTED)
                        ->first();
                    }

                    // Block time
                    $bt  = $linkedPirep ? ($linkedPirep->block_time ?: $linkedPirep->flight_time) : null;
                    $plannedBt = $flight ? $flight->flight_time : null;
                    $displayBt = $bt ?? $plannedBt;
                    $btH = $displayBt ? intdiv($displayBt, 60) : 0;
                    $btM = $displayBt ? ($displayBt % 60) : 0;

                    // Dep/Arr times
                    $depTime = null;
                    $arrTime = null;
                    if ($linkedPirep) {
                      $depTime = $linkedPirep->block_off_time;
                      $arrTime = $linkedPirep->block_on_time;
                    } elseif ($flight && $flight->dpt_time) {
                      $depTime = \Carbon\Carbon::parse($flight->dpt_time);
                      $arrTime = $flight->arr_time ? \Carbon\Carbon::parse($flight->arr_time) : null;
                    }

                    // Distance
                    $distance = null;
                    try {
                      if ($linkedPirep && $linkedPirep->distance) $distance = number_format($linkedPirep->distance->internal(), 0).' nm';
                      elseif ($flight && $flight->distance && $flight->distance->internal() > 0) $distance = number_format($flight->distance->internal(), 0).' nm';
                    } catch (\Exception $e) {}

                    // Score
                    $score      = $linkedPirep ? $linkedPirep->score : null;
                    $scoreClass = '';
                    if ($score !== null) {
                      $scoreClass = $score >= 80 ? 'lb-score-good' : ($score >= 60 ? 'lb-score-ok' : 'lb-score-bad');
                    }

                    // Aircraft
                    $acTypes   = [];
                    $acIcao    = null;
                    $acReg     = null;
                    $hasReservedAircraft = false;
                    $reservedRegistration = null;
                    $reservedSubfleetName = null;
                    $reservedAircraftIcao = '';
                    if ($isCompleted && $linkedPirep && $linkedPirep->aircraft) {
                      $acIcao = $linkedPirep->aircraft->icao;
                      $acReg  = $linkedPirep->aircraft->registration;
                    } elseif ($isBid && isset($saved[$flight->id])) {
                      $bid = App\Models\Bid::find($saved[$flight->id]);
                      if ($bid && $bid->aircraft_id) {
                        $aircraft = App\Models\Aircraft::find($bid->aircraft_id);
                        if ($aircraft) {
                          $hasReservedAircraft  = true;
                          $reservedRegistration = $aircraft->registration;
                          $reservedSubfleetName = $aircraft->subfleet->name ?? ($aircraft->name ?? $aircraft->icao);
                          $reservedAircraftIcao = $aircraft->icao ?? '';
                          $acIcao = $aircraft->icao;
                          $acReg  = $aircraft->registration;
                        }
                      }
                    } elseif ($flight) {
                      foreach ($flight->subfleets as $sf) {
                          $acTypes[] = ['icao' => $sf->icao ?? $sf->type, 'name' => $sf->name ?? ''];
                      }
                    }

                    // Status
                    if ($pirepStatus === 'in_progress') {
                      $asStatusColor = 'bg-info text-dark'; $asStatusLabel = __('common.in_flight');
                    } elseif ($isCompleted) {
                      $asStatusColor = 'bg-success'; $asStatusLabel = __('common.completed');
                    } elseif ($isCurrentMonth) {
                      $asStatusColor = 'bg-warning text-dark'; $asStatusLabel = __('common.active');
                    } else {
                      $asStatusColor = 'bg-secondary'; $asStatusLabel = __('common.past');
                    }
                  @endphp

                  {{-- Row 1: logbook data --}}
                  <tr class="pirep-toggle"
                      role="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#asd-{{ $as->id }}"
                      aria-expanded="false">

                    {{-- Order --}}
                    <td>
                      <span class="lb-toggle-icon">&#9654;</span>
                      <span class="lb-order-badge">#{{ $as->assignment_order }}</span>
                    </td>

                    {{-- Flight --}}
                    <td>
                      @if($isCompleted && $linkedPirep)
                        <a href="{{ route('frontend.pireps.show', [$linkedPirep->id]) }}"
                           class="lb-fltnum" onclick="event.stopPropagation()">
                          {{ $flight ? $flight->ident : '—' }}
                        </a>
                      @elseif($flight)
                        <a href="{{ route('frontend.flights.show', [$flight->id]) }}"
                           class="lb-fltnum" onclick="event.stopPropagation()">
                          {{ $flight->ident }}
                        </a>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                      @if($flight && $flight->airline)
                        <span class="lb-airline">{{ $flight->airline->name }}</span>
                      @endif
                    </td>

                    {{-- Route --}}
                    <td>
                      @if($flight)
                        @php $dptApt = $flight->dpt_airport; $arrApt = $flight->arr_airport; @endphp
                        <div class="lb-route">
                          @if($dptApt && $dptApt->country)
                            <span class="fi fi-{{ strtolower($dptApt->country) }}" style="font-size:0.9em;"></span>
                          @endif
                          <span class="lb-icao">{{ $flight->dpt_airport_id }}</span>
                          <span class="lb-route-arrow">✈</span>
                          @if($arrApt && $arrApt->country)
                            <span class="fi fi-{{ strtolower($arrApt->country) }}" style="font-size:0.9em;"></span>
                          @endif
                          <span class="lb-icao">{{ $flight->arr_airport_id }}</span>
                        </div>
                        <div class="lb-cities">
                          <small>{{ optional($dptApt)->name }}</small>
                          <small>{{ optional($arrApt)->name }}</small>
                        </div>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>

                    {{-- DEP --}}
                    <td class="text-center">
                      @if($depTime)
                        <span class="{{ $isCompleted ? 'lb-time' : 'lb-sched-time' }}">{{ $depTime->format('H:i') }}</span>
                      @else
                        <span class="text-muted" style="font-size:.8rem">—</span>
                      @endif
                    </td>

                    {{-- ARR --}}
                    <td class="text-center">
                      @if($arrTime)
                        <span class="{{ $isCompleted ? 'lb-time' : 'lb-sched-time' }}">{{ $arrTime->format('H:i') }}</span>
                      @else
                        <span class="text-muted" style="font-size:.8rem">—</span>
                      @endif
                    </td>

                    {{-- Block time --}}
                    <td class="text-center lb-blocktime">
                      @if($displayBt)
                        <span class="lb-blocktime-icon">✈</span>
                        <span class="lb-blocktime-val" style="{{ !$bt ? 'color:#9898b2;' : '' }}">
                          {{ $btH }}:{{ str_pad($btM, 2, '0', STR_PAD_LEFT) }}h
                        </span>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>

                    {{-- Score --}}
                    <td class="text-center">
                      @if($score !== null)
                        <span class="lb-score {{ $scoreClass }}">{{ $score }}</span>
                      @else
                        <span class="text-muted" style="font-size:.8rem">—</span>
                      @endif
                    </td>

                    {{-- Assignment status --}}
                    <td class="text-center">
                      <span class="badge lb-as-status {{ $asStatusColor }}">{{ $asStatusLabel }}</span>
                    </td>
                  </tr>

                  {{-- Row 2: aircraft + actions (always visible) --}}
                  <tr class="lb-as-sub">
                    <td colspan="8">
                      <div class="lb-as-sub-inner">
                        {{-- Aircraft --}}
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                          @if($acIcao || $acReg)
                            @if($acIcao)<span class="lb-ac-type">{{ $acIcao }}</span>@endif
                            @if($acReg)<span class="lb-ac-reg" style="display:inline;margin-left:2px;">{{ $acReg }}</span>@endif
                          @elseif(count($acTypes))
                            @foreach($acTypes as $eq)
                              <span class="lb-ac-type" title="{{ $eq['name'] }}">{{ $eq['icao'] }}</span>
                            @endforeach
                          @else
                            <span class="text-muted" style="font-size:0.72rem;">—</span>
                          @endif
                        </div>
                        {{-- Actions --}}
                        <div class="lb-actions" onclick="event.stopPropagation()">
                          @if($flight)
                            <a href="{{ route('frontend.flights.show', [$flight->id]) }}"
                               class="lb-action-btn" title="Details">
                              <i class="bi bi-eye"></i> @lang('flights.details')
                            </a>
                          @endif

                          @if($isCompleted && $linkedPirep)
                            <a href="{{ route('frontend.pireps.show', [$linkedPirep->id]) }}"
                               class="lb-action-btn" title="View PIREP">
                              <i class="bi bi-file-text"></i> {{ trans_choice('common.pirep', 1) }}
                            </a>
                          @elseif(!$isCompleted && $isCurrentMonth && $flight)
                            <a href="{{ route('frontend.pireps.create') }}?flight_id={{ $flight->id }}"
                               class="lb-action-btn" title="File PIREP">
                              <i class="bi bi-file-earmark-plus"></i> @lang('flights.file_pirep')
                            </a>
                          @endif

                          @if($isBid && $hasReservedAircraft)
                            <button class="lb-action-btn sb-dispatch-btn" type="button" title="SimBrief"
                              data-fltnum="{{ $flight->flight_number }}"
                              data-airline="{{ optional($flight->airline)->icao ?? '' }}"
                              data-orig="{{ $flight->dpt_airport_id }}"
                              data-dest="{{ $flight->arr_airport_id }}"
                              data-orig-name="{{ addslashes(optional($flight->dpt_airport)->name ?? '') }}"
                              data-dest-name="{{ addslashes(optional($flight->arr_airport)->name ?? '') }}"
                              data-actype="{{ $reservedAircraftIcao }}"
                              data-acreg="{{ $reservedRegistration ?? '' }}"
                              data-route="{{ addslashes($flight->route ?? '') }}">
                              <i class="bi bi-cloud-upload"></i> SimBrief
                            </button>
                          @endif

                          @if($canBid && $flight)
                            <button class="lb-action-btn {{ isset($saved[$flight->id]) ? 'btn-bid-rem save_flight' : 'btn-bid-add save_flight' }}"
                                    x-id="{{ $flight->id }}" x-saved-class="btn-bid-rem" type="button">
                              <i class="bi bi-{{ isset($saved[$flight->id]) ? 'bookmark-x' : 'bookmark-plus' }}"></i>
                              {{ isset($saved[$flight->id]) ? __('flights.remove_bid') : __('flights.add_bid') }}
                            </button>
                          @endif
                        </div>
                      </div>
                    </td>
                  </tr>

                  {{-- Row 3: collapsible details --}}
                  <tr class="lb-detail-row">
                    <td colspan="8">
                      <div class="collapse" id="asd-{{ $as->id }}">
                        <div class="lb-detail-inner">
                          @if($distance)
                            <div>
                              <div class="lb-detail-label">@lang('common.distance')</div>
                              <div class="lb-detail-val">{{ $distance }}</div>
                            </div>
                          @endif

                          @if($flight && $flight->callsign && $flight->callsign !== $flight->ident)
                            <div>
                              <div class="lb-detail-label">@lang('flights.callsign')</div>
                              <div class="lb-detail-val">{{ $flight->callsign }}</div>
                            </div>
                          @endif

                          @if(!$isCompleted && count($acTypes) && $flight)
                            <div>
                              <div class="lb-detail-label">@lang('common.equipment_options')</div>
                              <div class="lb-detail-val">
                                @foreach($acTypes as $eq)
                                  {{ $eq['icao'] }}@if($eq['name']) ({{ $eq['name'] }})@endif
                                  @if(!$loop->last) / @endif
                                @endforeach
                              </div>
                            </div>
                          @endif

                          @if($linkedPirep && $linkedPirep->landing_rate)
                            @php $lrC = $linkedPirep->landing_rate < -300 ? 'lrate-hard' : ($linkedPirep->landing_rate < -200 ? 'lrate-ok' : 'lrate-good'); @endphp
                            <div>
                              <div class="lb-detail-label">@lang('common.landing_rate')</div>
                              <div class="lb-detail-val {{ $lrC }}">{{ $linkedPirep->landing_rate }} ft/m</div>
                            </div>
                          @endif

                          @if($flight && $flight->route)
                            <div>
                              <div class="lb-detail-label">@lang('flights.route')</div>
                              <div class="lb-detail-val" style="font-family:'Courier New',monospace;font-size:0.7rem;max-width:300px;word-break:break-all;">{{ $flight->route }}</div>
                            </div>
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
      @endforeach

      @if(count($assignments) === 0)
        <div class="card vholar-logbook-wrap">
          <div class="card-body text-center text-muted py-4" style="font-size:0.85rem;">
            @lang('common.no_assignments')
          </div>
        </div>
      @endif
      </div>{{-- /col-12 assignments --}}

      {{-- Stats: order-3 mobile (last) · col-lg-4 order-lg-2 desktop (right of map) --}}
      @if($stats)
      <div class="col-12 col-lg-4 order-3 order-lg-2">
          <div class="card mb-0" style="border-radius:10px;overflow:hidden;">
            <div class="lb-month-header">
              <span class="lb-month-title">&#9654; @lang('DSpecial::common.personal_stats')</span>
            </div>
            <div class="card-body p-0">
              @foreach ($stats as $month => $stat)
                @if($month !== 'Overall')<hr class="m-0">@endif
                <table class="table table-sm table-borderless align-middle text-center mb-0">
                  @if($month !== 'Overall')
                    <thead><tr><th class="text-start fw-semibold ps-3" colspan="3"
                      style="font-size:0.72rem;letter-spacing:0.06em;color:#9898b0;text-transform:uppercase;">
                      {{ $month }}</th></tr></thead>
                  @endif
                  <thead>
                    <tr style="font-size:0.65rem;color:#9898b2;letter-spacing:0.06em;text-transform:uppercase;">
                      <th>@lang('DSpecial::common.assignments')</th>
                      <th>@lang('DSpecial::common.completed')</th>
                      <th>@lang('DSpecial::common.earnings')<sup>¹</sup></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><span class="badge lb-as-status bg-secondary">{{ $stat['total'] }}</span></td>
                      <td><span class="badge lb-as-status bg-success">{{ $stat['completed'] }}</span></td>
                      <td><span class="badge lb-as-status bg-primary">{{ $stat['earnings'] }}</span></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="px-3 pb-2">
                        <div class="progress" style="height:6px;border-radius:3px;">
                          <div class="progress-bar bg-success" style="width:{{ $stat['ratio'] }}%;"></div>
                        </div>
                        <small style="font-size:0.62rem;color:#9090a8;">{{ $stat['ratio'] }}% @lang('common.completed')</small>
                      </td>
                    </tr>
                  </tbody>
                </table>
              @endforeach
            </div>
            <div class="card-footer" style="font-size:0.65rem;color:#9090a8;">
              <sup>¹</sup> @lang('DSpecial::common.earning_note')
            </div>
          </div>
      </div>{{-- /col-lg-4 stats --}}
      @endif

      </div>{{-- /row --}}

  @if(setting('bids.block_aircraft', false))
    @include('flights.bids_aircraft')
  @endif
  @include('flights.scripts')
  @include('vholar::components.simbrief-dispatch-modal')

  @push('scripts')
  <script>
    (function() {
      var modal = document.getElementById('vholarFullMapModal');
      if (!modal) return;
      modal.addEventListener('shown.bs.modal', function() {
        if (modal._fm) { modal._fm.invalidateSize(); return; }
        var el = document.getElementById('vholarFullMap');
        if (!el || typeof L === 'undefined') return;
        var fmap = L.map('vholarFullMap').setView([4.5709, -74.2973], 4);
        L.tileLayer('{{ carto_tile_url('dark_all') }}', {
          attribution: '&copy; OSM &copy; CartoDB', subdomains: 'abcd', minZoom: 2, maxZoom: 18
        }).addTo(fmap);
        var airports = {}, routes = [], bounds = [];
        @foreach($assignments as $group => $tas)
          @foreach($tas as $as)
            @if($as->flight && $as->flight->dpt_airport && $as->flight->arr_airport)
              @php $dpt = $as->flight->dpt_airport; $arr = $as->flight->arr_airport; @endphp
              @if($dpt && $dpt->lat && $dpt->lon)
                if (!airports['{{ $dpt->id }}']) {
                  airports['{{ $dpt->id }}'] = { lat:{{ $dpt->lat }}, lon:{{ $dpt->lon }}, name:'{{ addslashes($dpt->name) }}', id:'{{ $dpt->id }}', iata:'{{ $dpt->iata ?? "" }}' };
                  bounds.push([{{ $dpt->lat }}, {{ $dpt->lon }}]);
                }
              @endif
              @if($arr && $arr->lat && $arr->lon)
                if (!airports['{{ $arr->id }}']) {
                  airports['{{ $arr->id }}'] = { lat:{{ $arr->lat }}, lon:{{ $arr->lon }}, name:'{{ addslashes($arr->name) }}', id:'{{ $arr->id }}', iata:'{{ $arr->iata ?? "" }}' };
                  bounds.push([{{ $arr->lat }}, {{ $arr->lon }}]);
                }
              @endif
              @if($dpt && $dpt->lat && $dpt->lon && $arr && $arr->lat && $arr->lon)
                routes.push({ from:'{{ $dpt->id }}', to:'{{ $arr->id }}', latlngs:[[{{ $dpt->lat }},{{ $dpt->lon }}],[{{ $arr->lat }},{{ $arr->lon }}]], ident:'{{ $as->flight->ident }}', order:{{ $as->assignment_order }} });
              @endif
            @endif
          @endforeach
        @endforeach
        for (var id in airports) {
          var apt = airports[id];
          var aptLabel = apt.iata ? apt.id + '/' + apt.iata : apt.id;
          var aptW = Math.max(80, aptLabel.length * 9 + 20);
          L.marker([apt.lat, apt.lon], {
            icon: L.divIcon({
              className: '',
              html: '<div style="width:'+aptW+'px;text-align:center;">' +
                '<span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.72rem;font-weight:800;color:#0d0b18;background:#c8d8ff;border:1.5px solid rgba(80,100,200,0.5);border-radius:4px;padding:2px 7px;box-shadow:0 2px 5px rgba(0,0,0,0.7);letter-spacing:0.04em;">&#9992; ' + aptLabel + '</span>' +
              '</div>',
              iconSize: [aptW, 22],
              iconAnchor: [aptW/2, 11]
            })
          }).bindPopup('<strong>'+aptLabel+'</strong><br>'+(apt.name||'')).addTo(fmap);
        }
        var colors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#2c3e50'];
        // First pass: count how many badges share the same midpoint
        var midCounts = {}, midIdx = {};
        routes.forEach(function(r) {
          var k = ((r.latlngs[0][0]+r.latlngs[1][0])/2).toFixed(3)+','+((r.latlngs[0][1]+r.latlngs[1][1])/2).toFixed(3);
          midCounts[k] = (midCounts[k] || 0) + 1;
        });
        // Second pass: draw lines and badges with perpendicular offset for overlaps
        routes.forEach(function(r) {
          var color = colors[(r.order-1) % colors.length];
          L.geodesic(r.latlngs, {weight:2.5, opacity:0.8, color:color, steps:15})
           .bindPopup('<strong>'+r.ident+'</strong><br>'+r.from+' → '+r.to).addTo(fmap);
          var midLat = (r.latlngs[0][0] + r.latlngs[1][0]) / 2;
          var midLon = (r.latlngs[0][1] + r.latlngs[1][1]) / 2;
          var k = midLat.toFixed(3)+','+midLon.toFixed(3);
          var idx = midIdx[k] || 0;
          midIdx[k] = idx + 1;
          var total = midCounts[k] || 1;
          // Offset perpendicular to the route direction
          var dlat = r.latlngs[1][0] - r.latlngs[0][0];
          var dlon = r.latlngs[1][1] - r.latlngs[0][1];
          var len  = Math.sqrt(dlat*dlat + dlon*dlon) || 1;
          var spread = Math.max(Math.sqrt(dlat*dlat + dlon*dlon) * 0.12, 0.25);
          var shift  = (idx - (total - 1) / 2) * spread;
          // Perpendicular unit vector (always same sign relative to midpoint — use absolute value sorting)
          var perpLat = -dlon / len;
          var perpLon =  dlat / len;
          L.marker([midLat + perpLat * shift, midLon + perpLon * shift], {
            icon: L.divIcon({
              className: '',
              html: '<div style="width:140px;text-align:center;"><span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.78rem;font-weight:700;color:#0d0b14;background:'+color+';border-radius:5px;padding:3px 10px;box-shadow:0 2px 6px rgba(0,0,0,0.7);">'+r.ident+'/'+r.order+'</span></div>',
              iconSize: [140, 24],
              iconAnchor: [70, 12]
            })
          }).addTo(fmap);
        });
        if (bounds.length) fmap.fitBounds(bounds, {padding:[40,40]});
        modal._fm = fmap;
        setTimeout(function(){ fmap.invalidateSize(); }, 100);
      });
    })();

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

@endsection
