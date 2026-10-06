{{--
  Analisis financiero de un PIREP concreto: ingresos por tarifas, desglose de costes y
  resultado, leidos del libro diario de ese vuelo.

  Espera $pirep (lo tiene siempre la vista del detalle). No pinta nada si el vuelo no tiene
  asientos contables: los PIREPs importados del CrewSystem no tienen ingreso derivable y es
  mejor no ensenar un cero que parece una perdida.

  COLORES: tokens --vh-* de tokens.css, NO clases semanticas de Bootstrap: en modo oscuro
  alert-warning cambia sus variables y el texto puede quedar invisible. Se usa el color de
  gravedad en el acento y --vh-text en el cuerpo.
--}}
@php
    $finance   = !empty($pirep) ? app(\App\Services\ProfitabilityService::class)->forPirep($pirep) : null;
    $profit    = $finance['profit'];
    $accent    = $profit >= 0 ? '--vh-success' : '--vh-danger';
    $accentSoft = $profit >= 0 ? '--vh-success-soft' : '--vh-danger-soft';
@endphp

@if($finance && $finance['has_ledger'])
  <div class="card vholar-card mt-3" style="border-color: var({{ $accent }})">
    <div class="card-header d-flex align-items-center gap-2">
      <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Análisis financiero</h5>
      <span class="badge"
            style="background: var({{ $accentSoft }}); color: var({{ $accent }}); border: 1px solid var({{ $accent }});">
        <i class="bi bi-{{ $profit >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' }} me-1"></i>
        {{ $profit >= 0 ? 'Rentable' : 'En pérdidas' }}
      </span>
      <span class="ms-auto small text-muted">
        {{ number_format($finance['hours'], 1) }} h · {{ $finance['margin'] !== null ? $finance['margin'].'% sobre coste' : '' }}
      </span>
    </div>
    <div class="card-body">
      <div class="row g-3 text-center mb-3">
        <div class="col-4">
          <div class="small text-muted">Ingresos</div>
          <div class="fs-5 fw-semibold">{{ number_format($finance['revenue']) }}</div>
          <div class="small text-muted">{{ number_format($finance['revenue_hour']) }}/h</div>
        </div>
        <div class="col-4">
          <div class="small text-muted">Costes</div>
          <div class="fs-5 fw-semibold">{{ number_format($finance['costs_total']) }}</div>
          <div class="small text-muted">{{ number_format($finance['cost_hour']) }}/h</div>
        </div>
        <div class="col-4">
          <div class="small text-muted">Resultado</div>
          <div class="fs-5 fw-bold" style="color: var({{ $accent }})">
            {{ $profit > 0 ? '+' : '' }}{{ number_format($profit) }}
          </div>
          <div class="small text-muted">USD</div>
        </div>
      </div>

      @if(!empty($finance['breakdown']))
        <p class="small text-muted mb-2 fw-semibold"><i class="bi bi-list-ul me-1"></i>Desglose de costes</p>
        <table class="table table-sm mb-0" style="font-size: 0.8rem;">
          @foreach($finance['breakdown'] as $concept => $amount)
            <tr>
              <td>{{ $concept }}</td>
              <td class="text-end">{{ number_format($amount) }}</td>
              <td class="text-end text-muted" style="width: 64px;">
                {{ $finance['costs_total'] > 0 ? round(100 * $amount / $finance['costs_total']) : 0 }}%
              </td>
            </tr>
          @endforeach
        </table>
      @endif

      @if($finance['fares']->isNotEmpty())
        <p class="small text-muted mb-2 mt-3 fw-semibold"><i class="bi bi-ticket-perforated me-1"></i>Tarifas del vuelo</p>
        <table class="table table-sm mb-0" style="font-size: 0.8rem;">
          @foreach($finance['fares'] as $fare)
            <tr>
              <td>
                <span class="text-muted">{{ $fare->code }}</span>
                {{ $fare->type == 1 ? 'carga' : 'pax' }} × {{ $fare->count }}
              </td>
              <td class="text-end text-muted">{{ number_format($fare->price) }}/ud</td>
              <td class="text-end fw-semibold">{{ number_format($fare->price * $fare->count) }}</td>
            </tr>
          @endforeach
        </table>
      @endif

      <p class="small text-muted mb-0 mt-3">
        <i class="bi bi-info-circle me-1"></i>Datos del libro diario de este vuelo: tarifas cobradas y todos sus
        costes (combustible, coste de bloque, handling y pago al piloto). No incluye nada de otros vuelos.
      </p>
    </div>
  </div>
@endif
