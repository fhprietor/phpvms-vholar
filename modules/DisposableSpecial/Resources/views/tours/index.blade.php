@extends('app')
@section('title', 'Tours')

@section('content')
  @if(!$tours->count())
    <div class="alert alert-info p-1 fw-bold">No Tours Found!</div>
  @else
    <ul class="nav nav-pills nav-justified mb-3" id="pills-tab" role="tablist">
      <li class="nav-item mx-1" role="presentation">
        <a class="nav-link p-1 active" id="pills-activet-tab" data-bs-toggle="pill" href="#pills-activet" role="tab" aria-controls="pills-activet" aria-selected="true">
          @lang('DSpecial::tours.current')
        </a>
      </li>
      <li class="nav-item mx-1" role="presentation">
        <a class="nav-link p-1" id="pills-futuret-tab" data-bs-toggle="pill" href="#pills-futuret" role="tab" aria-controls="pills-futuret" aria-selected="false">
          @lang('DSpecial::tours.future')
        </a>
      </li>
      <li class="nav-item mx-1" role="presentation">
        <a class="nav-link p-1" id="pills-closedt-tab" data-bs-toggle="pill" href="#pills-closedt" role="tab" aria-controls="pills-closedt" aria-selected="false">
          @lang('DSpecial::tours.past')
        </a>
      </li>
      <li class="nav-item mx-1" role="presentation">
        <a class="nav-link p-1" id="pills-rulest-tab" data-bs-toggle="pill" href="#pills-rulest" role="tab" aria-controls="pills-rulest" aria-selected="false">
          @lang('DSpecial::tours.trules')
        </a>
      </li>
      @if($tour_subfleets->count() > 0)
        <form method="GET" action="{{ route('DSpecial.tours') }}">
          <div class="input-group input-group-sm mx-1">
            <span class="input-group-text">Fleet :</span>
            <select class="form-select form-select-sm" name="sfid">
              <option value="">Please select...</option>
              @foreach($tour_subfleets as $sf)
                <option value="{{ $sf->id }}" @if($sf->id == @request()->input('sfid')) selected @endif>{{ $sf->name.' | '.optional($sf->airline)->code }}</option>
              @endforeach
            </select>
            <input class="btn btn-sm btn-success" type="submit" value="Search">
          </div>
        </form>
      @endif
    </ul>

    <div class="tab-content" id="pills-tabContent">
      <div class="tab-pane fade show active" id="pills-activet" role="tabpanel" aria-labelledby="pills-activet-tab">
        <div id="activet" class="row row-cols-lg-3">
          @foreach($tours as $tour)
            @if($carbon_now >= $tour->start_date && $carbon_now <= $tour->end_date)
              @include('DSpecial::tours.table')
            @endif
          @endforeach
        </div>
      </div>
      <div class="tab-pane fade" id="pills-futuret" role="tabpanel" aria-labelledby="pills-futuret-tab">
        <div id="futuret" class="row row-cols-lg-3">
          @foreach ($tours as $tour)
            @if($carbon_now < $tour->start_date)
              @include('DSpecial::tours.table')
            @endif
          @endforeach
        </div>
      </div>
      <div class="tab-pane fade" id="pills-closedt" role="tabpanel" aria-labelledby="pills-closedt-tab">
        <div id="closedt" class="row row-cols-lg-3">
          @foreach ($tours as $tour)
            @if($carbon_now > $tour->end_date)
              @include('DSpecial::tours.table')
            @endif
          @endforeach
        </div>
      </div>
      <div class="tab-pane fade" id="pills-rulest" role="tabpanel" aria-labelledby="pills-rulest-tab">
        <div id="rulest" class="row">
          <div class="col">
            <div class="card mb-2">
              <div class="card-header p-1">
                <h5 class="m-1">
                  Tour Rules
                  <i class="fas fa-question-circle float-end"></i>
                </h5>
              </div>
              <div class="card-body p-1">
                @foreach(__('DSpecial::tours.trules_text') as $rule)
                  <p>&bull;&nbsp;{!! $rule !!}</p>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
