@extends('app')

@section('title', $subfleet->name . ' - ' . $simulator->name . ' ' . __('vmsopenfilemanager::messages.liveries'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>{{ $subfleet->name }} - {{ $simulator->name }} @lang('vmsopenfilemanager::messages.liveries')</h3>
                    <a href="{{ route('vmsopenfilemanager.liveries.simulator', $simulator->slug) }}" class="btn btn-secondary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> @lang('vmsopenfilemanager::messages.back_to_aircraft')
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($liveries as $livery)
                            <div class="col-md-4 col-lg-3 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-img-top text-center p-3" style="height: 180px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                        @if($livery->thumbnail_url)
                                            <img src="{{ $livery->thumbnail_url }}" alt="{{ $livery->name }}" style="max-width: 100%; max-height: 150px; object-fit: contain;">
                                        @else
                                            <i class="fas fa-paint-roller fa-4x text-muted"></i>
                                        @endif
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title text-center">{{ $livery->name }}</h5>
                                        <p class="card-text text-center text-muted small">
                                            <i class="fas fa-industry"></i> {{ $livery->manufacturer->name ?? __('vmsopenfilemanager::messages.unknown') }}
                                        </p>
                                        @if($livery->aircraft && $livery->aircraft->registration)
                                            <p class="card-text text-center">
                                                <small class="text-muted">@lang('vmsopenfilemanager::messages.registration'):</small><br>
                                                <strong>{{ $livery->aircraft->registration }}</strong>
                                            </p>
                                        @endif
                                        @if($livery->description)
                                            <p class="card-text small">{{ Str::limit($livery->description, 60) }}</p>
                                        @endif
                                        <div class="text-center mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-download"></i> {{ $livery->downloads }} @lang('vmsopenfilemanager::messages.downloads')
                                            </small>
                                            @if($livery->file_size)
                                                <br><small class="text-muted">
                                                    <i class="fas fa-hdd"></i> {{ $livery->file_size }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent text-center">
                                        <a href="{{ route('vmsopenfilemanager.liveries.download', $livery->id) }}" class="btn btn-success btn-block" {{ $livery->is_external ? 'target="_blank"' : '' }}>
                                            <i class="fas fa-download"></i> @lang('vmsopenfilemanager::messages.download')
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-paint-roller fa-4x text-muted mb-3"></i>
                                <p class="text-muted">@lang('vmsopenfilemanager::messages.no_liveries_available', ['simulator' => $simulator->name])</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection