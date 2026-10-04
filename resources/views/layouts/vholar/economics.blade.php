@extends('app')
@section('title', 'Economía')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Economía de la compañía</h4>
  <div class="vh-tour-meta" style="font-size:0.75rem;color:var(--vh-text-muted);">
    Libro diario de los PIREPs · ingresos por tarifas frente a todos los costes
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Utilidad anual</div>
      <div class="card-body"><canvas id="chartAnnual" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Utilidad mensual · {{ $series['year'] }}</div>
      <div class="card-body"><canvas id="chartMonthly" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Utilidad diaria · {{ $series['month'] }}</div>
      <div class="card-body"><canvas id="chartDaily" height="200"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <div class="vh-tour-meta" style="font-size:0.72rem;color:var(--vh-text-muted);text-transform:uppercase;">Este año ({{ $series['year'] }})</div>
      <div class="fs-4 fw-bold {{ $series['totals']['year']['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
        {{ number_format($series['totals']['year']['profit']) }} USD
      </div>
      <div style="font-size:0.75rem;color:var(--vh-text-muted);">
        ingresos {{ number_format($series['totals']['year']['income']) }} · costes {{ number_format($series['totals']['year']['cost']) }}
      </div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <div class="vh-tour-meta" style="font-size:0.72rem;color:var(--vh-text-muted);text-transform:uppercase;">Este mes ({{ $series['month'] }})</div>
      <div class="fs-4 fw-bold {{ $series['totals']['month']['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
        {{ number_format($series['totals']['month']['profit']) }} USD
      </div>
      <div style="font-size:0.75rem;color:var(--vh-text-muted);">
        ingresos {{ number_format($series['totals']['month']['income']) }} · costes {{ number_format($series['totals']['month']['cost']) }}
      </div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <div class="vh-tour-meta" style="font-size:0.72rem;color:var(--vh-text-muted);text-transform:uppercase;">Cobertura del dato</div>
      <table class="table table-sm mb-0" style="font-size:0.78rem;">
        @foreach($coverage as $row)
          <tr>
            <td>{{ $row->year }}</td>
            <td class="text-end">{{ $row->with_income }}/{{ $row->accepted }}</td>
            <td class="text-end {{ $row->coverage < 80 ? 'text-warning' : 'text-success' }}">{{ $row->coverage }}%</td>
          </tr>
        @endforeach
      </table>
      <div class="mt-2" style="font-size:0.72rem;color:var(--vh-text-muted);">
        Los PIREPs importados del CrewSystem y los manuales no tienen telemetría ACARS, así que
        no se les puede derivar el ingreso: sus periodos salen peor de lo que fueron.
      </div>
    </div></div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    var series = @json($series);

    function barChart(id, rows, labelFn) {
      var el = document.getElementById(id);
      if (!el || !rows.length || typeof Chart === 'undefined') return;

      new Chart(el, {
        type: 'bar',
        data: {
          labels: rows.map(labelFn),
          datasets: [
            {label: 'Ingresos', data: rows.map(function (r) { return r.income; }), backgroundColor: 'rgba(76,175,118,0.7)'},
            {label: 'Costes',   data: rows.map(function (r) { return r.cost; }),   backgroundColor: 'rgba(224,82,82,0.7)'},
            {label: 'Utilidad', data: rows.map(function (r) { return r.profit; }), backgroundColor: 'rgba(74,144,217,0.9)'}
          ]
        },
        options: {
          responsive: true,
          plugins: {
            tooltip: {callbacks: {label: function (c) { return c.dataset.label + ': ' + c.parsed.y.toLocaleString() + ' USD'; }}}
          },
          scales: {y: {ticks: {callback: function (v) { return v.toLocaleString(); }}}}
        }
      });
    }

    barChart('chartAnnual', series.annual, function (r) { return r.label; });
    barChart('chartMonthly', series.monthly, function (r) { return r.label.slice(5); });
    barChart('chartDaily', series.daily, function (r) { return r.label.slice(8); });
  });
</script>
@endpush
