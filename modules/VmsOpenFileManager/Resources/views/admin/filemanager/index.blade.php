@extends('admin.app')

@section('title', __('vmsopenfilemanager::messages.file_manager'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">@lang('vmsopenfilemanager::messages.file_manager')</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" onclick="openUploadModal()">
                            <i class="fas fa-upload"></i> @lang('vmsopenfilemanager::messages.upload_file')
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="createFolder()">
                            <i class="fas fa-folder-plus"></i> @lang('vmsopenfilemanager::messages.new_folder')
                        </button>
                    </div>
                </div>
                <div class="card-body"> 
                    @if(isset($currentFolder))
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('admin.vmsopenfilemanager.index') }}">@lang('vmsopenfilemanager::messages.root')</a>
                                </li>
                                @foreach($breadcrumbs ?? [] as $crumb)
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('admin.vmsopenfilemanager.index', ['folder' => $crumb['id']]) }}">
                                            {{ $crumb['name'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif

                    <div class="row">
                        @forelse($folders as $folder)
                            <div class="col-md-2 col-sm-3 col-6 mb-4">
                                <div class="text-center">
                                    <a href="{{ route('admin.vmsopenfilemanager.index', ['folder' => $folder->id]) }}" class="text-decoration-none">
                                        <i class="fas fa-folder fa-4x text-warning"></i>
                                        <div class="mt-2">{{ $folder->name }}</div>
                                        <small class="text-muted">@lang('vmsopenfilemanager::messages.files_count', ['count' => $folder->files->count()])</small>
                                        @if($folder->is_public)
                                            <span class="badge badge-info">@lang('vmsopenfilemanager::messages.public_folder')</span>
                                        @endif
                                    </a>
                                    <div class="mt-1">
                                        <button class="btn btn-sm btn-danger" onclick="deleteFolder({{ $folder->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                <p class="text-muted">@lang('vmsopenfilemanager::messages.no_folders')</p>
                            </div>
                        @endforelse

                        @foreach($files as $file)
                            <div class="col-md-2 col-sm-3 col-6 mb-4">
                                <div class="text-center">
                                    @if($file->is_image)
                                        @if($file->thumbnail_path)
                                            <img src="{{ Storage::url($file->thumbnail_path) }}" class="img-thumbnail" style="height: 64px; width: 64px; object-fit: cover;">
                                        @else
                                            <img src="{{ Storage::url($file->path) }}" class="img-thumbnail" style="height: 64px; width: 64px; object-fit: cover;">
                                        @endif
                                    @elseif($file->extension == 'zip')
                                        <i class="fas fa-file-archive fa-4x text-warning"></i>
                                    @else
                                        <i class="fas fa-file fa-4x text-secondary"></i>
                                    @endif
                                    <div class="mt-2" title="{{ $file->name }}">
                                        {{ Str::limit($file->name, 20) }}
                                    </div>
                                    @if($file->description)
                                        <small class="text-muted d-block">{{ Str::limit($file->description, 30) }}</small>
                                    @endif
                                    <small class="text-muted">{{ $file->size }}</small>
                                    <div class="mt-1">
                                        <a href="{{ route('vmsopenfilemanager.downloads.file', $file->id) }}" class="btn btn-sm btn-success" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="deleteFile({{ $file->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.3); z-index: 1050; width: 500px; max-width: 90%;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <h5>@lang('vmsopenfilemanager::messages.upload_file')</h5>
        <button type="button" onclick="closeUploadModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
    </div>
    <form id="uploadForm" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="folder_id" value="{{ $currentFolder->id ?? '' }}">
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">@lang('vmsopenfilemanager::messages.select_file')</label>
            <input type="file" name="file" id="uploadFile" class="form-control-file" required>
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">@lang('vmsopenfilemanager::messages.description_optional')</label>
            <textarea name="description" maxlength="30" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" rows="2"></textarea>
            <small style="color: #666; font-size: 11px;"><span id="descCounter">0</span>/30 @lang('vmsopenfilemanager::messages.characters')</small>
        </div>
        
        <!-- Progress Bar -->
        <div id="progressContainer" style="display: none; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>@lang('vmsopenfilemanager::messages.upload_progress')</span>
                <span id="progressPercent">0%</span>
            </div>
            <div style="width: 100%; background-color: #f0f0f0; border-radius: 4px; overflow: hidden;">
                <div id="progressBar" style="width: 0%; height: 20px; background-color: #007bff; transition: width 0.3s;"></div>
            </div>
            <div id="uploadStatus" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeUploadModal()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">@lang('vmsopenfilemanager::messages.cancel')</button>
            <button type="submit" id="uploadSubmitBtn" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">@lang('vmsopenfilemanager::messages.upload')</button>
        </div>
    </form>
</div>

<!-- Overlay -->
<div id="modalOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1040;"></div>
@endsection

<script>
function openUploadModal() {
    document.getElementById('modalOverlay').style.display = 'block';
    document.getElementById('uploadModal').style.display = 'block';
    document.getElementById('uploadForm').reset();
    document.getElementById('progressContainer').style.display = 'none';
    var submitBtn = document.getElementById('uploadSubmitBtn');
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
    var counter = document.getElementById('descCounter');
    if (counter) {
        counter.innerText = '0';
        counter.style.color = '#666';
    }
}

function closeUploadModal() {
    document.getElementById('modalOverlay').style.display = 'none';
    document.getElementById('uploadModal').style.display = 'none';
}

function createFolder() {
    let name = prompt('@lang('vmsopenfilemanager::messages.enter_folder_name')');
    if (name) {
        let isPublic = confirm('@lang('vmsopenfilemanager::messages.make_folder_public')');
        let parentId = {{ $currentFolder->id ?? 'null' }};
        
        let data = {
            name: name,
            is_public: isPublic
        };
        
        if (parentId) {
            data.parent_id = parentId;
        }
        
        fetch('{{ route("admin.vmsopenfilemanager.folder.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
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

function deleteFile(id) {
    if (confirm('@lang('vmsopenfilemanager::messages.delete_file_confirm')')) {
        fetch('{{ url("admin/vmsopenfilemanager/file") }}/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
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

function deleteFolder(id) {
    if (confirm('@lang('vmsopenfilemanager::messages.delete_folder_confirm')')) {
        fetch('{{ url("admin/vmsopenfilemanager/folder") }}/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
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

document.addEventListener('DOMContentLoaded', function() {
    var descTextarea = document.querySelector('textarea[name="description"]');
    if (descTextarea) {
        var counter = document.getElementById('descCounter');
        if (counter) {
            descTextarea.addEventListener('input', function() {
                var length = this.value.length;
                counter.innerText = length;
                if (length > 30) {
                    counter.style.color = 'red';
                } else {
                    counter.style.color = '#666';
                }
            });
            counter.innerText = descTextarea.value.length;
        }
    }
    
    var form = document.getElementById('uploadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var fileInput = document.getElementById('uploadFile');
            var file = fileInput.files[0];
            
            if (!file) {
                alert('@lang('vmsopenfilemanager::messages.select_file')');
                return;
            }
            
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressPercent').innerText = '0%';
            document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.upload_progress')';
            
            var submitBtn = document.getElementById('uploadSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            
            var formData = new FormData(this);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '{{ route("admin.vmsopenfilemanager.file.upload") }}', true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    document.getElementById('progressBar').style.width = percent + '%';
                    document.getElementById('progressPercent').innerText = percent + '%';
                    
                    var loadedMB = (e.loaded / 1024 / 1024).toFixed(2);
                    var totalMB = (e.total / 1024 / 1024).toFixed(2);
                    document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.upload_progress') ' + loadedMB + ' MB / ' + totalMB + ' MB';
                }
            });
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.upload_complete')';
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.error_occurred') ' + response.message;
                            document.getElementById('progressBar').style.backgroundColor = '#dc3545';
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '1';
                        }
                    } catch (e) {
                        document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.error_occurred')';
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }
                } else {
                    document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.upload_failed')';
                    document.getElementById('progressBar').style.backgroundColor = '#dc3545';
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('uploadStatus').innerHTML = '@lang('vmsopenfilemanager::messages.network_error')';
                document.getElementById('progressBar').style.backgroundColor = '#dc3545';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            };
            
            xhr.send(formData);
        });
    }
});
</script>