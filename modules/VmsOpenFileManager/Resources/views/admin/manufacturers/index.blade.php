@extends('admin.app')
@section('title', 'Manufacturers')

@section('content')
<div class="container-fluid">
  <div class="row">

    {{-- List --}}
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Manufacturers</h3>
        </div>
        <div class="card-body p-0">

          @if(session('success'))
            <div class="alert alert-success m-3">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger m-3">{{ session('error') }}</div>
          @endif

          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width:50px">Order</th>
                <th>Name</th>
                <th>Slug</th>
                <th class="text-center">Liveries</th>
                <th class="text-center">Active</th>
                <th class="text-end" style="width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($manufacturers as $m)
                <tr>
                  <td>{{ $m->order }}</td>
                  <td>{{ $m->name }}</td>
                  <td><code>{{ $m->slug }}</code></td>
                  <td class="text-center">{{ $m->liveries_count }}</td>
                  <td class="text-center">
                    @if($m->is_active)
                      <span class="badge badge-success">Active</span>
                    @else
                      <span class="badge badge-secondary">Inactive</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <button class="btn btn-xs btn-info"
                            onclick="openEdit({{ $m->id }}, '{{ addslashes($m->name) }}', {{ $m->order }}, {{ $m->is_active ? 'true' : 'false' }})">
                      <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST"
                          action="{{ route('admin.manufacturers.destroy', $m) }}"
                          style="display:inline">
                      @csrf @method('DELETE')
                      <button class="btn btn-xs btn-danger"
                              data-vh-confirm="¿Eliminar fabricante {{ addslashes($m->name) }}? Esta acción no se puede deshacer."
                              {{ $m->liveries_count > 0 ? 'disabled title=Has liveries' : '' }}>
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No manufacturers found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Create / Edit form --}}
    <div class="col-md-4">
      <div class="card" id="formCard">
        <div class="card-header">
          <h3 class="card-title" id="formTitle">New Manufacturer</h3>
        </div>
        <div class="card-body">

          <form method="POST" id="manufacturerForm"
                action="{{ route('admin.manufacturers.store') }}">
            @csrf
            <span id="methodField"></span>

            @if($errors->any())
              <div class="alert alert-danger">
                <ul class="mb-0">
                  @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <div class="form-group">
              <label>Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="fieldName"
                     class="form-control" value="{{ old('name') }}" required>
            </div>

            <div class="form-group">
              <label>Order</label>
              <input type="number" name="order" id="fieldOrder"
                     class="form-control" value="{{ old('order', 0) }}" min="0">
              <small class="text-muted">Lower = appears first</small>
            </div>

            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="checkbox" name="is_active" id="fieldActive"
                       class="custom-control-input" value="1" checked>
                <label class="custom-control-label" for="fieldActive">Active</label>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save
              </button>
              <button type="button" class="btn btn-secondary" onclick="resetForm()">
                <i class="fas fa-times"></i> Cancel
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@section('scripts')
<script>
  const storeUrl  = "{{ route('admin.manufacturers.store') }}";
  const updateBase = "{{ url('/admin/manufacturers') }}";

  function openEdit(id, name, order, isActive) {
    document.getElementById('formTitle').textContent = 'Edit Manufacturer';
    document.getElementById('fieldName').value  = name;
    document.getElementById('fieldOrder').value = order;
    document.getElementById('fieldActive').checked = isActive;

    const form = document.getElementById('manufacturerForm');
    form.action = updateBase + '/' + id;

    document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT">';

    document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
  }

  function resetForm() {
    document.getElementById('formTitle').textContent = 'New Manufacturer';
    document.getElementById('manufacturerForm').action = storeUrl;
    document.getElementById('methodField').innerHTML = '';
    document.getElementById('fieldName').value  = '';
    document.getElementById('fieldOrder').value = '0';
    document.getElementById('fieldActive').checked = true;
  }
</script>
@endsection
