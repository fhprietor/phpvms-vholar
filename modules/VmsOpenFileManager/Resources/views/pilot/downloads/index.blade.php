@extends('app')

@section('title', __('vmsopenfilemanager::messages.downloads'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>@lang('vmsopenfilemanager::messages.downloads')</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($folders as $folder)
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-folder fa-3x text-warning"></i>
                                        <h5 class="mt-2">{{ $folder->name }}</h5>
                                        @if($folder->description)
                                            <small class="text-muted d-block">{{ Str::limit($folder->description, 40) }}</small>
                                        @endif
                                        <a href="{{ route('vmsopenfilemanager.downloads.folder', $folder->id) }}" class="btn btn-primary btn-sm mt-2">@lang('vmsopenfilemanager::messages.open')</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center">
                                <p>@lang('vmsopenfilemanager::messages.no_folders')</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection