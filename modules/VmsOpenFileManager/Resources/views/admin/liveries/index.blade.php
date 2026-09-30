@extends('admin.app')

@section('title', __('vmsopenfilemanager::messages.liveries_management'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">@lang('vmsopenfilemanager::messages.liveries_management')</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" onclick="openUploadModal()">
                            <i class="fas fa-upload"></i> @lang('vmsopenfilemanager::messages.upload_livery')
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px">@lang('vmsopenfilemanager::messages.preview')</th>
                                    <th>@lang('vmsopenfilemanager::messages.name')</th>
                                    <th>@lang('vmsopenfilemanager::messages.subfleet')</th>
                                    <th>@lang('vmsopenfilemanager::messages.simulator')</th>
                                    <th>@lang('vmsopenfilemanager::messages.manufacturer')</th>
                                    <th>@lang('vmsopenfilemanager::messages.aircraft')</th>
                                    <th>@lang('vmsopenfilemanager::messages.downloads_count')</th>
                                    <th>@lang('vmsopenfilemanager::messages.status')</th>
                                    <th style="width: 100px">@lang('vmsopenfilemanager::messages.actions')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($liveries as $livery)
                                    <tr>
                                        <td>
                                            @if($livery->thumbnail_url)
                                                <img src="{{ $livery->thumbnail_url }}" style="height: 40px; width: 40px; object-fit: cover;" class="rounded">
                                            @else
                                                <i class="fas fa-image fa-2x text-muted"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $livery->name }}</strong>
                                            @if($livery->is_external)
                                                <span class="badge badge-info">@lang('vmsopenfilemanager::messages.external')</span>
                                            @endif
                                        </td>
                                        <td>{{ $livery->subfleet->name ?? '-' }}</td>
                                        <td>{{ $livery->simulator->name ?? '-' }}</td>
                                        <td>{{ $livery->manufacturer->name ?? '-' }}</td>
                                        <td>{{ $livery->aircraft->registration ?? '-' }}</td>
                                        <td>{{ $livery->downloads }}</td>
                                        <td>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="active_{{ $livery->id }}" {{ $livery->is_active ? 'checked' : '' }} onchange="toggleActive({{ $livery->id }})">
                                                <label class="custom-control-label" for="active_{{ $livery->id }}"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.liveries.edit', $livery->id) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger" onclick="deleteLivery({{ $livery->id }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <i class="fas fa-paint-roller fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">@lang('vmsopenfilemanager::messages.no_liveries')</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $liveries->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal (Simple) -->
