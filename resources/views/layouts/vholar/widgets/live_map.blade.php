@section('css')
  @parent
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" ... />
  <style>
    /* Estilos Vholar para el mapa */
    .map-info-box {
      position: absolute;
      bottom: 0;
      padding: 15px 20px;
      min-height: 100px;
      z-index: 1000 !important;
      background: linear-gradient(135deg, #2a2633 0%, #1f1c27 100%);
      border-top: 2px solid #412c4d;
      backdrop-filter: blur(8px);
      border-radius: 12px 12px 0 0;
      color: #e5e5e5;
    }

    /* POPUP - z-index extremadamente alto */
    .leaflet-popup {
      z-index: 99999 !important;
    }

    .leaflet-popup-content-wrapper {
      z-index: 99999 !important;
    }

    .leaflet-popup-tip {
      z-index: 99999 !important;
    }

    /* POPUP COMPACTO */
    .leaflet-popup-content-wrapper {
      background: linear-gradient(135deg, #2a2633 0%, #1f1c27 100%) !important;
      border-radius: 8px !important;
      border: 1px solid #412c4d !important;
      padding: 0 !important;
    }

    .leaflet-popup-content {
      margin: 4px 6px !important;
      min-width: 160px;
      max-width: 200px;
    }

    .leaflet-popup-content p {
      margin: 0 0 2px 0 !important;
      line-height: 1.2 !important;
      font-size: 0.7rem !important;
    }

    .leaflet-popup-content strong {
      font-size: 0.8rem;
    }

    .leaflet-popup-content a {
      font-size: 0.6rem;
      padding: 1px 4px;
      margin-top: 2px;
      display: inline-block;
    }
  </style>
@endsection

<div class="row">
  <div class="col-md-12">
    <div class="box-body">
      <div id="map" style="width: {{ $config['width'] }}; height: {{ $config['height'] }}; border-radius: 12px; overflow: hidden; border: 1px solid #412c4d;">
        <div id="map-info-box" class="map-info-box" rv-show="pirep.id" style="width: {{ $config['width'] }};">
          <div style="float: left; width: 50%;">
            <h3 style="margin: 0" id="map_flight_id">
              <a rv-href="pirep.id | prepend '{{url('/pireps/')}}/'" target="_blank">
                { pirep.airline.icao }{ pirep.flight_number }
              </a>
            </h3>
            <p id="map_flight_info">
              { pirep.dpt_airport.name } ({ pirep.dpt_airport.icao }) @lang('common.to')
              { pirep.arr_airport.name } ({ pirep.arr_airport.icao })
            </p>
          </div>
          <div style="float: right; margin-left: 30px; margin-right: 30px;">
            <p id="map_flight_stats_right">
              @lang('widgets.livemap.groundspeed'): <span style="font-weight: bold">{ pirep.position.gs }</span><br/>
              @lang('widgets.livemap.altitude'): <span style="font-weight: bold">{ pirep.position.altitude }</span><br/>
              @lang('widgets.livemap.heading'): <span style="font-weight: bold">{ pirep.position.heading }</span><br/>
            </p>
          </div>
          <div style="float: right; margin-left: 30px;">
            <p id="map_flight_stats_middle">
              @lang('common.status'): <span style="font-weight: bold">{ pirep.status_text }</span><br/>
              @lang('flights.flighttime'): <span style="font-weight: bold">{ pirep.flight_time | time_hm }</span><br/>
              @lang('common.distance'): <span style="font-weight: bold">{ pirep.position.distance.{{setting('units.distance')}} }</span>
              / <span style="font-weight: bold">{ pirep.planned_distance.{{setting('units.distance')}} }</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@if($config['table'] === true)
<div class="clearfix" style="padding-top: 25px"></div>

<div id="live_flights" class="row">
  <div class="col-md-12">
    <div class="card vholar-card">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-table"></i> Active Flights</h5>
      </div>
      <div class="card-body p-0">
        <div rv-hide="has_data" class="p-5 text-center text-muted">
          <i class="bi bi-airplane" style="font-size: 2rem;"></i>
          <p class="mt-2">@lang('widgets.livemap.noflights')</p>
        </div>
        <div class="table-responsive">
          <table rv-show="has_data" id="live_flights_table" class="table">
            <thead>
              <tr class="text-small header">
                <th class="text-small">{{ trans_choice('common.flight', 2) }}</th>
                <th class="text-small">Pilot</th>
                <th class="text-small">@lang('common.departure')</th>
                <th class="text-small">@lang('common.arrival')</th>
                <th class="text-small">@lang('common.aircraft')</th>
                <th class="text-small">@lang('widgets.livemap.altitude')</th>
                <th class="text-small">@lang('widgets.livemap.gs')</th>
                <th class="text-small">@lang('widgets.livemap.distance')</th>
                <th class="text-small">@lang('common.status')</th>
              </tr>
            </thead>
            <tbody>
              <tr rv-each-pirep="pireps">
                <td><a href="#top_anchor" rv-on-click="controller.focusMarker" style="color: #c9a6db;">{ pirep.ident }</a></td>
                <td>{ pirep.user.name_private | fallback pirep.user.name }</td>
                <td><span rv-title="pirep.dpt_airport.name">{ pirep.dpt_airport.icao }</span></td>
                <td><span rv-title="pirep.arr_airport.name">{ pirep.arr_airport.icao }</span></td>
                <td>{ pirep.aircraft.registration }</td>
                <td>{ pirep.position.altitude } ft</td>
                <td>{ pirep.position.gs } kt</td>
                <td>{ pirep.position.distance.{{setting('units.distance')}} | fallback 0 } / { pirep.planned_distance.{{setting('units.distance')}} | fallback 0 }</td>
                <td>{ pirep.status_text }</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

@section('scripts')
  <script>
    // Colores por estado (estilo FR24)
    const fr24StatusColors = {
        'SCHEDULED': '#6c757d',
        'ENROUTE': '#28a745',
        'ARRIVED': '#17a2b8',
        'CANCELLED': '#dc3545',
        'TAKEOFF': '#17a2b8',
        'APPROACH': '#ffc107',
        'LANDING': '#dc3545'
    };

    function getFr24StatusColor(status) {
        return fr24StatusColors[status] || '#6c757d';
    }

    function formatTime(timeStr) {
        if (!timeStr) return '--:--';
        const date = new Date(timeStr);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDuration(minutes) {
        if (!minutes) return '--';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}h ${mins}m`;
    }

    function loadFlightInfo(pirepId) {
        const modalBody = document.getElementById('flightInfoModalBody');
        const modalLink = document.getElementById('flightInfoModalLink');
        
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 text-muted">Loading flight data...</p>
            </div>
        `;
        
        modalLink.href = `/pireps/${pirepId}`;
        
        fetch(`/api/pireps/${pirepId}`)
            .then(response => response.json())
            .then(data => {
                const p = data.data;
                const pos = p.position || {};
                const statusColor = getFr24StatusColor(p.status);
                
                modalBody.innerHTML = `
                    <div class="fr24-flight-header">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <div class="fr24-callsign">${p.ident}</div>
                                <div class="fr24-airline">${p.airline?.name || ''}</div>
                            </div>
                            <div class="fr24-status" style="background: ${statusColor}20; color: ${statusColor}">
                                <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                                ${p.status_text}
                            </div>
                        </div>
                    </div>
                    
                    <div class="fr24-route d-flex align-items-center justify-content-between">
                        <div class="fr24-route-point text-start">
                            <div class="fr24-airport-code">${p.dpt_airport_id}</div>
                            <div class="fr24-airport-name">${p.dpt_airport?.name || 'Departure'}</div>
                            <div class="fr24-route-time">${formatTime(p.block_off_time)}</div>
                        </div>
                        <div class="fr24-route-arrow">
                            <i class="bi bi-arrow-right"></i>
                        </div>
                        <div class="fr24-route-point text-end">
                            <div class="fr24-airport-code">${p.arr_airport_id}</div>
                            <div class="fr24-airport-name">${p.arr_airport?.name || 'Arrival'}</div>
                            <div class="fr24-route-time">${formatTime(p.block_on_time)}</div>
                        </div>
                    </div>
                    
                    <div class="fr24-info-grid">
                        <div class="fr24-info-row">
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">AIRCRAFT</div>
                                <div class="fr24-info-value">${p.aircraft?.registration || 'N/A'} (${p.aircraft?.name || 'N/A'})</div>
                            </div>
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">CALLSIGN</div>
                                <div class="fr24-info-value">${p.callsign || p.ident}</div>
                            </div>
                        </div>
                        <div class="fr24-info-row">
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">PILOT</div>
                                <div class="fr24-info-value">${p.user?.ident || 'N/A'} - ${p.user?.name_private || p.user?.name || 'N/A'}</div>
                            </div>
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">FLIGHT TIME</div>
                                <div class="fr24-info-value">${formatDuration(p.flight_time)}</div>
                            </div>
                        </div>
                        <div class="fr24-info-row">
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">ALTITUDE</div>
                                <div class="fr24-info-value">${Math.round(pos.altitude || 0)} ft</div>
                            </div>
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">GROUND SPEED</div>
                                <div class="fr24-info-value">${Math.round(pos.gs || 0)} kt</div>
                            </div>
                        </div>
                        <div class="fr24-info-row">
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">HEADING</div>
                                <div class="fr24-info-value">${Math.round(pos.heading || 0)}°</div>
                            </div>
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">DISTANCE</div>
                                <div class="fr24-info-value">${Math.round(p.distance || 0)} nm</div>
                            </div>
                        </div>
                        <div class="fr24-info-row">
                            <div class="fr24-info-item">
                                <div class="fr24-info-label">ROUTE</div>
                                <div class="fr24-info-value"><code>${p.route || 'Direct'}</code></div>
                            </div>
                        </div>
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('flightInfoModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error loading flight info:', error);
                modalBody.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem; color: #dc3545;"></i>
                        <p class="mt-3 text-danger">Error loading flight information</p>
                    </div>
                `;
                const modal = new bootstrap.Modal(document.getElementById('flightInfoModal'));
                modal.show();
            });
    }

    // Función para aplicar estilos compactos a los popups
    function makePopupCompact() {
        // Buscar todos los popups de Leaflet
        const popups = document.querySelectorAll('.leaflet-popup-content');
        popups.forEach(function(popup) {
            // Aplicar estilos inline directamente
            popup.style.margin = '4px 6px !important';
            popup.style.minWidth = '160px';
            popup.style.maxWidth = '200px';
            
            // Ajustar el contenido
            const paragraphs = popup.querySelectorAll('p');
            paragraphs.forEach(function(p) {
                p.style.margin = '0 0 2px 0';
                p.style.lineHeight = '1.2';
                p.style.fontSize = '0.7rem';
            });
            
            const strongs = popup.querySelectorAll('strong');
            strongs.forEach(function(s) {
                s.style.fontSize = '0.8rem';
            });
            
            const links = popup.querySelectorAll('a');
            links.forEach(function(a) {
                a.style.fontSize = '0.6rem';
                a.style.padding = '1px 4px';
                a.style.marginTop = '2px';
                a.style.display = 'inline-block';
            });
        });
    }

    // Renderizar el mapa
    phpvms.map.render_live_map({
        center: ['{{ $center[0] }}', '{{ $center[1] }}'],
        zoom: '{{ $zoom }}',
        aircraft_icon: '{!! public_asset('/assets/img/acars/aircraft.png') !!}',
        refresh_interval: {{ setting('acars.update_interval', 60) }},
        units: '{{ setting('units.distance') }}',
        flown_route_color: '#067ec1',
        leafletOptions: {
            scrollWheelZoom: false,
        }
    });

    // Aplicar estilos compactos periódicamente (porque los popups se recrean)
setInterval(function() {
    const popups = document.querySelectorAll('.leaflet-popup');
    const infoBox = document.querySelector('.map-info-box');
    
    if (popups.length > 0 && infoBox) {
        popups.forEach(function(popup) {
            popup.style.zIndex = '10001';
        });
        infoBox.style.zIndex = '9999';
    }
}, 100);

    // También después de cada actualización del mapa
    const originalRender = phpvms.map.render_live_map;
    
    // Interceptar clics en los enlaces del popup para abrir el modal
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href*="/pireps/"]');
        if (link && link.href) {
            e.preventDefault();
            e.stopPropagation();
            const pirepId = link.href.split('/pireps/')[1].split('/')[0];
            loadFlightInfo(pirepId);
        }
    });

    // Mover el popup al final del body para que esté por encima
