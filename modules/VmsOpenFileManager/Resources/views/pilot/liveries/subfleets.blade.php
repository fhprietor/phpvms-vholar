@extends('app')

@section('title', $simulator->name . ' - ' . __('vmsopenfilemanager::messages.liveries'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>{{ $simulator->name }} - @lang('vmsopenfilemanager::messages.aircraft_liveries')</h3>
                    <a href="{{ route('vmsopenfilemanager.liveries.index') }}" class="btn btn-secondary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> @lang('vmsopenfilemanager::messages.back_to_simulators')
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($subfleets as $subfleet)
                            <div class="col-md-3 col-sm-6 mb-4">
                                <div class="card text-center h-100 shadow-sm">
                                    <div class="card-body">
                                        @php
                                            $firstAircraft = $subfleet->aircraft->first();
                                        @endphp
                                        @if($firstAircraft && $firstAircraft->image)
                                            <img src="{{ Storage::url($firstAircraft->image) }}" alt="{{ $subfleet->name }}" style="max-width: 100%; height: 100px; object-fit: contain;" class="mb-3">
                                        @else
                                            <i class="fas fa-plane fa-4x text-secondary mb-3"></i>
                                        @endif
                                        <h5>{{ $subfleet->name }}</h5>
                                        <p class="text-muted small">{{ $subfleet->type ?? '' }}</p>
                                        <a href="{{ route('vmsopenfilemanager.liveries.subfleet', [$simulator->slug, $subfleet->id]) }}" class="btn btn-primary mt-2">
                                            <i class="fas fa-paint-roller"></i> @lang('vmsopenfilemanager::messages.view_liveries')
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-plane fa-4x text-muted mb-3"></i>
                                <p class="text-muted">@lang('vmsopenfilemanager::messages.no_aircraft_available', ['simulator' => $simulator->name])</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<style>
.shadow-sm {
    transition: all 0.3s ease;
}
.shadow-sm:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>
@endpush