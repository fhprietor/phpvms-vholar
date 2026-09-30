@extends('app')
@section('title', __('common.new_jumpseat'))

@section('content')
@once
@include('vholar::pireps.logbook-styles')
@endonce

<div class="row mb-3">
  <div class="col d-flex align-items-center justify-content-between">
    <div>
      <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:#9898b0;">
        ✈ &nbsp;@lang('common.new_jumpseat')
      </h4>
    </div>
    <div>
      <a href="{{ route('vmsopenops.jumpseat.index') }}" class="btn btn-sm btn-secondary">
        <i class="bi bi-arrow-left"></i> @lang('common.cancel')
      </a>
    </div>
  </div>
</div>

@include('flash::message')

<div class="row g-3">
  <div class="col-12 col-lg-6">

    {{-- Info panel --}}
    <div class="vholar-logbook-wrap mb-3" style="padding:1.1rem 1.4rem;">
      @php $currentAirport = \App\Models\Airport::find($user->curr_airport_id); @endphp
      <div style="font-size:0.8rem;color:#9898b0;margin-bottom:0.5rem;">
        <i class="bi bi-geo-alt" style="color:#7a6aaa;margin-right:4px;"></i>
        <span style="font-weight:600;color:#a8a8c0;">@lang('common.current_location'):</span>
        &nbsp;<span class="lb-icao">{{ $user->curr_airport_id }}</span>
        @if($currentAirport)
          <span style="color:#9090a8;font-size:0.72rem;"> — {{ $currentAirport->name }}</span>
        @endif
      </div>
      <div style="font-size:0.8rem;color:#9898b0;">
        <i class="bi bi-wallet2" style="color:#7a6aaa;margin-right:4px;"></i>
        <span style="font-weight:600;color:#a8a8c0;">@lang('common.current_balance'):</span>
        &nbsp;<span style="color:#c8d8ff;font-weight:700;">{{ $user->journal->balance->money->format() ?? '$0.00' }}</span>
      </div>
    </div>

    {{-- Form --}}
    <div class="vholar-logbook-wrap" style="padding:1.25rem 1.5rem;">
      <form method="POST" action="{{ route('vmsopenops.jumpseat.store') }}">
        {{ csrf_field() }}

        {{-- Type --}}
        <div class="mb-4">
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="type" value="0" id="typeRequest">
            <label class="form-check-label" for="typeRequest" style="color:#a0a8c0;font-size:0.85rem;">
              @lang('vmsopenops.make_jumpseat_request')
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="type" id="typeImmediate" value="1" checked>
            <label class="form-check-label" for="typeImmediate" style="color:#a0a8c0;font-size:0.85rem;">
              @lang('vmsopenops.pay_immediate_jumpseat') (<span id="immediateCostLabel">$0.00</span>)
            </label>
          </div>
        </div>

        {{-- Destination airport --}}
        <div class="mb-4">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.airport')
          </label>
          <select class="custom-select airport_search" name="to_airport_id" required style="width:100%;"></select>
        </div>

        {{-- Preview panel --}}
        <div id="previewPanel" style="display:none;margin-bottom:1.25rem;">
          <div style="background:rgba(40,60,100,0.18);border:1px solid rgba(80,120,200,0.2);border-radius:8px;padding:1rem;">
            <div style="font-size:0.65rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#7a8aaa;margin-bottom:0.75rem;">
              @lang('vmsopenops.jumpseat_preview')
            </div>
            <div class="d-flex gap-4 mb-1">
              <div>
                <div style="font-size:0.62rem;text-transform:uppercase;color:#9090a8;letter-spacing:0.06em;">@lang('common.distance')</div>
                <span id="distanceDisplay" class="lb-time" style="font-size:0.92rem;">0</span>
                <span style="font-size:0.65rem;color:#9090a8;"> NM</span>
              </div>
              <div>
                <div style="font-size:0.62rem;text-transform:uppercase;color:#9090a8;letter-spacing:0.06em;">@lang('common.cost')</div>
                <span id="costDisplay" class="lb-time" style="font-size:0.92rem;">$0.00</span>
              </div>
            </div>
            <div id="sameAirportWarning" style="display:none;margin-top:8px;font-size:0.78rem;color:#e8d080;">
              <i class="bi bi-exclamation-triangle"></i> @lang('vmsopenops.already_at_airport')
            </div>
            <div id="insufficientFundsWarning" style="display:none;margin-top:8px;font-size:0.78rem;color:#e08080;">
              <i class="bi bi-exclamation-circle"></i> @lang('vmsopenops.insufficient_funds_jumpseat')
            </div>
          </div>
        </div>

        {{-- Reason --}}
        <div class="mb-4">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.reason_optional')
          </label>
          <input class="form-control" name="reason"
                 placeholder="{{ __('common.optional_reason_placeholder') }}"
                 style="background:#1a1828;border:1px solid rgba(120,100,180,0.3);color:#c0c8e8;border-radius:6px;font-size:0.85rem;padding:7px 12px;"/>
        </div>

        {{-- Submit --}}
        <div class="d-flex gap-2">
          <button class="btn btn-primary btn-sm" style="font-size:0.8rem;font-weight:600;">
            <i class="bi bi-send"></i> @lang('common.submit')
          </button>
          <a href="{{ route('vmsopenops.jumpseat.index') }}" class="btn btn-secondary btn-sm" style="font-size:0.8rem;">
            @lang('common.cancel')
          </a>
        </div>

      </form>
    </div>

  </div>
