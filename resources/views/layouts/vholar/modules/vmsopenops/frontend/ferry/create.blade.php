@extends('app')
@section('title', __('vmsopenops.new_ferry_request'))

@section('content')
@once
@include('vholar::pireps.logbook-styles')
@endonce

<div class="row mb-3">
  <div class="col d-flex align-items-center justify-content-between">
    <div>
      <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:#9898b0;">
        ✈ &nbsp;@lang('vmsopenops.new_ferry_request')
      </h4>
    </div>
    <div>
      <a href="{{ route('vmsopenops.ferry.index') }}" class="btn btn-sm btn-secondary">
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
        &nbsp;<span style="color:#c8d8ff;font-weight:700;">{{ $balance->money->format() ?? '$0.00' }}</span>
      </div>
    </div>

    {{-- Form --}}
    <div class="vholar-logbook-wrap" style="padding:1.25rem 1.5rem;">
      <form method="POST" action="{{ route('vmsopenops.ferry.store') }}" id="ferryForm">
        {{ csrf_field() }}

        {{-- Aircraft type --}}
        <div class="mb-3">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.aircraft_type')
          </label>
          <select name="subfleet_id" id="subfleet_id" class="form-control" required
                  style="background:#1a1828;border:1px solid rgba(120,100,180,0.3);color:#c0c8e8;border-radius:6px;font-size:0.85rem;padding:7px 12px;">
            <option value="">@lang('vmsopenops.select_aircraft_type')</option>
            @foreach($subfleets as $subfleet)
              <option value="{{ $subfleet->id }}">{{ $subfleet->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Aircraft --}}
        <div class="mb-3" id="aircraftGroup" style="display:none;">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.aircraft')
          </label>
          <select name="aircraft_id" id="aircraft_id" class="form-control" required
                  style="background:#1a1828;border:1px solid rgba(120,100,180,0.3);color:#c0c8e8;border-radius:6px;font-size:0.85rem;padding:7px 12px;">
            <option value="">@lang('vmsopenops.select_aircraft')</option>
          </select>
        </div>

        {{-- Preview panel --}}
        <div id="previewPanel" style="display:none;margin-bottom:1.25rem;">
          <div style="background:rgba(40,60,100,0.18);border:1px solid rgba(80,120,200,0.2);border-radius:8px;padding:1rem;">
            <div style="font-size:0.65rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#7a8aaa;margin-bottom:0.75rem;">
              @lang('vmsopenops.ferry_preview')
            </div>
            <div class="d-flex gap-4 mb-1">
              <div>
                <div style="font-size:0.62rem;text-transform:uppercase;color:#9090a8;letter-spacing:0.06em;">@lang('common.distance')</div>
                <span id="distanceDisplay" class="lb-time" style="font-size:0.92rem;">0</span>
              </div>
              <div>
                <div style="font-size:0.62rem;text-transform:uppercase;color:#9090a8;letter-spacing:0.06em;">@lang('common.cost')</div>
                <span id="costDisplay" class="lb-time" style="font-size:0.92rem;">$0.00</span>
              </div>
            </div>
            <div id="sameAirportWarning" style="display:none;margin-top:8px;font-size:0.78rem;color:#e8d080;">
              <i class="bi bi-exclamation-triangle"></i> @lang('vmsopenops.aircraft_already_here')
            </div>
            <div id="insufficientFundsWarning" style="display:none;margin-top:8px;font-size:0.78rem;color:#e08080;">
              <i class="bi bi-exclamation-circle"></i> @lang('vmsopenops.insufficient_funds_ferry')
            </div>
          </div>
        </div>

        {{-- Payment type --}}
        <div class="mb-4">
          <div class="form-check mb-2">
            <input type="radio" name="type" id="typeRequest" value="0" class="form-check-input">
            <label class="form-check-label" for="typeRequest" style="color:#a0a8c0;font-size:0.85rem;">
              @lang('vmsopenops.submit_for_approval')
            </label>
          </div>
          <div class="form-check">
            <input type="radio" name="type" id="typeImmediate" value="1" class="form-check-input" checked>
            <label class="form-check-label" for="typeImmediate" style="color:#a0a8c0;font-size:0.85rem;">
              @lang('vmsopenops.pay_immediately') (<span id="immediateCostLabel">$0.00</span>)
            </label>
          </div>
        </div>

        {{-- Reason --}}
        @if($requireReason)
        <div class="mb-4">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.reason') *
          </label>
          <textarea name="reason" rows="3" maxlength="{{ $maxReasonLength }}"
                    placeholder="{{ __('vmsopenops.reason_ferry_placeholder') }}"
                    style="width:100%;background:#1a1828;border:1px solid rgba(120,100,180,0.3);color:#c0c8e8;border-radius:6px;font-size:0.85rem;padding:7px 12px;resize:vertical;"></textarea>
          <small style="font-size:0.68rem;color:#9090a8;">{{ __('common.max_characters', ['n' => $maxReasonLength]) }}</small>
        </div>
        @else
        <div class="mb-4">
          <label style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.07em;color:#9090a8;margin-bottom:6px;display:block;">
            @lang('common.reason_optional')
          </label>
          <textarea name="reason" rows="3" maxlength="{{ $maxReasonLength }}"
                    placeholder="{{ __('common.optional_reason_placeholder') }}"
                    style="width:100%;background:#1a1828;border:1px solid rgba(120,100,180,0.3);color:#c0c8e8;border-radius:6px;font-size:0.85rem;padding:7px 12px;resize:vertical;"></textarea>
          <small style="font-size:0.68rem;color:#9090a8;">{{ __('common.max_characters', ['n' => $maxReasonLength]) }}</small>
        </div>
        @endif

        {{-- Submit --}}
        <div class="d-flex gap-2">
          <button type="submit" id="submitBtn" class="btn btn-primary btn-sm" style="font-size:0.8rem;font-weight:600;">
            <i class="bi bi-send"></i> @lang('common.submit_request')
          </button>
          <a href="{{ route('vmsopenops.ferry.index') }}" class="btn btn-secondary btn-sm" style="font-size:0.8rem;">
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
    let selectedAircraftId = null;

    $('#subfleet_id').select2({
        width: '100%',
        placeholder: '{{ __("vmsopenops.select_aircraft_type") }}',
        allowClear: true
    }).on('change', function () {
        const subfleetId = $(this).val();
        if (subfleetId) { loadAircraft(subfleetId); }
        else { $('#aircraftGroup').hide(); $('#aircraft_id').empty(); $('#previewPanel').hide(); }
    });

    function loadAircraft(subfleetId) {
        $('#aircraft_id').html('<option value="">{{ __("vmsopenops.loading_aircraft") }}</option>');
        $('#aircraftGroup').show();

        $.ajax({
            url: '/api/vmsopenops/ferry/available',
            method: 'POST',
            data: { subfleet_id: subfleetId, _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response.success && response.aircraft.length > 0) {
                    const aircraftSelect = $('#aircraft_id');
                    aircraftSelect.empty();
                    aircraftSelect.append('<option value="">{{ __("vmsopenops.select_aircraft") }}</option>');
                    response.aircraft.forEach(function (ac) {
                        aircraftSelect.append(
                            `<option value="${ac.id}"
                                data-distance="${ac.distance}"
                                data-cost="${ac.cost}"
                                data-cost-formatted="${ac.cost_formatted}"
                                data-registration="${ac.registration}"
                                data-current-airport="${ac.current_airport}">
                                ${ac.registration} - ${ac.name} (${ac.current_airport}, ${ac.distance.toFixed(2)} NM)
                            </option>`
                        );
                    });
                    aircraftSelect.select2({
                        width: '100%',
                        placeholder: '{{ __("vmsopenops.select_aircraft") }}',
                        allowClear: true
                    }).on('change', function () {
                        const selected = $(this).find(':selected');
                        if (selected.val()) { previewFerry(selected); }
                        else { $('#previewPanel').hide(); selectedAircraftId = null; }
                    });
                } else {
                    $('#aircraft_id').html('<option value="">{{ __("vmsopenops.no_aircraft_available") }}</option>');
                    $('#previewPanel').hide();
                    if (response.message) { alert(response.message); }
                }
            },
            error: function (xhr) {
                let errorMessage = 'Error loading aircraft. ';
                if (xhr.responseJSON && xhr.responseJSON.message) { errorMessage += xhr.responseJSON.message; }
                $('#aircraft_id').html('<option value="">{{ __("vmsopenops.no_aircraft_available") }}</option>');
                $('#previewPanel').hide();
                alert(errorMessage);
            }
        });
    }

    function previewFerry(selectedOption) {
        const aircraftId = selectedOption.val();
        $.ajax({
            url: '{{ route("api.vmsopenops.api.ferry.preview") }}',
            method: 'POST',
            data: { aircraft_id: aircraftId, _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response.success) {
                    currentPreviewData = response.data;
                    selectedAircraftId = aircraftId;
                    $('#distanceDisplay').text(response.data.distance.value.toFixed(2) + ' NM');
                    $('#costDisplay').text('$' + (response.data.cost.value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#previewPanel').show();
                    if (response.data.is_same_airport) {
                        $('#sameAirportWarning').show();
                        $('#insufficientFundsWarning').hide();
                        $('#submitBtn').prop('disabled', true);
                    } else {
                        $('#sameAirportWarning').hide();
                        $('#submitBtn').prop('disabled', false);
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
            $('#immediateCostLabel').text('$' + (currentPreviewData.cost.value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
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

    $('#ferryForm').on('submit', function (e) {
        const aircraftId = $('#aircraft_id').val();
        if (!aircraftId) {
            e.preventDefault();
            alert('{{ __("vmsopenops.alert_select_aircraft") }}');
            return false;
        }
        @if($requireReason)
        const reason = $('textarea[name="reason"]').val().trim();
        if (!reason) {
            e.preventDefault();
            alert('{{ __("vmsopenops.alert_reason_ferry") }}');
            return false;
        }
        @endif
        return true;
    });
});
</script>
@endpush
