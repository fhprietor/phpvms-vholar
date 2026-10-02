@extends('app')
@section('title', trans_choice('common.pirep', 2))

@section('content')
  <div class="row mb-3">
    <div class="col-md-12 d-flex align-items-center justify-content-between">
      <div>
        <h4 class="mb-0" style="font-weight:800; letter-spacing:0.04em; text-transform:uppercase; font-size:0.85rem; color:var(--vh-text-muted);">
          ✈ &nbsp;Pilot Logbook
        </h4>
        <p class="mb-0" style="font-size:0.75rem; color:var(--vh-text-muted); letter-spacing:0.05em;">
          {{ $user->name }} &mdash; {{ trans_choice('pireps.pilotreport', 2) }}
        </p>
      </div>
      <a class="btn btn-outline-info btn-sm" href="{{ route('frontend.pireps.create') }}"
         style="font-size:0.75rem; letter-spacing:0.06em; text-transform:uppercase; font-weight:700;">
        + @lang('pireps.filenewpirep')
      </a>
    </div>
  </div>

  @include('flash::message')
  @include('pireps.table')
@endsection
