<div class="flights-container">
    {{-- Mapa de rutas disponibles --}}
    <div class="flights-map-container mb-4">
        <div id="availableRoutesMap" style="height: 450px; width: 100%; border-radius: 12px; overflow: hidden; background: var(--vh-surface);"></div>
    </div>
    @php
        $isBidsPage = request()->routeIs('frontend.flights.bids');
    @endphp
    @if($flights->count() > 0)
        <div class="flights-stats mb-3">
            <div class="stats-badge">
                <i class="bi bi-airplane-fill"></i>
                <span>
                    @if($isBidsPage)
                        {{ trans_choice('flights.bids_active', $flights->count(), ['count' => $flights->count()]) }}
                    @else
                        {{ trans('flights.flights_found', ['count' => $flights->total() ?? $flights->count()]) }}
                    @endif
                </span>
            </div>
        </div>
    @endif

    <div class="flights-list">
        @foreach ($flights as $flight)
@php
    $isBid = isset($saved[$flight->id]);
    
    // Obtener la matrícula y tipo del avión reservado (si existe)
    $reservedAircraftRegistration = null;
    $reservedAircraftType = null;
    $reservedAircraftIcao = '';
    $hasReservedAircraft = false;
    
    if ($isBid && isset($saved[$flight->id])) {
        $bid = App\Models\Bid::find($saved[$flight->id]);
        if ($bid && $bid->aircraft_id) {
            $aircraft = App\Models\Aircraft::find($bid->aircraft_id);
            if ($aircraft) {
                if ($aircraft->registration) {
                    $reservedAircraftRegistration = $aircraft->registration;
                }
                if ($aircraft->name || $aircraft->icao) {
                    $reservedAircraftType = $aircraft->name ?? $aircraft->icao;
                }
                $reservedAircraftIcao = $aircraft->icao ?? '';
                $hasReservedAircraft = true;
            }
        }
    }
    
    // Procesar subfleets para EQUIPMENT (máx 4)
    $equipmentList = [];
    $equipmentCount = count($flight->subfleets);
    
    // Si hay reserva CON avión específico, mostrar el tipo de ese avión
    if ($hasReservedAircraft && $reservedAircraftType) {
        $equipmentDisplay = $reservedAircraftType;
    } else {
        foreach ($flight->subfleets as $index => $sf) {
            if ($index < 4) {
                $equipmentList[] = $sf->icao ?? $sf->type;
            }
        }
        $equipmentDisplay = implode('/', $equipmentList);
        if ($equipmentCount > 4) {
            $equipmentDisplay .= '/...';
        }
        $equipmentDisplay = $equipmentDisplay ?: 'N/A';
    }
    
    // Procesar subfleets para AIRCRAFT (máx 3)
    $aircraftList = [];
    $aircraftCount = count($flight->subfleets);
    
    // Si hay reserva CON avión específico, mostrar la matrícula
    if ($hasReservedAircraft && $reservedAircraftRegistration) {
        $finalAircraftDisplay = $reservedAircraftRegistration;
    } elseif ($isBid && !$hasReservedAircraft) {
        $finalAircraftDisplay = 'N/A';
    } else {
        foreach ($flight->subfleets as $index => $sf) {
            if ($index < 3) {
                $aircraftList[] = $sf->type;
            }
        }
        $finalAircraftDisplay = implode('/', $aircraftList);
        if ($aircraftCount > 3) {
            $finalAircraftDisplay .= '/...';
        }
        $finalAircraftDisplay = $finalAircraftDisplay ?: 'N/A';
    }
    
    // Duración en formato legible
    $duration = '--';
    if ($flight->flight_time) {
        if (is_object($flight->flight_time) && method_exists($flight->flight_time, 'internalUnit')) {
            $minutes = (float) $flight->flight_time->internalUnit;
        } else {
            $minutes = (float) $flight->flight_time;
        }
        $duration = floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm';
    }
    
    // Estado del vuelo
    $status = __('flights.scheduled');
    // Va como literal: se concatena el alfa en linea ('{{ $statusColor }}20').
    $statusColor = '#8FA6D9';
