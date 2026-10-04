@extends('app')
@section('title', __('vholar_network.title'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-diagram-3"></i> @lang('vholar_network.title')</h4>
  <div class="vh-tour-meta">
    @lang('vholar_network.subtitle', ['routes' => count($routes), 'flights' => number_format($totalFlights)])
  </div>
</div>

@if(empty($routes))
  <div class="alert alert-info p-1 fw-bold">@lang('vholar_network.no_routes')</div>
@else
  <div class="row g-3">
    <div class="col-lg-9">
      <div class="card">
        <div id="networkmap" class="vh-net-map"></div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="vh-tour-section mb-3"><i class="bi bi-palette"></i> @lang('vholar_network.legend')</h6>

          {{-- Paleta tambien aqui: si el proceso web sirve una version anterior del
               controlador (opcache), la vista no depende de que llegue $colors --}}
          @php
            $palette = ($colors ?? []) + [
              'heavy' => '#7E57C2', 'airliner' => '#4A90D9',
              'regional' => '#4CAF76', 'express' => '#EF6C00',
            ];
          @endphp

          @foreach(['heavy', 'airliner', 'regional', 'express'] as $category)
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size: 0.82rem;">
              <span class="vh-net-dot" style="background: {{ $palette[$category] }};"></span>
              <span>@lang('vholar_network.category_'.$category)</span>
              <span class="ms-auto vh-tour-meta">{{ $legend[$category] }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endif

<style>
.vh-tour-section {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--vh-text-muted, #9898b0);
  margin: 0;
}
.vh-tour-meta { font-size: 0.75rem; color: var(--vh-text-muted, #9898b0); }
.vh-net-map { height: 72vh; min-height: 420px; width: 100%; }
.vh-net-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
/* Popup de Leaflet en oscuro: por defecto es blanco y los enlaces del tema (claros)
   quedaban invisibles. Colores de los tokens, con respaldo. */
.leaflet-popup-content-wrapper, .leaflet-popup-tip {
  background: var(--vh-surface-2, #2b2333);
  color: var(--vh-text, #EDEAF1);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
}
.leaflet-popup-content { margin: 10px 12px; color: var(--vh-text, #EDEAF1); }
.leaflet-container a.leaflet-popup-close-button { color: var(--vh-text-muted, #A79FB2); }
.vh-net-popup { font-size: 0.8rem; line-height: 1.5; color: var(--vh-text, #EDEAF1); }
.vh-net-popup .vh-net-title { font-size: 0.9rem; font-weight: 700; color: var(--vh-white, #FFFFFF); }
.vh-net-popup .vh-net-sub { color: var(--vh-text-muted, #A79FB2); }
.vh-net-popup .vh-net-flights { margin-top: 6px; }
.vh-net-popup a { color: var(--vh-accent-lite, #DFC8F5); text-decoration: none; font-weight: 600; }
.vh-net-popup a:hover { color: var(--vh-white, #FFFFFF); text-decoration: underline; }
.leaflet-container { background: #1f1c27; }
.leaflet-control-layers {
  background: var(--vh-surface-2, #2b2333);
  color: var(--vh-text, #EDEAF1);
}
</style>
@endsection

@push('scripts')
  @if(!empty($routes))
    <script src="https://unpkg.com/leaflet-geodesic@1.0.0/leaflet.geodesic.js"></script>
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', function () {
        var routes = @json($routes, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        var map = L.map('networkmap', {worldCopyJump: true});
        L.tileLayer('{!! carto_tile_url('dark_all') !!}', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap &copy; CARTO'
        }).addTo(map);

        var labels = {
          distance: @json(__('vholar_network.distance')),
          flights:  @json(__('vholar_network.flights'))
        };

        var groups = {
          heavy:    L.layerGroup(),
          airliner: L.layerGroup(),
          regional: L.layerGroup(),
          express:  L.layerGroup()
        };

        var bounds = L.latLngBounds([]);
        var airports = {};

        routes.forEach(function (route) {
          var line = L.geodesic(
            [[route.dpt_lat, route.dpt_lon], [route.arr_lat, route.arr_lon]],
            {weight: 2, opacity: 0.7, steps: 8, color: route.color}
          );

          var list = route.flights.map(function (f) {
            return '<a href="/flights/' + f.id + '" target="_blank">' + f.number + '</a>';
          }).join(', ');

          var html = '<div class="vh-net-popup">'
            + '<div class="vh-net-title">' + route.dpt + ' &rarr; ' + route.arr + '</div>'
            + '<div class="vh-net-sub">' + route.dpt_name + '<br>' + route.arr_name + '</div>'
            + '<div class="mt-1">' + labels.distance + ': ' + route.distance + ' nm</div>'
            + '<div class="vh-net-flights">' + labels.flights + ' (' + route.flights.length + '):<br>' + list + '</div>'
            + '</div>';

          line.bindPopup(html, {maxWidth: 340, autoPan: false});

          // Al pasar el raton se abre; al salir se cierra con un respiro, para que de
          // tiempo a llegar al popup y pulsar el numero de vuelo. Con click se queda
          // fijado (y en movil es la unica forma).
          var closeTimer = null;

          function cancelClose() {
            if (closeTimer) {
              clearTimeout(closeTimer);
              closeTimer = null;
            }
          }

          line.on('mouseover', function (e) {
            cancelClose();
            e.target.setStyle({weight: 4, opacity: 1});
            e.target.openPopup();
          });

          line.on('mouseout', function (e) {
            e.target.setStyle({weight: 2, opacity: 0.7});

            if (e.target.netPinned) {
              return;
            }

            var layer = e.target;
            closeTimer = setTimeout(function () { layer.closePopup(); }, 450);
          });

          line.on('click', function (e) {
            cancelClose();
            e.target.netPinned = true;
            e.target.openPopup();
          });

          line.on('popupopen', function (e) {
            var el = e.popup.getElement();

            if (!el) {
              return;
            }

            el.addEventListener('mouseenter', cancelClose);
            el.addEventListener('mouseleave', function () {
              if (!line.netPinned) {
                line.closePopup();
              }
            });
          });

          line.addTo(groups[route.category]);

          bounds.extend([[route.dpt_lat, route.dpt_lon], [route.arr_lat, route.arr_lon]]);

          [['dpt', route.dpt_lat, route.dpt_lon, route.dpt, route.dpt_name],
           ['arr', route.arr_lat, route.arr_lon, route.arr, route.arr_name]].forEach(function (a) {
            if (!airports[a[3]]) {
              airports[a[3]] = L.circleMarker([a[1], a[2]], {
                radius: 2.5, color: '#EDEAF1', weight: 1, opacity: 0.5, fillOpacity: 0.5
              }).bindTooltip(a[3] + ' - ' + a[4], {direction: 'top'}).addTo(map);
            }
          });
        });

        ['heavy', 'airliner', 'regional', 'express'].forEach(function (category) {
          groups[category].addTo(map);
        });

        L.control.layers(null, groups, {collapsed: false, position: 'topright'}).addTo(map);

        if (bounds.isValid()) {
          map.fitBounds(bounds.pad(0.06));
        } else {
          map.setView([4.6, -74.1], 5);
        }
      });
    </script>
  @endif
@endpush