setInterval(function() {
    const popup = document.querySelector('.leaflet-popup');
    if (popup && popup.parentNode !== document.body) {
        document.body.appendChild(popup);
        popup.style.zIndex = '99999';
    }
}, 100);

// Función para compactar el contenido del popup - Versión ultra compacta
function compactPopupContent() {
    const popups = document.querySelectorAll('.leaflet-popup-content');
    
    popups.forEach(function(popup) {
        if (popup.hasAttribute('data-compacted')) return;
        
        const content = popup.innerHTML;
        
        const identMatch = content.match(/([A-Z0-9]{3,10})/);
        const ident = identMatch ? identMatch[1] : 'Flight';
        
        const routeMatch = content.match(/([A-Z]{4}) → ([A-Z]{4})/);
        const dpt = routeMatch ? routeMatch[1] : '---';
        const arr = routeMatch ? routeMatch[2] : '---';
        
        const linkMatch = content.match(/href="([^"]+)"/);
        const link = linkMatch ? linkMatch[1] : '#';
        
        popup.innerHTML = `
            <div style="padding: 4px;">
                <div style="font-weight: 700; font-size: 0.8rem;">${ident}</div>
                <div style="font-size: 0.65rem; color: #2c7be5;">${dpt}→${arr}</div>
                <a href="${link}" style="display: block; margin-top: 3px; font-size: 0.6rem; color: #c9a6db; text-decoration: none;">Details →</a>
            </div>
        `;
        
        popup.setAttribute('data-compacted', 'true');
        
        const wrapper = popup.closest('.leaflet-popup-content-wrapper');
        if (wrapper) {
            wrapper.style.width = 'auto';
            wrapper.style.maxWidth = '140px';
        }
    });
}

// Ejecutar cada vez que se abre un popup
setInterval(function() {
    compactPopupContent();
}, 200);

  </script>
@endsection