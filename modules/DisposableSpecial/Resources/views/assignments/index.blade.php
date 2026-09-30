@extends('app')
@section('title', 'Flight Assignments')

@section('content')
  <div class="row">
    {{-- Left Column - Tarjetas de asignaciones --}}
    <div class="col-lg-8">
@if (count($assignments) > 0)
    <div class="row mb-3">
        <div class="col">
            <div class="card">
                <div class="card-header py-2 px-3">
                    <h6 class="m-0">
                        <i class="bi bi-map me-2"></i>
                        Mapa de Asignaciones
                    </h6>
                </div>
                <div class="card-body p-1">
                    <div id="assignmentsMap" style="height: 300px; width: 100%; border-radius: 8px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Cargar scripts de Leaflet directamente --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-geodesic@1.0.0/leaflet.geodesic.js"></script>
    
    <script>
    (function() {
        // Función para inicializar el mapa
        function initMap() {
            var mapContainer = document.getElementById('assignmentsMap');
            if (!mapContainer) return;
            if (window.assignmentsMapInitialized) return;
            
            if (typeof L === 'undefined') {
                setTimeout(initMap, 200);
                return;
            }
            
            window.assignmentsMapInitialized = true;
            
            // Coordenadas por defecto (Bogotá)
            var defaultCenter = [4.5709, -74.2973];
            
            // Crear el mapa
            var map = L.map('assignmentsMap').setView(defaultCenter, 5);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
                subdomains: 'abcd',
                minZoom: 2,
                maxZoom: 18
            }).addTo(map);
            
            // Colecciones
            var airports = {};
            var routes = [];
            var bounds = [];
            
            // Recorrer todas las asignaciones
            @foreach($assignments as $group => $tas)
                @foreach($tas as $as)
                    @if($as->flight && $as->flight->dpt_airport && $as->flight->arr_airport)
                        @php
                            $dpt = $as->flight->dpt_airport;
                            $arr = $as->flight->arr_airport;
                        @endphp
                        
                        @if($dpt && $dpt->lat && $dpt->lon)
                            if (!airports['{{ $dpt->id }}']) {
                                airports['{{ $dpt->id }}'] = {
                                    lat: {{ $dpt->lat }},
                                    lon: {{ $dpt->lon }},
                                    name: '{{ addslashes($dpt->name) }}',
                                    id: '{{ $dpt->id }}'
                                };
                                bounds.push([{{ $dpt->lat }}, {{ $dpt->lon }}]);
                            }
                        @endif
                        
                        @if($arr && $arr->lat && $arr->lon)
                            if (!airports['{{ $arr->id }}']) {
                                airports['{{ $arr->id }}'] = {
                                    lat: {{ $arr->lat }},
                                    lon: {{ $arr->lon }},
                                    name: '{{ addslashes($arr->name) }}',
                                    id: '{{ $arr->id }}'
                                };
                                bounds.push([{{ $arr->lat }}, {{ $arr->lon }}]);
                            }
                        @endif
                        
                        @if($dpt && $dpt->lat && $dpt->lon && $arr && $arr->lat && $arr->lon)
                            routes.push({
                                from: '{{ $dpt->id }}',
                                to: '{{ $arr->id }}',
                                latlngs: [[{{ $dpt->lat }}, {{ $dpt->lon }}], [{{ $arr->lat }}, {{ $arr->lon }}]],
                                ident: '{{ $as->flight->ident }}',
                                order: {{ $as->assignment_order }}
                            });
                        @endif
                    @endif
                @endforeach
            @endforeach
            
            // Añadir marcadores de aeropuertos
            for (var id in airports) {
                var apt = airports[id];
                var popupContent = '<strong>' + apt.id + '</strong><br>' + (apt.name || 'Aeropuerto');
                L.marker([apt.lat, apt.lon]).bindPopup(popupContent).addTo(map);
            }
            
            // Añadir rutas
            var colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#2c3e50'];
            routes.forEach(function(route) {
                var color = colors[(route.order - 1) % colors.length];
                L.geodesic(route.latlngs, {
                    weight: 3,
                    opacity: 0.7,
                    color: color,
                    steps: 10
                }).bindPopup('<strong>' + route.ident + '</strong><br>' + route.from + ' → ' + route.to)
                 .addTo(map);
            });
            
            // Ajustar el mapa
            if (bounds.length > 0) {
                map.fitBounds(bounds);
            }
            
            // Forzar actualización del tamaño
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
        }
        
        // Esperar a que el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initMap, 300);
            });
        } else {
            setTimeout(initMap, 300);
        }
    })();
    </script>
