@extends('admin.app')

@section('title', 'Edit Livery')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Livery: {{ $livery->name }}</h3>
                    <a href="{{ route('admin.liveries.index') }}" class="btn btn-secondary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <form action="{{ route('admin.liveries.update', $livery->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Livery Name *</label>
                                    <input type="text" name="name" class="form-control" value="{{ $livery->name }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Subfleet *</label>
                                    <select name="subfleet_id" class="form-control" required>
                                        @foreach($subfleets as $subfleet)
                                            <option value="{{ $subfleet->id }}" {{ $livery->subfleet_id == $subfleet->id ? 'selected' : '' }}>
                                                {{ $subfleet->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Simulator *</label>
                                    <select name="simulator_id" class="form-control" required>
                                        @foreach($simulators as $simulator)
                                            <option value="{{ $simulator->id }}" {{ $livery->simulator_id == $simulator->id ? 'selected' : '' }}>
                                                {{ $simulator->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Manufacturer *</label>
                                    <select name="manufacturer_id" class="form-control" required>
                                        @foreach($manufacturers as $manufacturer)
                                            <option value="{{ $manufacturer->id }}" {{ $livery->manufacturer_id == $manufacturer->id ? 'selected' : '' }}>
                                                {{ $manufacturer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Aircraft (Registration)</label>
                                    <select name="aircraft_id" class="form-control">
                                        <option value="">None</option>
                                        @foreach($aircrafts as $aircraft)
                                            <option value="{{ $aircraft->id }}" {{ $livery->aircraft_id == $aircraft->id ? 'selected' : '' }}>
                                                {{ $aircraft->registration ?? $aircraft->name }} ({{ $aircraft->icao }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>File Type *</label>
                                    <select name="file_type" class="form-control" id="fileTypeSelect" required>
                                        <option value="local" {{ $livery->file_type == 'local' ? 'selected' : '' }}>Local File (Upload ZIP)</option>
                                        <option value="external" {{ $livery->file_type == 'external' ? 'selected' : '' }}>External URL</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-12" id="localFileGroup" style="display: {{ $livery->file_type == 'local' ? 'block' : 'none' }};">
                                <div class="form-group">
                                    <label>Livery File (ZIP)</label>
                                    <input type="file" name="file" class="form-control-file" accept=".zip">
                                    <small class="text-muted">Leave empty to keep current file. Max size: 200MB</small>
                                    @if($livery->file_type == 'local' && $livery->file_path)
                                        <div class="mt-2">
                                            <small>Current file: <a href="{{ Storage::url($livery->file_path) }}" target="_blank">{{ basename($livery->file_path) }}</a></small>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-12" id="externalUrlGroup" style="display: {{ $livery->file_type == 'external' ? 'block' : 'none' }};">
                                <div class="form-group">
                                    <label>External Download URL *</label>
                                    <input type="url" name="external_url" class="form-control" placeholder="https://example.com/livery.zip" value="{{ $livery->file_type == 'external' ? $livery->file_path : '' }}">
                                    @if($livery->file_type == 'external' && $livery->file_path)
                                        <div class="mt-2">
                                            <small>Current URL: <a href="{{ $livery->file_path }}" target="_blank">{{ $livery->file_path }}</a></small>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Current Thumbnail</label>
                                    @if($livery->thumbnail_url)
                                        <div>
                                            <img src="{{ $livery->thumbnail_url }}" style="max-height: 100px;" class="mb-2">
                                        </div>
                                    @endif
                                    <input type="file" name="thumbnail" class="form-control-file" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current thumbnail</small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" rows="3">{{ $livery->description }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="isActive" {{ $livery->is_active ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="isActive">Active (visible to pilots)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Update Livery</button>
                        <a href="{{ route('admin.liveries.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle file type fields
document.getElementById('fileTypeSelect')?.addEventListener('change', function() {
    if (this.value === 'local') {
        document.getElementById('localFileGroup').style.display = 'block';
        document.getElementById('externalUrlGroup').style.display = 'none';
        document.querySelector('input[name="file"]').required = false;
        document.querySelector('input[name="external_url"]').required = false;
    } else {
        document.getElementById('localFileGroup').style.display = 'none';
        document.getElementById('externalUrlGroup').style.display = 'block';
        document.querySelector('input[name="file"]').required = false;
        document.querySelector('input[name="external_url"]').required = true;
    }
});
</script>
@endsection