@endphp
            
            <div class="flight-card" data-flight-id="{{ $flight->id }}">
                {{-- Cabecera con flight y airline --}}
                <div class="flight-header">
                    <div class="flight-info">
                        <span class="flight-callsign">{{ $flight->ident }}</span>
                        <span class="flight-airline">{{ $flight->airline->name ?? $flight->airline->code ?? 'N/A' }}</span>
                    </div>
                    <div class="flight-status" style="background: {{ $statusColor }}20; color: {{ $statusColor }}">
                        {{ $status }}
                    </div>
                </div>
                
                {{-- Ruta principal con ICAO + IATA --}}
                <div class="flight-route">
                    <div class="route-point">
                        <div style="display:flex; align-items:center; gap:6px;">
                            @if($flight->dpt_airport && $flight->dpt_airport->country)
                                <span class="fi fi-{{ strtolower($flight->dpt_airport->country) }}" style="font-size:1.2em; flex-shrink:0;"></span>
                            @endif
                            <div>
                                <a href="{{ route('frontend.airports.show', [$flight->dpt_airport_id]) }}" class="point-code">
                                    {{ $flight->dpt_airport_id }}
                                    @if($flight->dpt_airport && $flight->dpt_airport->iata)
                                        <span class="point-iata">({{ $flight->dpt_airport->iata }})</span>
                                    @endif
                                </a>
                                <div class="point-name">{{ $flight->dpt_airport->name ?? __('common.departure') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="route-arrow">
                        <i class="bi bi-arrow-right"></i>
                    </div>

                    <div class="route-point">
                        <div style="display:inline-flex; align-items:center; gap:6px; text-align:left;">
                            @if($flight->arr_airport && $flight->arr_airport->country)
                                <span class="fi fi-{{ strtolower($flight->arr_airport->country) }}" style="font-size:1.2em; flex-shrink:0;"></span>
                            @endif
                            <div>
                                <a href="{{ route('frontend.airports.show', [$flight->arr_airport_id]) }}" class="point-code">
                                    {{ $flight->arr_airport_id }}
                                    @if($flight->arr_airport && $flight->arr_airport->iata)
                                        <span class="point-iata">({{ $flight->arr_airport->iata }})</span>
                                    @endif
                                </a>
                                <div class="point-name">{{ $flight->arr_airport->name ?? __('common.arrival') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Grid de información estilo FR24 --}}
                <div class="flight-details-grid">
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.scheduled_dep')</div>
                            <div class="detail-value">
                                {{ $flight->dpt_time ? \Carbon\Carbon::parse($flight->dpt_time)->format('g:i A') : '--:--' }}
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.actual_dep')</div>
                            <div class="detail-value">--:--</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.scheduled_arr')</div>
                            <div class="detail-value">
                                {{ $flight->arr_time ? \Carbon\Carbon::parse($flight->arr_time)->format('g:i A') : '--:--' }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">@lang('common.status')</div>
                            <div class="detail-value status-value">
                                {{ $status }}
                                @if($flight->dpt_time)
                                    <span class="status-time">({{ __('flights.estimated_dep') }} {{ \Carbon\Carbon::parse($flight->dpt_time)->format('g:i A') }})</span>
                                @endif
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.equipment')</div>
                            <div class="detail-value">{{ $equipmentDisplay }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.callsign')</div>
                            <div class="detail-value">{{ $flight->callsign ?: $flight->ident }}</div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">{{ trans_choice('common.flight', 1) }}</div>
                            <div class="detail-value">{{ $flight->ident }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('common.airline')</div>
                            <div class="detail-value">{{ $flight->airline->name ?? $flight->airline->code ?? 'N/A' }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('common.aircraft')</div>
                            <div class="detail-value">
                                @if($isBid && $hasReservedAircraft)
                                    <span class="text-success">
                                        <i class="bi bi-check-circle-fill"></i> 
                                        {{ $finalAircraftDisplay }}
                                    </span>
                                @else
                                    {{ $finalAircraftDisplay }}
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.category')</div>
                            <div class="detail-value">@lang('flights.passenger')</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">@lang('flights.duration')</div>
                            <div class="detail-value">{{ $duration }}</div>
                        </div>

<div class="detail-item">
    <div class="detail-label">@lang('common.distance')</div>
    <div class="detail-value">
        @php
            $distanceValue = 0;
            if ($flight->distance) {
                if (is_numeric($flight->distance)) {
                    $distanceValue = (float) $flight->distance;
                } elseif (is_object($flight->distance) && method_exists($flight->distance, 'getValue')) {
                    $distanceValue = (float) $flight->distance->getValue();
                } elseif (is_object($flight->distance) && method_exists($flight->distance, 'internalUnit')) {
                    $distanceValue = (float) $flight->distance->internalUnit;
                } elseif (is_object($flight->distance) && property_exists($flight->distance, 'value')) {
                    $distanceValue = (float) $flight->distance->value;
                } else {
                    // Intentar convertir a string y luego a float
                    $distanceValue = (float) (string) $flight->distance;
                }
            }
        @endphp
        @if($distanceValue > 0)
            {{ number_format($distanceValue) }} nm
        @else
            --
        @endif
    </div>
</div>

                    </div>
                </div>
                
                {{-- Footer con acciones --}}
                <div class="flight-actions">
                    <a href="{{ route('frontend.flights.show', [$flight->id]) }}" class="action-link">
                        <i class="bi bi-eye"></i> @lang('flights.details')
                    </a>

                    <a href="{{ route('frontend.pireps.create') }}?flight_id={{ $flight->id }}" class="action-link">
                        <i class="bi bi-file-text"></i> @lang('flights.file_pirep')
                    </a>
                    
                    @if ($simbrief !== false)
                        @if ($flight->simbrief && $flight->simbrief->user_id === $user->id)
                            <a href="{{ route('frontend.simbrief.briefing', $flight->simbrief->id) }}" class="action-link">
                                <i class="bi bi-file-pdf"></i> Briefing
                            </a>
                        @else
                            @if ($simbrief_bids === false || ($simbrief_bids === true && isset($saved[$flight->id])))
                                @php
                                    $aircraft_id = isset($saved[$flight->id]) 
                                        ? App\Models\Bid::find($saved[$flight->id])->aircraft_id 
                                        : null;
                                @endphp
                                <a href="{{ route('frontend.simbrief.generate') }}?flight_id={{ $flight->id }}@if($aircraft_id)&aircraft_id={{ $aircraft_id}}@endif" 
                                   class="action-link">
                                    <i class="bi bi-cloud-upload"></i> SimBrief
                                </a>
                            @endif
                        @endif
                    @endif
                    
                    @if ($acars_plugin)
                        @if (isset($saved[$flight->id]))
                            <a href="vmsacars:bid/{{ $saved[$flight->id] }}" class="action-link">
                                <i class="bi bi-radio"></i> vmsACARS
                            </a>
                        @else
                            <a href="vmsacars:flight/{{ $flight->id }}" class="action-link">
                                <i class="bi bi-radio"></i> vmsACARS
                            </a>
                        @endif
                    @endif
                    
                    @if ($isBid && $hasReservedAircraft)
                        <button class="action-link sb-dispatch-btn" type="button"
                            data-fltnum="{{ $flight->flight_number }}"
                            data-airline="{{ optional($flight->airline)->icao ?? '' }}"
                            data-orig="{{ $flight->dpt_airport_id }}"
                            data-dest="{{ $flight->arr_airport_id }}"
                            data-orig-name="{{ addslashes(optional($flight->dpt_airport)->name ?? '') }}"
                            data-dest-name="{{ addslashes(optional($flight->arr_airport)->name ?? '') }}"
                            data-actype="{{ $reservedAircraftIcao }}"
                            data-acreg="{{ $reservedAircraftRegistration ?? '' }}"
                            data-route="{{ addslashes($flight->route ?? '') }}">
                            <i class="bi bi-cloud-upload"></i> SimBrief
                        </button>
                    @endif

                    @if (!setting('pilots.only_flights_from_current') || $flight->dpt_airport_id == $user->current_airport->icao)
                        <button class="bid-button {{ $isBid ? 'bid-remove' : 'bid-add' }} save_flight"
                                x-id="{{ $flight->id }}" 
                                data-bid-id="{{ $isBid ? $saved[$flight->id] : '' }}"
                                x-saved-class="btn-danger" 
                                type="button">
                            <i class="bi {{ $isBid ? 'bi-dash-circle' : 'bi-plus-circle' }}"></i>
                            {{ $isBid ? __('flights.remove_bid') : __('flights.add_bid') }}
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    
{{-- Script para Remove bid con modal Vholar --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const apiKey = document.querySelector('meta[name="api-key"]').getAttribute('content');
    
    // Headers para peticiones API
    const baseHeaders = {
        'Accept': 'application/json, text/plain, */*',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
        'x-api-key': apiKey,
        'x-csrf-token': token
    };
    
    let currentBidId = null;
    let currentCard = null;
    
    // Obtener el modal
    const modalElement = document.getElementById('confirmRemoveModal');
    let confirmModal;
    
    if (modalElement) {
        // Usar la instancia existente o crear una nueva
        confirmModal = modalElement.__modal || new bootstrap.Modal(modalElement);
    }
    
    const confirmBtn = document.getElementById('confirmRemoveBtn');
    
    // ==================== REMOVE BID CON MODAL PERSONALIZADO ====================
    document.querySelectorAll('.bid-remove.save_flight').forEach(function(btn) {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const bidId = this.getAttribute('data-bid-id');
            const card = this.closest('.flight-card');
            
            if (!bidId) {
                Toast.error('Error: No se encontró el ID de la reserva');
                return;
            }
            
            // Obtener información del vuelo para mostrar en el modal
            const flightIdent = card.querySelector('.flight-callsign')?.innerText || 'Vuelo';
            const dptAirport = card.querySelector('.route-point:first-child .point-code')?.innerText?.split('(')[0]?.trim() || '---';
            const arrAirport = card.querySelector('.route-point:last-child .point-code')?.innerText?.split('(')[0]?.trim() || '---';
            
            // Actualizar el modal con la información del vuelo
            const detailsDiv = document.getElementById('confirmFlightDetails');
            if (detailsDiv) {
                detailsDiv.innerHTML = `
                    <div class="flight-ident">${flightIdent}</div>
                    <div class="flight-route">${dptAirport} → ${arrAirport}</div>
                `;
            }
            
            // Guardar datos para la confirmación
            currentBidId = bidId;
            currentCard = card;
            
            // Mostrar modal
            if (confirmModal) {
                confirmModal.show();
            }
        });
    });
    
    // Manejar confirmación de eliminación
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!currentBidId) return;
            
            // Cerrar modal inmediatamente
            if (confirmModal) {
                confirmModal.hide();
            }
            
            // Mostrar loading en el botón original
            const removeBtn = document.querySelector(`.bid-remove.save_flight[data-bid-id="${currentBidId}"]`);
            if (removeBtn) {
                removeBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> {{ __("flights.removing") }}';
                removeBtn.disabled = true;
            }
            
            // Mostrar toast de cargando
            Toast.info('Cancelando reserva...', 'Procesando');
            
            fetch('/api/user/bids', {
                method: 'DELETE',
                headers: baseHeaders,
                body: JSON.stringify({ bid_id: currentBidId })
            })
            .then(response => {
                if (response.status === 200) {
                    return response.json();
                } else {
                    throw new Error(`Error ${response.status}`);
                }
            })
            .then(data => {
                Toast.success('Reserva cancelada exitosamente');
                setTimeout(() => {
                    location.reload();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                Toast.error(error.message || 'Error al cancelar la reserva');
                
                if (removeBtn) {
                    removeBtn.innerHTML = '<i class="bi bi-dash-circle"></i> {{ __("flights.remove_bid") }}';
                    removeBtn.disabled = false;
                }
            });
            
            currentBidId = null;
            currentCard = null;
        });
    }
    
    // Limpiar variables cuando se cierra el modal
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', function() {
            currentBidId = null;
            currentCard = null;
        });
    }
});
</script>
@endpush

    {{-- Script para el mapa de rutas disponibles --}}
    <script>
    (function() {
        // Función para inicializar el mapa de rutas disponibles
        function initAvailableRoutesMap() {
            var mapContainer = document.getElementById('availableRoutesMap');
            if (!mapContainer) return;
            if (window.availableRoutesMapInitialized) return;
            
            if (typeof L === 'undefined') {
                setTimeout(initAvailableRoutesMap, 200);
                return;
            }
            
            window.availableRoutesMapInitialized = true;
            
            // Coordenadas por defecto (Bogotá)
            var defaultCenter = [4.5709, -74.2973];
            
            // Crear el mapa
            var map = L.map('availableRoutesMap').setView(defaultCenter, 5);
            L.tileLayer('{{ carto_tile_url('light_all') }}', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
                subdomains: 'abcd',
                minZoom: 2,
                maxZoom: 18
            }).addTo(map);
            
            // Colecciones
            var airports = {};
            var routes = [];
            var bounds = [];
            
            // Aeropuerto base del piloto
            var userAirport = null;
            
            @auth
                @if($user->current_airport)
                    userAirport = {
                        id: '{{ $user->current_airport->icao }}',
                        lat: {{ $user->current_airport->lat ?? 0 }},
                        lon: {{ $user->current_airport->lon ?? 0 }},
                        name: '{{ addslashes($user->current_airport->name) }}'
                    };
                    if (userAirport.lat && userAirport.lon) {
                        airports[userAirport.id] = userAirport;
                        bounds.push([userAirport.lat, userAirport.lon]);
                    }
                @endif
            @endauth
            
            // Recorrer todos los vuelos disponibles
            @foreach($flights as $flight)
                @if($flight->dpt_airport && $flight->arr_airport)
                    @php
                        $dpt = $flight->dpt_airport;
                        $arr = $flight->arr_airport;
                    @endphp
                    
                    @if($dpt && $dpt->lat && $dpt->lon)
                        if (!airports['{{ $dpt->id }}']) {
                            airports['{{ $dpt->id }}'] = {
                                lat: {{ $dpt->lat }},
                                lon: {{ $dpt->lon }},
                                name: '{{ addslashes($dpt->name) }}',
                                id: '{{ $dpt->id }}',
                                iata: '{{ $dpt->iata ?? '' }}'
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
                                id: '{{ $arr->id }}',
                                iata: '{{ $arr->iata ?? '' }}'
                            };
                            bounds.push([{{ $arr->lat }}, {{ $arr->lon }}]);
                        }
                    @endif
                    
                    @if($dpt && $dpt->lat && $dpt->lon && $arr && $arr->lat && $arr->lon)
                        @php
                            $isUserAirport = ($user->current_airport && $dpt->id == $user->current_airport->icao);
                        @endphp
                        routes.push({
                            from: '{{ $dpt->id }}',
                            to: '{{ $arr->id }}',
                            latlngs: [[{{ $dpt->lat }}, {{ $dpt->lon }}], [{{ $arr->lat }}, {{ $arr->lon }}]],
                            ident: '{{ $flight->ident }}',
                            isUserAirport: {{ $isUserAirport ? 'true' : 'false' }}
                        });
                    @endif
                @endif
            @endforeach
            
            // Añadir marcadores de aeropuertos
            for (var id in airports) {
                var apt = airports[id];
                var aptLabel = apt.iata ? apt.id + '/' + apt.iata : apt.id;
                var aptW = Math.max(72, aptLabel.length * 8 + 20);
                var isBase = userAirport && apt.id === userAirport.id;
                var badgeBg     = isBase ? '#f1c40f' : '#BDBFC1';
                var badgeBorder = isBase ? 'rgba(180,140,0,0.5)' : 'rgba(13,11,24,0.35)';
                var symbol      = isBase ? '&#11088; ' : '&#9992; ';
                var popupExtra  = isBase ? '<br><span>&#11088; Tu base actual</span>' : '';
                L.marker([apt.lat, apt.lon], {
                    icon: L.divIcon({
                        className: '',
                        html: '<div style="width:'+aptW+'px;text-align:center;">' +
                            '<span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.62rem;font-weight:800;color:#0d0b18;background:'+badgeBg+';border:1.5px solid '+badgeBorder+';border-radius:4px;padding:1px 5px;box-shadow:0 2px 4px rgba(0,0,0,0.6);letter-spacing:0.03em;">'+symbol+aptLabel+'</span>' +
                        '</div>',
                        iconSize: [aptW, 20],
                        iconAnchor: [aptW/2, 10]
                    })
                }).bindPopup('<strong>'+aptLabel+'</strong><br>'+(apt.name||'Aeropuerto')+popupExtra).addTo(map);
            }

            // Midpoint overlap prevention
            var midCounts = {}, midIdx = {};
            routes.forEach(function(r) {
                var k = ((r.latlngs[0][0]+r.latlngs[1][0])/2).toFixed(3)+','+((r.latlngs[0][1]+r.latlngs[1][1])/2).toFixed(3);
                midCounts[k] = (midCounts[k] || 0) + 1;
            });

            // Dibujar rutas con badge de vuelo
            routes.forEach(function(route) {
                var color   = route.isUserAirport ? '#4CAF76' : '#8FA6D9';
                var weight  = route.isUserAirport ? 3.5 : 2;
                var opacity = route.isUserAirport ? 0.9 : 0.55;
                L.geodesic(route.latlngs, {
                    weight: weight,
                    opacity: opacity,
                    color: color,
                    steps: 10
                }).bindPopup('<strong>'+route.ident+'</strong><br>'+route.from+' → '+route.to).addTo(map);
                var midLat = (route.latlngs[0][0] + route.latlngs[1][0]) / 2;
                var midLon = (route.latlngs[0][1] + route.latlngs[1][1]) / 2;
                var k = midLat.toFixed(3)+','+midLon.toFixed(3);
                var idx = midIdx[k] || 0;
                midIdx[k] = idx + 1;
                var total = midCounts[k] || 1;
                var dlat = route.latlngs[1][0] - route.latlngs[0][0];
                var dlon = route.latlngs[1][1] - route.latlngs[0][1];
                var len  = Math.sqrt(dlat*dlat + dlon*dlon) || 1;
                var spread = Math.max(len * 0.12, 0.25);
                var shift  = (idx - (total - 1) / 2) * spread;
                L.marker([midLat + (-dlon/len)*shift, midLon + (dlat/len)*shift], {
                    icon: L.divIcon({
                        className: '',
                        html: '<div style="width:120px;text-align:center;"><span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;font-size:0.65rem;font-weight:700;color:#0d0b14;background:'+color+';border-radius:4px;padding:2px 8px;box-shadow:0 2px 5px rgba(0,0,0,0.65);">'+route.ident+'</span></div>',
                        iconSize: [120, 22],
                        iconAnchor: [60, 11]
                    })
                }).addTo(map);
            });
            
            // Ajustar el mapa
            if (bounds.length > 0) {
                map.fitBounds(bounds);
            } else if (userAirport && userAirport.lat && userAirport.lon) {
                map.setView([userAirport.lat, userAirport.lon], 8);
            }
            
            // Forzar actualización del tamaño múltiples veces
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
            
            setTimeout(function() {
                map.invalidateSize();
            }, 500);
            
            // Escuchar cambios de tamaño de ventana
            window.addEventListener('resize', function() {
                setTimeout(function() {
                    map.invalidateSize();
                }, 100);
            });
        }
        
        // Esperar a que el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initAvailableRoutesMap, 300);
            });
        } else {
            setTimeout(initAvailableRoutesMap, 300);
        }
        
        // También inicializar cuando las imágenes terminen de cargar
        window.addEventListener('load', function() {
            if (!window.availableRoutesMapInitialized) {
                setTimeout(initAvailableRoutesMap, 100);
            }
        });
    })();
    </script>

    {{-- Paginación --}}
    @if(method_exists($flights, 'links') && $flights->hasPages())
        <div class="pagination-wrapper mt-4">
            {{ $flights->links() }}
        </div>
    @endif
</div>


{{-- SimBrief Dispatch Modal --}}
@include('vholar::components.simbrief-dispatch-modal')
