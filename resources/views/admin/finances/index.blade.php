@extends('admin.app')

@section('title', 'Financial Reports')
@section('actions')
  <li><a href="{{ route('admin.finances.index') }}"><i class="ti-menu-alt"></i>Overview</a></li>
@endsection
@section('content')
  <div class="row">
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>Annual profit</h5><canvas id="chartAnnual" height="180"></canvas>
    </div></div></div>
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>Monthly profit · {{ $series['year'] }}</h5><canvas id="chartMonthly" height="180"></canvas>
    </div></div></div>
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>Daily profit · {{ $series['month'] }}</h5><canvas id="chartDaily" height="180"></canvas>
    </div></div></div>
  </div>

  <div class="row">
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>This year ({{ $series['year'] }})</h5>
      <div style="font-size:1.3rem;font-weight:700;">{{ number_format($series['totals']['year']['profit']) }} USD</div>
      <div class="text-muted" style="font-size:0.8rem;">income {{ number_format($series['totals']['year']['income']) }} · costs {{ number_format($series['totals']['year']['cost']) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>This month ({{ $series['month'] }})</h5>
      <div style="font-size:1.3rem;font-weight:700;">{{ number_format($series['totals']['month']['profit']) }} USD</div>
      <div class="text-muted" style="font-size:0.8rem;">income {{ number_format($series['totals']['month']['income']) }} · costs {{ number_format($series['totals']['month']['cost']) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-blue-bottom"><div class="content">
      <h5>Data coverage</h5>
      <table class="table table-sm" style="font-size:0.8rem;">
        @foreach($coverage as $row)
          <tr><td>{{ $row->year }}</td><td class="text-right">{{ $row->with_income }}/{{ $row->accepted }}</td><td class="text-right">{{ $row->coverage }}%</td></tr>
        @endforeach
      </table>
      <div class="text-muted" style="font-size:0.72rem;">Imported (CrewSystem) and manual PIREPs have no ACARS telemetry, so no income can be derived: those periods look worse than they were.</div>
    </div></div></div>
  </div>

  <div class="card border-blue-bottom">
    <div class="content">
      <div style="float:right;">
        {{ Form::select(
                'month_select',
                $months_list,
                $current_month,
                ['id' => 'month_select']
            ) }}
      </div>

      @include('admin.finances.table')

    </div>
  </div>
@endsection
@include('admin.finances.scripts')
