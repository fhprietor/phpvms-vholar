@extends('app')
@section('title', trans_choice('common.pirep', 1).' '.$pirep->ident)

@section('content')
  <div class="row">
    <div class="col-sm-8">
      <h2>{{ $pirep->ident }} : {{ $pirep->dpt_airport_id }} to {{ $pirep->arr_airport_id }}</h2>
    </div>

    <div class="col-sm-4">
      {{-- Show the link to edit if it can be edited --}}
      @if (!empty($pirep->simbrief))
        <a href="{{ url(route('frontend.simbrief.briefing', [$pirep->simbrief->id])) }}"
           class="btn btn-outline-info">View SimBrief</a>
      @endif

      @if(!$pirep->read_only && $user && $pirep->user_id === $user->id)
        <div class="float-end" style="margin-bottom: 10px;">
          <form method="get"
                action="{{ route('frontend.pireps.edit', $pirep->id) }}"
                style="display: inline">
            @csrf
            <button class="btn btn-outline-info">@lang('common.edit')</button>
          </form>
          &nbsp;
          <form method="post"
                action="{{ route('frontend.pireps.submit', $pirep->id) }}"
                style="display: inline">
            @csrf
            <button class="btn btn-outline-success">@lang('common.submit')</button>
          </form>
        </div>
      @endif
    </div>
  </div>

  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-column flex-md-row justify-content-between">
            {{-- DEPARTURE INFO --}}
            <div class="text-left">
              <h4>
                {{$pirep->dpt_airport->location}}
              </h4>
              <p>
                <a href="{{route('frontend.airports.show', $pirep->dpt_airport_id)}}">
                  {{ $pirep->dpt_airport->full_name }}</a>
                <br/>
                @if($pirep->block_off_time)
                  {{ $pirep->block_off_time->toDayDateTimeString() }}
                @endif
              </p>
            </div>

            {{-- ARRIVAL INFO --}}
            <div class="text-md-end text-left">
              <h4>
                {{$pirep->arr_airport->location}}
              </h4>
              <p>
                <a href="{{route('frontend.airports.show', $pirep->arr_airport_id)}}">
                  {{ $pirep->arr_airport->full_name }}</a>
                <br/>
                @if($pirep->block_on_time)
                  {{ $pirep->block_on_time->toDayDateTimeString() }}
                @endif
              </p>
            </div>
          </div>

          @if(!empty($pirep->distance))
            <div class="row">
              <div class="col-12">
                <div class="progress" style="margin: 20px 0;">
                  <div class="progress-bar @if($pirep->state === PirepState::IN_PROGRESS) bg-primary progress-bar-striped progress-bar-animated @else bg-success @endif" role="progressbar"
                       aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"
                       style="width: {{$pirep->progress_percent}}%;">
                  </div>
                </div>
              </div>
            </div>
          @endif
          </div>
          </div>

      @if(!empty($logData))
      @php
        $to      = $logData['takeoff']  ?? [];
        $ld      = $logData['landing']  ?? [];
        $ap      = $logData['approach'] ?? [];
        $bonuses = $logData['bonuses']  ?? [];
        $sc      = $logData['score'] ?? ($pirep->score !== null ? ['value' => $pirep->score, 'rating' => null] : null);
        $lrColor = isset($ld['vs_fpm'])
          ? ($ld['vs_fpm'] > -200 ? 'text-success' : ($ld['vs_fpm'] > -400 ? 'text-warning' : 'text-danger'))
          : '';
        // Unidentified penalties: score comes from DB but log has partial analysis
        $unidentifiedPts = null;
        if (!isset($logData['score']) && $sc !== null && (count($logData['penalties']) > 0 || count($bonuses) > 0)) {
            $penaltyTotal    = array_sum(array_column($logData['penalties'], 'points'));
            $bonusTotal      = array_sum(array_column($bonuses, 'points'));
            $diff            = (100 - $penaltyTotal + $bonusTotal) - $sc['value'];
            if ($diff !== 0) { $unidentifiedPts = $diff; }
        }
      @endphp

      {{-- Score + Penalties --}}
      @php $hasAnalysis = $sc || count($logData['penalties']) > 0 || count($bonuses) > 0; @endphp
      @if($hasAnalysis)
      <div class="card vholar-card mt-3">
        <div class="card-header d-flex align-items-center gap-2">
          <h5 class="mb-0"><i class="bi bi-graph-up"></i> Advanced Flight Analysis</h5>
          @if($logData['aircraft'])
            <span class="badge bg-secondary">{{ $logData['aircraft']['type'] }} — Vmo {{ $logData['aircraft']['vmo_kts'] }} kts</span>
          @endif
          @if($logData['network'])
            <span class="badge bg-info text-dark">{{ $logData['network']['name'] }} VID {{ $logData['network']['vid'] }}</span>
          @endif
        </div>
        <div class="card-body">
          <div class="row align-items-center">
            @if($sc)
            <div class="col-4 text-center border-end">
              <div class="display-4 fw-bold {{ $sc['value'] >= 80 ? 'text-success' : ($sc['value'] >= 60 ? 'text-warning' : 'text-danger') }}">
                {{ $sc['value'] }}
              </div>
              <div class="text-muted small">/100{{ $sc['rating'] ? ' — '.$sc['rating'] : '' }}</div>
              <div class="progress mt-2" style="height:8px">
                <div class="progress-bar {{ $sc['value'] >= 80 ? 'bg-success' : ($sc['value'] >= 60 ? 'bg-warning' : 'bg-danger') }}"
                     style="width:{{ $sc['value'] }}%"></div>
              </div>
            </div>
            @endif
            <div class="{{ $sc ? 'col-8' : 'col-12' }}">
              @if(count($logData['penalties']) > 0 || $unidentifiedPts !== null)
                <p class="small text-muted mb-1 fw-semibold">Penalizaciones:</p>
                <ul class="list-unstyled mb-1 small">
                  @foreach($logData['penalties'] as $p)
                    <li><span class="text-danger fw-bold me-1">−{{ $p['points'] }} pts</span>{{ $p['reason'] }}</li>
                  @endforeach
                  @if($unidentifiedPts !== null && $unidentifiedPts > 0)
                    <li class="text-muted fst-italic"><span class="text-danger fw-bold me-1">−{{ $unidentifiedPts }} pts</span>Penalizaciones no identificadas</li>
                  @endif
                </ul>
              @endif
              @if(count($bonuses) > 0)
                <p class="small text-muted mb-1 fw-semibold">Bonificaciones:</p>
                <ul class="list-unstyled mb-0 small">
                  @foreach($bonuses as $b)
                    <li><span class="text-success fw-bold me-1">+{{ $b['points'] }} pts</span>{{ $b['reason'] }}</li>
                  @endforeach
                </ul>
              @endif
              @if(count($logData['penalties']) === 0 && count($bonuses) === 0)
                <p class="text-success mb-0"><i class="bi bi-check-circle-fill me-1"></i>Sin penalizaciones</p>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endif

      {{-- Takeoff / Landing --}}
      <div class="row mt-3">
        <div class="col-6 mb-3">
          <div class="card vholar-card h-100">
            <div class="card-header">
              <h5 class="mb-0"><i class="bi bi-airplane-takeoff"></i> Takeoff</h5>
            </div>
            <div class="card-body p-0">
              @if(!empty($to))
                <table class="table table-sm table-borderless mb-0">
                  @if(isset($to['runway']))
                    <tr><th class="text-muted ps-3">Pista</th><td class="fw-semibold">{{ $to['runway'] }}
                      @if(isset($to['threshold_ft']))<small class="text-muted ms-1">+{{ $to['threshold_ft'] }} ft umbral</small>@endif
                      @if(isset($to['centerline_ft']))<small class="text-muted ms-1">| CL {{ $to['centerline_ft'] }} ft</small>@endif
                    </td></tr>
                  @endif
                  @if(isset($to['rotation_kts']))
                    <tr><th class="text-muted ps-3">Rotación</th><td>{{ $to['rotation_kts'] }} kts
                      @if(isset($to['pitch_deg']))<small class="text-muted ms-1">pitch {{ $to['pitch_deg'] }}°</small>@endif
                    </td></tr>
                  @endif
                  @if(isset($to['liftoff_kts']))
                    <tr><th class="text-muted ps-3">Liftoff</th><td>{{ $to['liftoff_kts'] }} kts</td></tr>
                  @endif
                  @if(isset($to['gs_kts']))
                    <tr><th class="text-muted ps-3">GS despegue</th><td>{{ $to['gs_kts'] }} kts</td></tr>
                  @endif
                  @if(isset($to['n1']))
                    <tr><th class="text-muted ps-3">N1</th><td>{{ $to['n1'][0] }}% / {{ $to['n1'][1] }}%</td></tr>
                  @endif
                  @if(isset($to['flaps_pct']))
                    <tr><th class="text-muted ps-3">Flaps</th><td>{{ $to['flaps_pct'] }}%</td></tr>
                  @endif
                  @if(isset($to['qnh_delta']))
                    <tr><th class="text-muted ps-3">QNH Δ</th>
                      <td class="{{ $to['qnh_delta'] == 0 ? 'text-success' : 'text-warning' }}">
                        {{ $to['qnh_delta'] > 0 ? '+' : '' }}{{ $to['qnh_delta'] }} hPa
                      </td>
                    </tr>
                  @endif
                  @if(isset($to['oat_c']))
                    <tr><th class="text-muted ps-3">OAT / Wind</th><td>{{ $to['oat_c'] }}°C | {{ $to['wind'] }}</td></tr>
                  @endif
                </table>
              @else
                <p class="text-muted p-3 mb-0">Sin datos de despegue</p>
              @endif
            </div>
          </div>
        </div>

        <div class="col-6 mb-3">
          <div class="card vholar-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0"><i class="bi bi-airplane-landing"></i> Landing</h5>
              @if($ap['stabilized'] !== null)
                <span class="badge {{ $ap['stabilized'] ? 'bg-success' : 'bg-danger' }}">
                  <i class="bi bi-{{ $ap['stabilized'] ? 'check-circle' : 'x-circle' }} me-1"></i>
                  {{ $ap['stabilized'] ? 'Stabilized' : 'Unstabilized' }}
                  @if(isset($ap['agl_ft'])) @ {{ $ap['agl_ft'] }} ft @endif
                </span>
              @endif
            </div>
            <div class="card-body p-0">
              @if(!empty($ld))
                <table class="table table-sm table-borderless mb-0">
                  @if(isset($ld['runway']))
                    <tr><th class="text-muted ps-3">Pista</th><td class="fw-semibold">{{ $ld['runway'] }}
                      @if(isset($ld['td_ft']))<small class="text-muted ms-1">+{{ $ld['td_ft'] }} ft umbral</small>@endif
                      @if(isset($ld['centerline_ft']))<small class="text-muted ms-1">| CL {{ $ld['centerline_ft'] }} ft</small>@endif
                    </td></tr>
                  @endif
                  @if(isset($ld['vs_fpm']))
                    <tr><th class="text-muted ps-3">Landing Rate</th>
                      <td class="fw-semibold {{ $lrColor }}">{{ number_format($ld['vs_fpm']) }} fpm
                        @if(isset($ld['gforce_label']))<small class="text-muted ms-1">({{ $ld['gforce_label'] }})</small>@endif
                      </td>
                    </tr>
                  @endif
                  @if(isset($ld['gforce']))
                    <tr><th class="text-muted ps-3">G-Force</th>
                      <td class="{{ $ld['gforce'] <= 1.2 ? 'text-success' : ($ld['gforce'] <= 1.5 ? 'text-warning' : 'text-danger') }}">
                        {{ $ld['gforce'] }} g
                      </td>
                    </tr>
                  @endif
                  @if(isset($ld['ias_kts']))
                    <tr><th class="text-muted ps-3">Velocidad</th><td>{{ $ld['ias_kts'] }} kts IAS / {{ $ld['gs_kts'] }} kts GS</td></tr>
                  @endif
                  @if(isset($ld['pitch_deg']))
                    <tr><th class="text-muted ps-3">Pitch / Bank</th><td>{{ $ld['pitch_deg'] }}° / {{ $ld['bank_deg'] ?? '—' }}°</td></tr>
                  @endif
                  @if(isset($ld['heading']))
                    <tr><th class="text-muted ps-3">Heading</th><td>{{ $ld['heading'] }}°</td></tr>
                  @endif
                  @if(isset($ld['flaps_pct']))
                    <tr><th class="text-muted ps-3">Flaps / Spoilers</th><td>{{ $ld['flaps_pct'] }}% / {{ $ld['spoilers_pct'] ?? '—' }}%</td></tr>
                  @endif
                  @if(isset($ld['autobrake']))
                    <tr><th class="text-muted ps-3">Autobrake</th><td>{{ $ld['autobrake'] }}</td></tr>
                  @endif
                  @if(isset($ld['reversers']))
                    <tr><th class="text-muted ps-3">Reversers</th><td>{{ $ld['reversers'][0] }}% / {{ $ld['reversers'][1] }}%</td></tr>
                  @endif
                  @if(isset($ld['oat_c']))
                    <tr><th class="text-muted ps-3">OAT / Wind</th><td>{{ $ld['oat_c'] }}°C | {{ $ld['wind'] }}</td></tr>
                  @endif
                </table>
              @else
                <p class="text-muted p-3 mb-0">Sin datos de aterrizaje</p>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Approach capture --}}
      @if(isset($ap['dist_nm']))
      <div class="card vholar-card mt-2 mb-3">
        <div class="card-body py-2 px-3 small text-muted">
          <i class="bi bi-geo-alt me-1"></i>
          Captura de aproximación — Pista <strong>{{ $ap['runway'] ?? '—' }}</strong>
          a <strong>{{ $ap['agl_ft'] }} ft AGL</strong> / <strong>{{ $ap['dist_nm'] }} NM</strong> del umbral
        </div>
      </div>
      @endif

      @endif {{-- /logData --}}
    </div>

    {{--

    RIGHT SIDEBAR

    --}}

    <div class="col-4">
      <div class="list-group">
      <div class="d-flex justify-content-between list-group-item">
        <span>Pilot</span>
        <a href="{{ route('frontend.profile.show', [$pirep->user_id]) }}">{{ $pirep->user->ident }} {{ $pirep->user->name_private }}</a>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>Aircraft</span>
        <a href="{{ route('DBasic.aircraft', $pirep->aircraft->registration) }}">{{ $pirep->aircraft->ident }}</a>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('common.state')</span>
        @php
        $stateClass = 'bg-info';
        if ($pirep->state === PirepState::PENDING) {
          $stateClass = 'bg-warning';
        } elseif ($pirep->state === PirepState::ACCEPTED) {
          $stateClass = 'bg-success';
        } elseif ($pirep->state === PirepState::REJECTED) {
          $stateClass = 'bg-danger';
        } elseif ($pirep->state === PirepState::IN_PROGRESS) {
          $stateClass = 'bg-primary';
        }
        @endphp
        <span class="badge {{ $stateClass }}">
        {{ PirepState::label($pirep->state) }}
        </span>
      </div>

      @if ($pirep->state !== PirepState::DRAFT)
      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('common.status')</span>
        @php
        $statusClass = 'bg-info';
        if ($pirep->status === PirepStatus::SCHEDULED) {
          $statusClass = 'bg-secondary';
        } elseif ($pirep->status === PirepStatus::ENROUTE) {
          $statusClass = 'bg-primary';
        } elseif ($pirep->status === PirepStatus::ARRIVED) {
          $statusClass = 'bg-success';
        } elseif ($pirep->status === PirepStatus::CANCELLED) {
          $statusClass = 'bg-danger';
        } elseif ($pirep->status === PirepStatus::DIVERTED) {
          $statusClass = 'bg-warning';
        }
        @endphp
        <span class="badge {{ $statusClass }}">
        {{ PirepStatus::label($pirep->status) }}
        </span>
      </div>
      @endif

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.source')</span>
        <span>{{ PirepSource::label($pirep->source) }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('flights.flighttype')</span>
        <span>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.filedroute')</span>
        <span>{{ $pirep->route }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>{{ trans_choice('common.note', 2) }}</span>
        <span>{{ $pirep->notes }}</span>
      </div>

<div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.filedon')</span>
        <span>{{ show_datetime($pirep->created_at) }}</span>
      </div>
      </div>

      @if(count($pirep->fields) > 0)
      <div class="separator"></div>
      @endif

      @if(count($pirep->fields) > 0)
      <h5>{{ trans_choice('common.field', 2) }}</h5>
      <div class="list-group">
        @foreach($pirep->fields as $field)
        <div class="d-flex justify-content-between list-group-item">
          <span>{{ $field->name }}</span>
          <span>{{ $field->value }}</span>
        </div>
        @endforeach
      </div>
      @endif

      @if(count($pirep->fares) > 0)
      <div class="separator"></div>
      <div class="row">
        <div class="col-12">
        <h5>{{ trans_choice('pireps.fare', 2) }}</h5>
        <div class="list-group">
          @foreach($pirep->fares as $fare)
          <div class="d-flex justify-content-between list-group-item">
            <span>{{ $fare->name }} ({{ $fare->code }})</span>
            <span>{{ $fare->count }}</span>
          </div>
          @endforeach
        </div>
        </div>
      </div>
      @endif
    </div>
  </div>

{{-- MAP (full width) --}}
<div class="separator"></div>
<div class="row">
  <div class="col-12">
    @include('pireps.map')
  </div>
</div>

  @if(count($pirep->acars_logs) > 0)
    <div class="separator"></div>
    <div class="row">
      <div class="col-12">
        <h5>@lang('pireps.flightlog')</h5>
      </div>
      <div class="col-12">
        <table class="table table-hover table-condensed" id="users-table">
          <tbody>
          @foreach($pirep->acars_logs->sortBy('created_at') as $log)
            <tr>
              <td class="text-nowrap small text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
              <td>{{ $log->log }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if(!empty($pirep->simbrief))
    <div class="separator"></div>
    <div class="row mt-5">
      <div class="col-12">
        <div class="form-container">
          <h6><i class="fas fa-info-circle"></i>
            &nbsp;OFP
          </h6>
          <div class="form-container-body border border-dark">
            <div class="overflow-auto" style="height: 600px;">
              {!! $pirep->simbrief->xml->text->plan_html !!}
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
{{-- ============================================================ --}}
{{-- ALTITUDE PROFILE CHART (independiente de logData)          --}}
{{-- ============================================================ --}}
@if($altitudeProfile && $altitudeProfile->count() > 0)
<div class="separator"></div>
<div class="row">
    <div class="col-12 mb-3">
        <div class="card vholar-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Altitude Profile</h5>
            </div>
            <div class="card-body">
                <canvas id="altitudeChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const altitudeData = @json($altitudeProfile);
new Chart(document.getElementById('altitudeChart'), {
    type: 'line',
    data: {
        labels: altitudeData.map((d, i) => i + 1),
        datasets: [{
            label: 'Altitude (ft)',
            data: altitudeData.map(d => d.altitude_msl),
            borderColor: '#8FA6D9',
            backgroundColor: 'rgba(143, 166, 217, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { labels: { color: '#EDEAF1' } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const point = altitudeData[context.dataIndex];
                        return [
                            `Altitude: ${Math.round(point.altitude_msl)} ft`,
                            `Speed: ${point.gs ?? 'N/A'} kt`,
                            `Phase: ${point.phase ?? 'N/A'}`
                        ];
                    }
                }
            }
        },
        scales: {
            y: {
                title: { display: true, text: 'Altitude (ft)', color: '#A79FB2' },
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#A79FB2' }
            },
            x: {
                title: { display: true, text: 'Sequence', color: '#A79FB2' },
                grid: { color: 'rgba(255,255,255,0.1)' },
                ticks: { color: '#A79FB2' }
            }
        }
    }
});
</script>
@endpush
@endif {{-- /altitudeProfile --}}

@if($pirep->comments->count() > 0)
<div class="separator"></div>
<div class="row">
  <div class="col-12">
    <div class="card vholar-card">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti-comment-alt"></i>
        <h5 class="mb-0">Observaciones</h5>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          @foreach($pirep->comments as $comment)
            <li class="list-group-item" style="background:transparent; border-color:var(--vh-border); color:var(--vh-text);">
              <div class="d-flex justify-content-between align-items-start">
                <span>{{ $comment->comment }}</span>
                <small class="text-muted ms-3 text-nowrap">{{ $comment->created_at->format('d M Y H:i') }}</small>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>
@endif

{{-- CSS compartido para las cards vholar --}}
@once
<style>
.vholar-card {
    background: linear-gradient(135deg, var(--vh-surface-2) 0%, var(--vh-surface) 100%);
    border: 1px solid var(--vh-primary);
    border-radius: 12px;
    overflow: hidden;
}
.vholar-card .card-header {
    background: var(--vh-primary-soft);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.vholar-card .card-header h5 {
    color: var(--vh-white);
}
</style>
@endonce

@endsection
