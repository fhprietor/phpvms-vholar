@extends('app')
@section('title', 'Tours')

@section('content')
@php
  // Distancias de los legs: una sola consulta agrupada por route_code. Se consulta la
  // tabla directamente porque el modelo Flight aplica un scope global y dejaba fuera
  // legs (daba 1 nm en vez de 360 para el tour ANDES).
  $legDistances = \Illuminate\Support\Facades\DB::table('flights')
      ->whereIn('route_code', $tours->pluck('tour_code')->all())
      ->selectRaw('route_code, coalesce(sum(distance), 0) as distance')
      ->groupBy('route_code')
      ->pluck('distance', 'route_code');

  // Secciones al estilo de la web de referencia: en vez de pestanas, bloques.
  $endingSoon = [];
  $current = [];
  $future = [];
  $past = [];

  foreach ($tours as $tour) {
      if ($carbon_now > $tour->end_date) {
          $past[] = $tour;
      } elseif ($carbon_now < $tour->start_date) {
          $future[] = $tour;
      } elseif ($tour->end_date->diffInDays($carbon_now) <= 30) {
          $endingSoon[] = $tour;
      } else {
          $current[] = $tour;
      }
  }

  $sections = [
      ['Terminan pronto', 'bi-hourglass-split', $endingSoon],
      ['Tours en curso', 'bi-airplane-engines', $current],
      ['Proximos tours', 'bi-calendar-plus', $future],
      ['Tours finalizados', 'bi-archive', $past],
  ];
@endphp

<style>
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
.vh-tour-card {
  overflow: hidden;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.vh-tour-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
}
.vh-tour-cover {
  position: relative;
  display: block;
  height: 132px;
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
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  color: rgba(255, 255, 255, 0.22);
}
.vh-tour-airline {
  position: absolute;
  left: 10px;
  bottom: 10px;
  max-height: 30px;
  max-width: 96px;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 5px;
  padding: 2px 4px;
}
.vh-tour-badge {
  position: absolute;
  top: 10px;
  right: 10px;
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
.vh-tour-title a { color: inherit; text-decoration: none; }
.vh-tour-title a:hover { color: var(--vh-accent-lite, #DFC8F5); }
.vh-tour-desc {
  font-size: 0.78rem;
  color: var(--vh-text-muted, #9898b0);
  min-height: 2.2rem;
}
.vh-tour-meta {
  font-size: 0.72rem;
  color: var(--vh-text-muted, #9898b0);
}
.vh-tour-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.72rem;
  color: var(--vh-text-muted, #9898b0);
  border-top: 1px solid rgba(255, 255, 255, 0.07);
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-map"></i> Tours</h4>

  @if($tour_subfleets->count() > 0)
    <form method="GET" action="{{ route('DSpecial.tours') }}">
      <div class="input-group input-group-sm">
        <span class="input-group-text">Fleet :</span>
        <select class="form-select form-select-sm" name="sfid">
          <option value="">Please select...</option>
          @foreach($tour_subfleets as $sf)
            <option value="{{ $sf->id }}" @if($sf->id == @request()->input('sfid')) selected @endif>{{ $sf->name.' | '.optional($sf->airline)->code }}</option>
          @endforeach
        </select>
        <input class="btn btn-sm btn-success" type="submit" value="Search">
      </div>
    </form>
  @endif
</div>

@if(!$tours->count())
  <div class="alert alert-info p-1 fw-bold">No Tours Found!</div>
@else
  @foreach($sections as [$title, $icon, $list])
    @if(count($list))
      <h5 class="vh-tour-section">
        <i class="bi {{ $icon }}"></i> {{ $title }}
        <span class="badge">{{ count($list) }}</span>
      </h5>
      <div class="row g-3 mb-4">
        @foreach($list as $tour)
          @include('DSpecial::tours.table', ['leg_distance' => $legDistances[$tour->tour_code] ?? null])
        @endforeach
      </div>
    @endif
  @endforeach

  <h5 class="vh-tour-section"><i class="bi bi-question-circle"></i> Reglas de los tours</h5>
  <div class="card mb-2">
    <div class="card-body p-3" style="font-size: 0.82rem;">
      <p>&bull;&nbsp;Tours can be flown and reported either manually or with acars support, for acars supported tour flights pilots can either bid/load a flight from the list or enter required info manually to
      New Flight window of our acars software. While sending a manual pirep or using acars with manual flight info entry, please do not forget to add correct route code and leg number to your reports. Missing this step may cause problems during route leg checks and award controls.</p>
      <p>&bull;&nbsp;<b>Open Tours</b>&nbsp; can be flown with any airline and aircraft according to pilot's choice, simply there are no company and/or aircraft restrictions for this type. While on the other hand <b>Airline Tours</b> must be flown with correct airline callsign and if provided with the subfleet assigned to the leg.</p>
      <p>&bull;&nbsp;As a general rule, all tour legs must be completed between validity period for earning awards.</p>
      <p>&bull;&nbsp;<b>To see the details and legs of a tour, simply click on the Tour Name</b></p>
      <p>Safe Flights</p>
    </div>
  </div>
@endif
@endsection
