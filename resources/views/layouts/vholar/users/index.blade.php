@extends('app')
@section('title', trans_choice('common.pilot', 2))

@section('content')
  <div class="row mb-3">
    <div class="col d-flex align-items-center justify-content-between">
      <div>
        <h4 class="mb-0" style="font-weight:800;letter-spacing:0.04em;text-transform:uppercase;font-size:0.85rem;color:var(--vh-text-muted);">
          ✈ &nbsp;{{ trans_choice('common.pilot', 2) }}
        </h4>
        @if($users->total())
          <p class="mb-0" style="font-size:0.72rem;color:var(--vh-text-muted);letter-spacing:0.05em;">
            {{ $users->total() }} {{ trans_choice('common.pilot', $users->total()) }}
          </p>
        @endif
      </div>
    </div>
  </div>

  @include('users.table')

  <div class="mt-3 text-center">
    {{ $users->withQueryString()->links('pagination.bootstrap-5') }}
  </div>
@endsection