</div>

@endsection

@push('scripts')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<style>
.select2-container--default .select2-selection--single {
    background-color: #1a1828 !important; border: 1px solid rgba(120,100,180,0.3) !important;
    border-radius: 6px !important; height: 36px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #c0c8e8 !important; line-height: 36px !important; font-size: 0.85rem;
}
.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #7a6aaa transparent transparent transparent !important;
}
.select2-dropdown { background-color: #1a1828 !important; border: 1px solid rgba(120,100,180,0.3) !important; }
.select2-search--dropdown { background-color: #1a1828 !important; }
.select2-search--dropdown .select2-search__field {
    background-color: #12101e !important; border: 1px solid rgba(120,100,180,0.3) !important; color: #c0c8e8 !important;
}
.select2-results__options { background-color: #1a1828 !important; }
.select2-results__option { color: #a0a8c0 !important; background-color: #1a1828 !important; }
.select2-results__option--highlighted,
.select2-results__option--highlighted[aria-selected] { background-color: rgba(80,60,140,0.5) !important; color: #dce4ff !important; }
.select2-results__option[aria-selected="true"] { background-color: rgba(60,40,100,0.5) !important; color: #90aaff !important; }
</style>

<script>
$(document).ready(function () {
    let currentPreviewData = null;

    $('select.airport_search').select2({
        dropdownParent: $('body'),
        ajax: {
            url: '{{ Config::get("app.url") }}/api/airports/search',
            data: function (params) {
                return { search: params.term, hubs: 0, page: params.page || 1, orderBy: 'id', sortedBy: 'asc' };
            },
            processResults: function (data) {
                if (!data.data) return { results: [] };
                return {
                    results: data.data.map(apt => ({ id: apt.id, text: apt.description })),
                    pagination: { more: data.meta && data.meta.next_page !== null }
                };
            },
            cache: true, dataType: 'json', delay: 250, minimumInputLength: 2,
        },
        width: '100%',
        placeholder: '{{ __("vmsopenops.select_airport_placeholder") }}'
    }).on('change', function () {
        const airportId = $(this).val();
        if (airportId) { previewJumpseat(airportId); }
        else { $('#previewPanel').hide(); }
    });

    function previewJumpseat(airportId) {
        $.ajax({
            url: '{{ route("api.vmsopenops.api.jumpseat.preview") }}',
            method: 'POST',
            data: { to_airport_id: airportId, _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response.success) {
                    currentPreviewData = response.data;
                    $('#distanceDisplay').text(response.data.distance.value.toFixed(2));
                    $('#costDisplay').text(response.data.cost.formatted);
                    $('#previewPanel').show();
                    if (response.data.is_same_airport) {
                        $('#sameAirportWarning').show();
                        $('#insufficientFundsWarning').hide();
                        $('button[type="submit"]').prop('disabled', true);
                    } else {
                        $('#sameAirportWarning').hide();
                        $('button[type="submit"]').prop('disabled', false);
                        if (!response.data.can_pay_immediately && $('#typeImmediate').is(':checked')) {
                            $('#insufficientFundsWarning').show();
                        } else {
                            $('#insufficientFundsWarning').hide();
                        }
                    }
                    updateImmediateCost();
                }
            },
            error: function (xhr) { console.error('Preview error:', xhr); $('#previewPanel').hide(); }
        });
    }

    function updateImmediateCost() {
        if (currentPreviewData && $('#typeImmediate').is(':checked')) {
            $('#immediateCostLabel').text(currentPreviewData.cost.formatted);
        }
    }

    $('input[name="type"]').on('change', function () {
        updateImmediateCost();
        if ($('#typeImmediate').is(':checked') && currentPreviewData && !currentPreviewData.can_pay_immediately && !currentPreviewData.is_same_airport) {
            $('#insufficientFundsWarning').show();
        } else {
            $('#insufficientFundsWarning').hide();
        }
    });

    $('form').on('submit', function (e) {
        const airportId = $('.airport_search').val();
        if (!airportId) {
            e.preventDefault();
            alert('{{ __("vmsopenops.alert_select_airport") }}');
            return false;
        }
        @if(setting('vms_open_ops_require_reason', true))
        const reason = $('input[name="reason"]').val().trim();
        if (!reason) {
            e.preventDefault();
            alert('{{ __("vmsopenops.alert_reason_jumpseat") }}');
            return false;
        }
        @endif
        return true;
    });
});
</script>
@endpush
