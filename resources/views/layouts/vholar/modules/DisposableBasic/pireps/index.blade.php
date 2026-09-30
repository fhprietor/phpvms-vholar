@extends('app')
@section('title', trans_choice('common.pirep', 2))

@section('content')
  <div class="row">
    <div class="col">
      <div class="row mb-3">
        <div class="col d-flex align-items-center justify-content-between">
          <div>
            <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:#9898b0;">
              ✈ &nbsp;@lang('DBasic::common.reports')
            </h4>
            @if($pireps->total())
              <p class="mb-0" style="font-size:0.72rem;color:#9090a8;letter-spacing:0.05em;">
                {{ $pireps->firstItem() }}–{{ $pireps->lastItem() }} / {{ $pireps->total() }} @lang('DBasic::common.reports')
              </p>
            @endif
          </div>
        </div>
      </div>

      <div class="card" style="border-radius:10px;overflow:hidden;">
        <div class="card-body p-0">
          @if(!$pireps->count())
            <div class="p-3 text-muted text-center" style="font-size:0.85rem;">@lang('common.none')</div>
          @else
            <div style="max-height:75vh;overflow-y:auto;">
              @include('DBasic::pireps.table')
            </div>
          @endif
        </div>

        @if($pireps->hasPages())
          <div class="card-footer p-3">
            {{ $pireps->withQueryString()->links('pagination.bootstrap-5') }}
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection
