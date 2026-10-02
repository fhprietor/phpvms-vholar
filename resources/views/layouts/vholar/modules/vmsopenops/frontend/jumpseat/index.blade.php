@extends('app')
@section('title', __('common.jumpseat_requests'))

@section('content')
@once
@include('vholar::pireps.logbook-styles')
@endonce

<div class="row mb-3">
  <div class="col d-flex align-items-center justify-content-between">
    <div>
      <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:var(--vh-text-muted);">
        ✈ &nbsp;@lang('common.jumpseat_requests')
      </h4>
    </div>
    <div>
      @if(!$pendingRequest)
        <a href="{{ route('vmsopenops.jumpseat.create') }}" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-lg"></i> @lang('common.new_jumpseat')
        </a>
      @else
        <button class="btn btn-sm btn-secondary" disabled>
          <i class="bi bi-hourglass-split"></i> @lang('common.pending_jumpseat')
        </button>
      @endif
    </div>
  </div>
</div>

@include('flash::message')

@if($pendingRequest)
  <div class="alert alert-warning d-flex gap-3 mb-3" style="background:var(--vh-warning-soft);border:1px solid rgba(224,168,46,0.35);color:var(--vh-text);border-radius:8px;">
    <div style="font-size:1.4rem;line-height:1;">⚠</div>
    <div>
      <div style="font-weight:700;margin-bottom:4px;">@lang('common.pending_jumpseat')</div>
      <div style="font-size:0.8rem;color:var(--vh-warning);">
        <span class="lb-icao">{{ $pendingRequest->from_airport_id }}</span>
        <span class="lb-route-arrow">→</span>
        <span class="lb-icao">{{ $pendingRequest->to_airport_id }}</span>
        &nbsp;·&nbsp; {{ number_format($pendingRequest->distance, 0) }} NM
        &nbsp;·&nbsp; {{ $pendingRequest->cost_formatted }}
        &nbsp;·&nbsp; {{ $pendingRequest->created_at->format('Y-m-d H:i') }}
      </div>
    </div>
  </div>
@endif

@if($requests->count() > 0)
  <div class="vholar-logbook-wrap">
    <div class="table-responsive">
      <table class="table vholar-logbook">
        <thead>
          <tr>
            <th>@lang('common.date')</th>
            <th>@lang('flights.route')</th>
            <th class="text-end">@lang('common.distance')</th>
            <th class="text-end">@lang('common.cost')</th>
            <th class="text-center">@lang('common.type')</th>
            <th class="text-center">@lang('common.status')</th>
            <th class="text-center">@lang('common.actions')</th>
          </tr>
        </thead>
        <tbody>
          @foreach($requests as $request)
            @php
              $day   = $request->created_at->format('d');
              $mon   = $request->created_at->format('M');
              $yr    = $request->created_at->format('Y');
              $statusBadge = match($request->status) {
                0 => 'bg-warning text-dark',
                1 => 'bg-success',
                2 => 'bg-danger',
                default => 'bg-secondary',
              };
            @endphp
            <tr>
              {{-- Date --}}
              <td class="lb-date" style="min-width:70px;">
                <span class="lb-day">{{ $day }}</span>
                <span class="lb-monyear">{{ $mon }}<br>{{ $yr }}</span>
              </td>

              {{-- Route --}}
              <td>
                <span class="lb-icao">{{ $request->from_airport_id }}</span>
                <span class="lb-route-arrow">→</span>
                <span class="lb-icao">{{ $request->to_airport_id }}</span>
              </td>

              {{-- Distance --}}
              <td class="text-end">
                <span class="lb-stat-val" style="font-size:0.92rem;">{{ number_format($request->distance, 0) }}</span>
                <span style="font-size:0.65rem;color:var(--vh-text-muted);margin-left:2px;">NM</span>
              </td>

              {{-- Cost --}}
              <td class="text-end">
                <span style="font-size:0.88rem;font-weight:700;color:var(--vh-text);font-variant-numeric:tabular-nums;">{{ $request->cost_formatted }}</span>
              </td>

              {{-- Type --}}
              <td class="text-center">
                <span class="lb-ac-type">{{ $request->type_text }}</span>
              </td>

              {{-- Status --}}
              <td class="text-center">
                <span class="lb-state {{ $statusBadge }}">{{ $request->status_text }}</span>
              </td>

              {{-- Actions --}}
              <td class="text-center">
                @if($request->status == 0)
                  <form action="{{ route('vmsopenops.jumpseat.cancel', $request->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="lb-edit-btn"
                            data-vh-confirm="{{ __('common.cancel_request_confirm') }}">
                      @lang('common.cancel')
                    </button>
                  </form>
                @endif
                @if($request->status == 2 && $request->admin_notes)
                  <button type="button" class="lb-edit-btn"
                          data-bs-toggle="modal" data-bs-target="#notesModal{{ $request->id }}">
                    @lang('common.notes')
                  </button>
                  <div class="modal fade" id="notesModal{{ $request->id }}" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content" style="background:var(--vh-surface);border:1px solid var(--vh-border);">
                        <div class="modal-header" style="border-bottom:1px solid var(--vh-border);">
                          <h5 class="modal-title" style="color:var(--vh-text);">@lang('common.admin_notes')</h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="color:var(--vh-text-muted);">
                          {{ $request->admin_notes }}
                        </div>
                      </div>
                    </div>
                  </div>
                @endif
                @if($request->status != 0 && !($request->status == 2 && $request->admin_notes))
                  <span class="text-muted" style="font-size:0.75rem;">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3 text-center">
    {{ $requests->links('pagination.bootstrap-5') }}
  </div>
@else
  <div class="vholar-logbook-wrap" style="padding:2rem;text-align:center;color:var(--vh-text-muted);">
    <div style="font-size:2rem;margin-bottom:0.5rem;">✈</div>
    <div style="font-size:0.85rem;letter-spacing:0.05em;text-transform:uppercase;">@lang('common.no_jumpseat_requests')</div>
    <a href="{{ route('vmsopenops.jumpseat.create') }}" style="display:inline-block;margin-top:1rem;font-size:0.8rem;color:var(--vh-silver-dim);">
      @lang('common.create_first_jumpseat')
    </a>
  </div>
@endif

@endsection