<div id="uploadModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.3); z-index: 1050; width: 600px; max-width: 90%; max-height: 90%; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <h5>@lang('vmsopenfilemanager::messages.upload_livery')</h5>
        <button type="button" onclick="closeUploadModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
    </div>
    <form id="uploadLiveryForm" action="{{ route('admin.liveries.upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.livery_name') *</label>
                    <input type="text" name="name" class="form-control" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label> @lang('vmsopenfilemanager::messages.subfleet') *</label>
                    <select name="subfleet_id" class="form-control" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value=""> @lang('vmsopenfilemanager::messages.select_subfleet')</option>
                        @foreach($subfleets as $subfleet)
                            <option value="{{ $subfleet->id }}">{{ $subfleet->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.simulator') *</label>
                    <select name="simulator_id" class="form-control" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">@lang('vmsopenfilemanager::messages.select_simulator')</option>
                        @foreach($simulators as $simulator)
                            <option value="{{ $simulator->id }}">{{ $simulator->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.manufacturer') *</label>
                    <select name="manufacturer_id" class="form-control" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">@lang('vmsopenfilemanager::messages.select_manufacturer')</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}">{{ $manufacturer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.aircraft') (@lang('vmsopenfilemanager::messages.registration'))</label>
                    <select name="aircraft_id" class="form-control" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">@lang('vmsopenfilemanager::messages.select_aircraft')</option>
                        @foreach($aircrafts as $aircraft)
                            <option value="{{ $aircraft->id }}">{{ $aircraft->registration ?? $aircraft->name }} ({{ $aircraft->icao }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.file_type') *</label>
                    <select name="file_type" class="form-control" id="fileTypeSelect" required style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="local">@lang('vmsopenfilemanager::messages.local_file')</option>
                        <option value="external">@lang('vmsopenfilemanager::messages.external_url')</option>
                    </select>
                </div>
            </div>
            <div class="col-md-12" id="localFileGroup">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.livery_file_zip') *</label>
                    <input type="file" name="file" class="form-control-file" accept=".zip" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <small class="text-muted">@lang('vmsopenfilemanager::messages.max_size_200mb')</small>
                </div>
            </div>
            <div class="col-md-12" id="externalUrlGroup" style="display: none;">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.external_download_url') *</label>
                    <input type="url" name="external_url" class="form-control" placeholder="@lang('vmsopenfilemanager::messages.external_url_placeholder')" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.preview_image')</label>
                    <input type="file" name="thumbnail" class="form-control-file" accept="image/*" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <small class="text-muted">@lang('vmsopenfilemanager::messages.image_resize_notice')</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('vmsopenfilemanager::messages.description')</label>
                    <textarea name="description" class="form-control" rows="2" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="isActive" checked>
                        <label class="custom-control-label" for="isActive">@lang('vmsopenfilemanager::messages.active_visible')</label>
                    </div>
                </div>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
            <button type="button" onclick="closeUploadModal()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">@lang('vmsopenfilemanager::messages.cancel')</button>
            <button type="submit" id="uploadSubmitBtn" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">@lang('vmsopenfilemanager::messages.upload_livery')</button>
        </div>
    </form>
</div>

<!-- Overlay -->
<div id="modalOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1040;"></div>

<script>
function openUploadModal() {
    document.getElementById('modalOverlay').style.display = 'block';
    document.getElementById('uploadModal').style.display = 'block';
}

function closeUploadModal() {
    document.getElementById('modalOverlay').style.display = 'none';
    document.getElementById('uploadModal').style.display = 'none';
}

function toggleActive(id) {
    fetch(`/admin/liveries/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    }).then(response => response.json())
      .then(data => {
          if (!data.success) {
              alert(data.message);
              location.reload();
          } else {
              location.reload();
          }
      });
}

function deleteLivery(id) {
    if (confirm('@lang('vmsopenfilemanager::messages.delete_livery_confirm')')) {
        fetch(`/admin/liveries/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert(data.message);
              }
          });
    }
}

// Toggle file type fields
document.getElementById('fileTypeSelect')?.addEventListener('change', function() {
    if (this.value === 'local') {
        document.getElementById('localFileGroup').style.display = 'block';
        document.getElementById('externalUrlGroup').style.display = 'none';
        document.querySelector('input[name="file"]').required = true;
        document.querySelector('input[name="external_url"]').required = false;
    } else {
        document.getElementById('localFileGroup').style.display = 'none';
        document.getElementById('externalUrlGroup').style.display = 'block';
        document.querySelector('input[name="file"]').required = false;
        document.querySelector('input[name="external_url"]').required = true;
    }
});

// Form submission
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('uploadLiveryForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var submitBtn = document.getElementById('uploadSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.innerText = '@lang('vmsopenfilemanager::messages.uploading')';
            
            var formData = new FormData(this);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', this.action, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            
            xhr.onload = function() {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.innerText = '@lang('vmsopenfilemanager::messages.upload_livery')';
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('@lang('vmsopenfilemanager::messages.livery_uploaded')');
                            closeUploadModal();
                            location.reload();
                        } else {
                            alert('@lang('vmsopenfilemanager::messages.error_occurred') ' + response.message);
                        }
                    } catch (e) {
                        closeUploadModal();
                        location.reload();
                    }
                } else {
                    alert('@lang('vmsopenfilemanager::messages.upload_failed') ' + xhr.status);
                }
            };
            
            xhr.onerror = function() {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.innerText = '@lang('vmsopenfilemanager::messages.upload_livery')';
                alert('@lang('vmsopenfilemanager::messages.network_error')');
            };
            
            xhr.send(formData);
        });
    }
});
</script>
@endsection