@endif

      @foreach ($assignments as $group => $tas)
        <div class="card mb-4">
          <div class="card-header py-2 px-3"
            style="background: linear-gradient(135deg, #412c4d20 0%, #2a263320 100%); border-bottom: 1px solid rgba(255,255,255,0.05);">
            <div class="d-flex justify-content-between align-items-center">
              @php
                $firstAssignment = $tas->first();
                $year = $firstAssignment ? $firstAssignment->assignment_year : date('Y');
              @endphp
              <h5 class="m-0">
                <i class="bi bi-calendar-week me-2"></i>
                {{ Carbon::createFromDate($year, $group, 1)->format('F Y') }}
              </h5>
              <span class="badge bg-primary">
                {{ $tas->count() }} {{ Str::plural('flight', $tas->count()) }}
              </span>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="flights-list">
              @foreach ($tas->sortBy('assignment_order', SORT_NATURAL) as $as)
                @php
                  $flight = $as->flight;
                  $isCompleted = $as->completed;
                  $pirepStatus = $as->pirep_status;
                  $isCurrentMonth = $as->assignment_month == $curr_month;
                  $isBid = isset($saved[$flight->id]) ? true : false;
                  $canBid = ($flight && !$isCompleted && $pirepStatus !== 'in_progress' && 
                    $isCurrentMonth && (!setting('pilots.only_flights_from_current') || 
                    $flight->dpt_airport_id == optional($user->current_airport)->icao));

                  $flight = $as->flight;
                  $isCompleted = $as->completed;
                  $isCurrentMonth = $as->assignment_month == $curr_month;
                  $isBid = isset($saved[$flight->id]) ? true : false;
                  
                  $canBid = ($flight && !$isCompleted && $pirepStatus !== 'in_progress' && 
                  $isCurrentMonth && (!setting('pilots.only_flights_from_current') || 
                  $flight->dpt_airport_id == optional($user->current_airport)->icao));

                  // Duración en formato legible
                  $duration = '--';
                  if ($flight && $flight->flight_time) {
                      $minutes = (float) $flight->flight_time;
                      $duration = floor($minutes / 60) . 'h ' . $minutes % 60 . 'm';
                  }

                  // Distancia - CORREGIDO
                  $distanceValue = null;
                  if ($flight && $flight->distance && property_exists($flight->distance, 'internalUnit')) {
                      $distanceValue = (float) $flight->distance->internalUnit;
                  }

                  // Estado de la asignación
                  if ($pirepStatus === 'in_progress') {
                      $statusText = 'In Progress';
                      $statusColor = '#28a745';
                      $statusIcon = 'bi-arrow-repeat';
                  } elseif ($isCompleted) {
                      $statusText = 'Completed';
                      $statusColor = '#28a745';
                      $statusIcon = 'bi-check-circle-fill';
                  } else {
                      $statusText = 'Pending';
                      $statusColor = '#ffc107';
                      $statusIcon = 'bi-hourglass-split';
                  }

                  // Verificar aeronave reservada
                  $hasReservedAircraft = false;
                  $reservedRegistration = null;
                  $reservedSubfleetName = null;

                  if ($isBid && isset($saved[$flight->id])) {
                      $bid = App\Models\Bid::find($saved[$flight->id]);
                      if ($bid && $bid->aircraft_id) {
                          $aircraft = App\Models\Aircraft::find($bid->aircraft_id);
                          if ($aircraft) {
                              $hasReservedAircraft = true;
                              $reservedRegistration = $aircraft->registration;
                              $reservedSubfleetName = $aircraft->subfleet->name ?? ($aircraft->name ?? $aircraft->icao);
                          }
                      }
                  }
                @endphp

                <div class="flight-card" data-flight-id="{{ $flight->id ?? 0 }}"
                  data-assignment-order="{{ $as->assignment_order }}">
                  {{-- Cabecera con número de orden y estado --}}
                  <div class="flight-header">
                    <div class="flight-info">
                      <span class="flight-callsign">
                        <span class="badge bg-secondary me-2" style="background: #412c4d !important;">
                          #{{ $as->assignment_order }}
                        </span>
                        {{ $flight ? $flight->ident : 'Flight not found' }}
                      </span>
                      @if ($flight)
                        <span
                          class="flight-airline">{{ optional($flight->airline)->name ?? (optional($flight->airline)->code ?? 'N/A') }}</span>
                      @endif
                    </div>
                    <div class="flight-status" style="background: {{ $statusColor }}20; color: {{ $statusColor }}">
                      <i class="bi {{ $statusIcon }} me-1"></i>
                      {{ $statusText }}
                    </div>
                  </div>

                  @if ($flight)
                    {{-- Ruta principal con ICAO + IATA --}}
                    <div class="flight-route">
                      <div class="route-point">
                        <a href="{{ route('frontend.airports.show', [$flight->dpt_airport_id]) }}" class="point-code">
                          {{ $flight->dpt_airport_id }}
                          @if ($flight->dpt_airport && $flight->dpt_airport->iata)
                            <span class="point-iata">({{ $flight->dpt_airport->iata }})</span>
                          @endif
                        </a>
                        <div class="point-name">{{ $flight->dpt_airport->name ?? 'Departure' }}
                        </div>
                      </div>

                      <div class="route-arrow">
                        <i class="bi bi-arrow-right"></i>
                      </div>

                      <div class="route-point">
                        <a href="{{ route('frontend.airports.show', [$flight->arr_airport_id]) }}" class="point-code">
                          {{ $flight->arr_airport_id }}
                          @if ($flight->arr_airport && $flight->arr_airport->iata)
                            <span class="point-iata">({{ $flight->arr_airport->iata }})</span>
                          @endif
                        </a>
                        <div class="point-name">{{ $flight->arr_airport->name ?? 'Arrival' }}</div>
                      </div>
                    </div>

                    {{-- Grid de información --}}
                    <div class="flight-details-grid">
                      <div class="detail-row">
                        <div class="detail-item">
                          <div class="detail-label">DURATION</div>
                          <div class="detail-value">{{ $duration }}</div>
                        </div>
                        <div class="detail-item">
                          <div class="detail-label">DISTANCE</div>
                          <div class="detail-value">
                            @if ($flight && $flight->distance && $flight->distance->internal() > 0)
                              {{ number_format($flight->distance->internal(), 0) }} nm
                            @else
                              --
                            @endif
                          </div>
                        </div>
                        <div class="detail-item">
                          <div class="detail-label">SCHEDULED</div>
                          <div class="detail-value">
                            @if ($flight->dpt_time)
                              {{ \Carbon\Carbon::parse($flight->dpt_time)->format('H:i') }} →
                              {{ \Carbon\Carbon::parse($flight->arr_time)->format('H:i') }}
                            @else
                              --
                            @endif
                          </div>
                        </div>
                      </div>

                      <div class="detail-row">
                        <div class="detail-item">
                          <div class="detail-label">EQUIPMENT</div>
                          <div class="detail-value">
                            @if ($hasReservedAircraft)
                              <span class="text-success">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                {{ $reservedRegistration }} ({{ $reservedSubfleetName }})
                              </span>
                            @elseif($isBid && !$hasReservedAircraft)
                              <span class="text-warning">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                N/A (No aircraft selected)
                              </span>
                            @else
                              @php
                                $equipmentList = [];
                                foreach ($flight->subfleets as $index => $sf) {
                                    if ($index < 4) {
                                        $equipmentList[] = $sf->icao ?? $sf->type;
                                    }
                                }
                                $equipmentDisplay = implode('/', $equipmentList);
                                if (count($flight->subfleets) > 4) {
                                    $equipmentDisplay .= '/...';
                                }
                                echo $equipmentDisplay ?: 'N/A';
                              @endphp
                            @endif
                          </div>
                        </div>
                        <div class="detail-item">
                          <div class="detail-label">CALLSIGN</div>
                          <div class="detail-value">{{ $flight->callsign ?: $flight->ident }}
                          </div>
                        </div>
                        <div class="detail-item">
                          <div class="detail-label">ASSIGNMENT</div>
                          <div class="detail-value">
                            @if ($isCompleted)
                              <span class="text-success">
                                <i class="bi bi-check-circle-fill me-1"></i> Completed
                              </span>
                            @elseif($isCurrentMonth)
                              <span class="text-warning">
                                <i class="bi bi-hourglass-split me-1"></i> Active
                              </span>
                            @else
                              <span class="text-muted">
                                <i class="bi bi-calendar me-1"></i> Past month
                              </span>
                            @endif
                          </div>
                        </div>
                      </div>
                    </div>

                    {{-- Footer con acciones --}}
                    <div class="flight-actions">
                      <div class="d-flex justify-content-between align-items-center w-100">
                        <div class="d-flex flex-wrap gap-2">
                          <a href="{{ route('frontend.flights.show', [$flight->id]) }}" class="action-link">
                            <i class="bi bi-eye"></i> Details
                          </a>

                          @if ($isCompleted && $as->pirep_id)
                            <a href="{{ route('frontend.pireps.show', [$as->pirep_id]) }}" class="action-link">
                              <i class="bi bi-file-text"></i> View PIREP
                            </a>
                          @elseif(!$isCompleted && $isCurrentMonth)
                            <a href="{{ route('frontend.pireps.create') }}?flight_id={{ $flight->id }}"
                              class="action-link">
                              <i class="bi bi-file-text"></i> File PIREP
                            </a>
                          @endif
                        </div>

                        @if ($canBid)
                          <button
                            class="btn btn-sm m-0 mx-1 p-0 px-1 save_flight {{ isset($saved[$flight->id]) ? 'btn-danger' : 'btn-success' }}"
                            x-id="{{ $flight->id }}" x-saved-class="btn-danger" type="button">
                            {{ isset($saved[$flight->id]) ? 'Remove Bid' : 'Add Bid' }}
                          </button>
                        @endif
                      </div>
                    </div>
                  @else
                    <div class="flight-route p-3 text-center text-muted">
                      <i class="bi bi-exclamation-triangle me-2"></i>
                      Flight information not available (flight may have been removed)
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Right Column - Personal Stats --}}
    <div class="col-lg-4">
      @if (count($assignments) > 0 && $dbasic === true)
        <div class="row mb-3">
          <div class="col">
            @widget('DBasic::Map', ['source' => 'assignment'])
          </div>
        </div>
      @endif

      @if ($stats)
        <div class="card mb-3">
          <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #412c4d20 0%, #2a263320 100%);">
            <h5 class="m-0">
              <i class="bi bi-graph-up me-2"></i>
              @lang('DSpecial::common.personal_stats')
            </h5>
          </div>
          <div class="card-body p-0">
            @foreach ($stats as $month => $stat)
              @if ($month === 'Overall')
                <table class="table table-sm table-borderless align-middle text-center mb-0">
                  <thead>
                    <tr>
                      <th class="fw-semibold">@lang('DSpecial::common.assignments')</th>
                      <th class="fw-semibold">@lang('DSpecial::common.completed')</th>
                      <th class="fw-semibold">@lang('DSpecial::common.earnings')<span class="small">¹</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><span class="badge bg-secondary">{{ $stat['total'] }}</span></td>
                      <td><span class="badge bg-success">{{ $stat['completed'] }}</span></td>
                      <td><span class="badge bg-primary">{{ $stat['earnings'] }}</span></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="pt-2">
                        <div class="progress" style="height: 10px;">
                          <div class="progress-bar bg-success" role="progressbar"
                            style="width: {{ $stat['ratio'] }}%;" aria-valuenow="{{ $stat['ratio'] }}"
                            aria-valuemin="0" aria-valuemax="100">
                          </div>
                        </div>
                        <small class="text-muted">{{ $stat['ratio'] }}% completed</small>
                      </td>
                    </tr>
                  </tbody>
                </table>
              @elseif($month != 'Overall')
                <hr class="m-0">
                <table class="table table-sm table-borderless align-middle text-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-start fw-semibold" colspan="3">{{ $month }}</th>
                    </tr>
                    <tr>
                      <th class="fw-semibold">@lang('DSpecial::common.assignments')</th>
                      <th class="fw-semibold">@lang('DSpecial::common.completed')</th>
                      <th class="fw-semibold">@lang('DSpecial::common.earnings')<span class="small">¹</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><span class="badge bg-secondary">{{ $stat['total'] }}</span></td>
                      <td><span class="badge bg-success">{{ $stat['completed'] }}</span></td>
                      <td><span class="badge bg-primary">{{ $stat['earnings'] }}</span></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="pt-2">
                        <div class="progress" style="height: 10px;">
                          <div class="progress-bar bg-success" role="progressbar"
                            style="width: {{ $stat['ratio'] }}%;" aria-valuenow="{{ $stat['ratio'] }}"
                            aria-valuemin="0" aria-valuemax="100">
                          </div>
                        </div>
                        <small class="text-muted">{{ $stat['ratio'] }}% completed</small>
                      </td>
                    </tr>
                  </tbody>
                </table>
              @endif
            @endforeach
          </div>
          <div class="card-footer small text-muted">
            <b>¹</b> @lang('DSpecial::common.earning_note')
          </div>
        </div>
      @endif


    </div>
  </div>

  {{-- Incluir el modal de selección de aeronave si está configurado --}}
  @if (setting('bids.block_aircraft', false))
    @include('flights.bids_aircraft')
  @endif

  {{-- Scripts de bids (el mismo que usa /flights) --}}
  @include('flights.scripts')
@endsection
