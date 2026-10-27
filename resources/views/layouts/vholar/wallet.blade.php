@extends('app')

@section('title', __('vholar_wallet.title'))

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0"><i class="bi bi-wallet2"></i> @lang('vholar_wallet.title')</h4>
    <div style="font-size: 0.75rem; color: var(--vh-text-muted);">@lang('vholar_wallet.subtitle')</div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card h-100" style="border-color: var(--vh-success)">
        <div class="card-body">
          <div class="small text-uppercase" style="color: var(--vh-text-muted);">@lang('vholar_wallet.balance')</div>
          <div class="fs-1 fw-bold" style="color: var(--vh-success)">{{ number_format($summary['balance'], 2) }}</div>
          <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_wallet.currency_note')</div>

          <hr>

          <div class="row text-center g-2">
            <div class="col-4">
              <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_wallet.received')</div>
              <div class="fw-semibold">{{ number_format($summary['received'], 2) }}</div>
            </div>
            <div class="col-4">
              <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_wallet.spent')</div>
              <div class="fw-semibold">{{ number_format($summary['spent'], 2) }}</div>
            </div>
            <div class="col-4">
              <div class="small" style="color: var(--vh-text-muted);">@lang('vholar_wallet.movements')</div>
              <div class="fw-semibold">{{ number_format($summary['count']) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">@lang('vholar_wallet.monthly')</div>
        <div class="card-body"><canvas id="walletMonthly" height="150"></canvas></div>
      </div>
    </div>
  </div>

  {{--
    Acciones: los cobros los hacen los modulos con su propio flujo (ya comprueban saldo y
    registran el asiento). Aqui solo se enlazan. Si un modulo esta inactivo, su ruta no
    existe y el boton no se pinta.
  --}}
  @php
    $actions = array_values(array_filter([
      Route::has('vmsopenops.jumpseat.index') ? ['bi-person-walking', route('vmsopenops.jumpseat.index'), __('vholar_wallet.action_jumpseat'), __('vholar_wallet.action_jumpseat_hint')] : null,
      Route::has('vmsopenops.ferry.index') ? ['bi-airplane', route('vmsopenops.ferry.index'), __('vholar_wallet.action_ferry'), __('vholar_wallet.action_ferry_hint')] : null,
      Route::has('vmsopenops.charter.create') ? ['bi-signpost-split', route('vmsopenops.charter.create'), __('vholar_wallet.action_charter'), __('vholar_wallet.action_charter_hint')] : null,
      Route::has('DSpecial.market') ? ['bi-shop', route('DSpecial.market'), __('vholar_wallet.action_market'), __('vholar_wallet.action_market_hint')] : null,
    ]));
  @endphp

  @if(count($actions))
    <div class="card mt-3">
      <div class="card-header">@lang('vholar_wallet.actions')</div>
      <div class="card-body">
        <div class="row g-2">
          @foreach($actions as [$icon, $url, $label, $hint])
            <div class="col-md-3">
              <a class="btn btn-outline-light w-100 h-100 text-start p-3" href="{{ $url }}"
                 style="border-color: var(--vh-border);">
                <i class="bi {{ $icon }} fs-5 d-block mb-1" style="color: var(--vh-accent-lite, #DFC8F5)"></i>
                <span class="fw-semibold d-block">{{ $label }}</span>
                <span class="small" style="color: var(--vh-text-muted);">{{ $hint }}</span>
              </a>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>@lang('vholar_wallet.history')</span>
      <span class="small" style="color: var(--vh-text-muted);">{{ number_format($movements->total()) }}</span>
    </div>
    <div class="card-body p-0">
      @if($movements->isEmpty())
        <div class="text-center p-4" style="color: var(--vh-text-muted);">@lang('vholar_wallet.empty')</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm mb-0" style="font-size: 0.85rem;">
            <thead>
              <tr>
                <th>@lang('vholar_wallet.date')</th>
                <th>@lang('vholar_wallet.concept')</th>
                <th class="text-end">@lang('vholar_wallet.amount')</th>
              </tr>
            </thead>
            <tbody>
              @foreach($movements as $movement)
                @php $net = ((int) $movement->credit - (int) $movement->debit) / 100; @endphp
                <tr>
                  <td class="text-nowrap" style="color: var(--vh-text-muted);">
                    {{ \Illuminate\Support\Carbon::parse($movement->post_date)->format('d M Y') }}
                  </td>
                  <td>{{ $movement->memo }}</td>
                  <td class="text-end fw-semibold" style="color: {{ $net >= 0 ? 'var(--vh-success)' : 'var(--vh-danger)' }}">
                    {{ $net > 0 ? '+' : '' }}{{ number_format($net, 2) }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
    @if($movements->hasPages())
      <div class="card-footer">{{ $movements->links() }}</div>
    @endif
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
      var rows = @json($monthly);
      var el = document.getElementById('walletMonthly');
      if (!el || !rows.length || typeof Chart === 'undefined') return;

      new Chart(el, {
        type: 'bar',
        data: {
          labels: rows.map(function (r) { return r.label.slice(5); }),
          datasets: [{
            data: rows.map(function (r) { return r.net; }),
            backgroundColor: rows.map(function (r) { return r.net >= 0 ? 'rgba(76,175,118,0.8)' : 'rgba(224,82,82,0.8)'; })
          }]
        },
        options: {
          plugins: {
            legend: {display: false},
            tooltip: {callbacks: {label: function (c) { return c.parsed.y.toLocaleString() + ' USD'; }}}
          }
        }
      });
    });
  </script>
@endpush
