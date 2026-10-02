@extends('app')
@section('title', __('common.fleet_map'))

@section('content')
<div class="mb-3 d-flex align-items-center justify-content-between">
  <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:var(--vh-text-muted);">
    &#9992; &nbsp;@lang('common.fleet_map')
  </h4>
  <div style="font-size:0.72rem;color:var(--vh-silver-dim);">
    {{ array_sum(array_column($airportFleet, 'total')) }} aircraft &middot; {{ count($airportFleet) }} airports
  </div>
</div>

<div class="card mb-3" style="border-radius:10px;overflow:hidden;">
  <div class="card-body p-0">
    <div id="fleetMap" style="height:78vh;width:100%;"></div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
  function initFleetMap() {
    var el = document.getElementById('fleetMap');
    if (!el || window._fleetMapInit || typeof L === 'undefined') {
      if (typeof L === 'undefined') setTimeout(initFleetMap, 200);
      return;
    }
    window._fleetMapInit = true;

    var map = L.map('fleetMap').setView([4.5709, -74.2973], 4);
    L.tileLayer('{{ carto_tile_url('dark_all') }}', {
      attribution: '&copy; OSM &copy; CartoDB', subdomains: 'abcd', minZoom: 2, maxZoom: 18
    }).addTo(map);

    var bounds = [];
    var airports = @json(array_values($airportFleet));

    airports.forEach(function(apt) {
      bounds.push([apt.lat, apt.lon]);

      // Build badge HTML
      var aptLabel = apt.iata ? apt.id + '/' + apt.iata : apt.id;
      var typeLines = Object.entries(apt.types)
        .map(function(e) { return e[0] + ' (' + e[1] + ')'; });

      // Width: widest of airport label or any type line (approx 7.5px per char at 0.62rem)
      var maxChars = aptLabel.length + 4; // +4 for "✈ "
      typeLines.forEach(function(t) { if (t.length > maxChars) maxChars = t.length; });
      var w = Math.max(80, maxChars * 7.5 + 18);

      // Height: 20px badge + 16px per type line + 4px padding
      var h = 20 + typeLines.length * 16 + 4;

      var typesHtml = typeLines.map(function(t) {
        return '<div style="font-size:0.6rem;font-weight:700;color:#EDEAF1;letter-spacing:0.03em;line-height:1.5;">' + t + '</div>';
      }).join('');

      var html =
        '<div style="width:' + w + 'px;text-align:center;">' +
          '<span style="display:inline-block;white-space:nowrap;font-family:\'Courier New\',monospace;' +
            'font-size:0.68rem;font-weight:800;color:#0d0b18;background:#BDBFC1;' +
            'border:1.5px solid rgba(13,11,24,0.35);border-radius:4px;padding:1px 6px;' +
            'box-shadow:0 2px 4px rgba(0,0,0,0.6);letter-spacing:0.04em;">&#9992; ' + aptLabel + '</span>' +
          (typeLines.length ? '<div style="margin-top:3px;">' + typesHtml + '</div>' : '') +
        '</div>';

      var popupLines = typeLines.join('<br>');
      L.marker([apt.lat, apt.lon], {
        icon: L.divIcon({
          className: '',
          html: html,
          iconSize: [w, h],
          iconAnchor: [w / 2, 10]
        })
      }).bindPopup('<strong>&#9992; ' + aptLabel + '</strong><br>' + (apt.name || '') + '<br>' + popupLines)
        .addTo(map);
    });

    if (bounds.length) map.fitBounds(bounds, { padding: [40, 40] });
    setTimeout(function() { map.invalidateSize(); }, 100);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { setTimeout(initFleetMap, 300); });
  } else {
    setTimeout(initFleetMap, 300);
  }
})();
</script>
@endsection
