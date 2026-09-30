@extends('app')

@section('title', __('vmsopenfilemanager::messages.liveries'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>@lang('vmsopenfilemanager::messages.select_simulator')</h3>
                    <p class="text-muted mb-0">@lang('vmsopenfilemanager::messages.choose_simulator')</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($simulators as $simulator)
                            <div class="col-md-3 col-sm-6 mb-4">
                                <div class="card text-center h-100 shadow-sm">
                                    <div class="card-body">
                                        @if($simulator->logo_url)
                                            <img src="{{ $simulator->logo_url }}" alt="{{ $simulator->name }}" style="max-height: 80px; width: auto;" class="mb-3">
                                        @else
                                            <i class="fas fa-desktop fa-4x text-primary mb-3"></i>
                                        @endif
                                        <h5>{{ $simulator->name }}</h5>
                                        <p class="text-muted small">
                                            <i class="fas fa-paint-roller"></i> {{ $simulator->liveries_count ?? 0 }} @lang('vmsopenfilemanager::messages.liveries_available')
                                        </p>
                                        @if(($simulator->liveries_count ?? 0) > 0)
                                            <a href="{{ route('vmsopenfilemanager.liveries.simulator', $simulator->slug) }}" class="btn btn-primary mt-2">
                                                <i class="fas fa-eye"></i> @lang('vmsopenfilemanager::messages.browse')
                                            </a>
                                        @else
                                            <button class="btn btn-secondary mt-2" disabled>
                                                <i class="fas fa-ban"></i> @lang('vmsopenfilemanager::messages.no_liveries')
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-desktop fa-4x text-muted mb-3"></i>
                                <p class="text-muted">@lang('vmsopenfilemanager::messages.no_simulators')</p>
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