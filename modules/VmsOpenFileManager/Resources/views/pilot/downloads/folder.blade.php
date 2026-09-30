@extends('app')

@section('title', $folder->name . ' - ' . __('vmsopenfilemanager::messages.downloads'))

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>{{ $folder->name }}</h3>
                    <a href="{{ route('vmsopenfilemanager.downloads.index') }}" class="btn btn-secondary btn-sm float-right">@lang('vmsopenfilemanager::messages.back')</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($subfolders as $subfolder)
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-folder fa-3x text-warning"></i>
                                        <h5 class="mt-2">{{ $subfolder->name }}</h5>
                                        <a href="{{ route('vmsopenfilemanager.downloads.folder', $subfolder->id) }}" class="btn btn-primary btn-sm">@lang('vmsopenfilemanager::messages.open')</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                        @endforelse

                        @forelse($files as $file)
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        @if($file->is_image)
                                            @if($file->thumbnail_path)
                                                <img src="{{ Storage::url($file->thumbnail_path) }}" style="height: 64px; width: 64px; object-fit: cover;" class="mb-2">
                                            @else
                                                <img src="{{ Storage::url($file->path) }}" style="height: 64px; width: 64px; object-fit: cover;" class="mb-2">
                                            @endif
                                        @elseif($file->extension == 'zip')
                                            <i class="fas fa-file-archive fa-3x text-warning mb-2"></i>
                                        @else
                                            <i class="fas fa-file fa-3x text-secondary mb-2"></i>
                                        @endif
                                        <h6 class="mt-2">{{ Str::limit($file->name, 20) }}</h6>
                                        @if($file->description)
                                            <small class="text-muted d-block">{{ Str::limit($file->description, 40) }}</small>
                                        @endif
                                        <small class="text-muted">{{ $file->size }}</small>
                                        <div class="mt-2">
                                            <a href="{{ route('vmsopenfilemanager.downloads.file', $file->id) }}" class="btn btn-success btn-sm">@lang('vmsopenfilemanager::messages.download')</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            @if($subfolders->isEmpty())
                                <div class="col-12 text-center">
                                    <p>@lang('vmsopenfilemanager::messages.no_files_folders')</p>
                                </div>
                            @endif
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection