@extends('admin.app')
@section('title', 'Admin - Flight Assignments')

@section('content')
        <div class="card mb-3">
            <div class="card-header p-2">
                <h5 class="m-1">
                    Administración de Asignaciones Mensuales
                    <i class="fas fa-tasks float-end"></i>
                </h5>
            </div>
            <div class="card-body p-2">
                {{-- Fila con dos columnas: Botones (izquierda) y Estadísticas (derecha) --}}
                <div class="row mb-3">
                    {{-- Botones de generación - columna izquierda --}}
                    <div class="col-md-7">
                        <div class="card h-100 bg-light">
                            <div class="card-header p-2 py-1">
                                <h6 class="m-0">Acciones de Asignación</h6>
                            </div>
                            <div class="card-body p-2">
                                {{-- Filtros --}}
                                <form method="get" action="{{ route('DSpecial.assignments.admin') }}" class="form-inline mb-2">
                                    <select name="year" class="form-control input-sm" style="width:auto; margin-right:6px;">
                                        @foreach($available_years as $year)
                                            <option value="{{ $year }}" {{ $selected_year == $year ? 'selected' : '' }}>{{ $year }}</option>
                                        @endforeach
                                    </select>
                                    <select name="month" class="form-control input-sm" style="width:auto; margin-right:6px;">
                                        @foreach($months as $num => $name)
                                            <option value="{{ $num }}" {{ $selected_month == $num ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="user_id" class="form-control input-sm" style="width:auto; margin-right:6px;">
                                        <option value="">--- Todos los pilotos ---</option>
                                        @foreach($pilots as $pilot)
                                            <option value="{{ $pilot->id }}" {{ $selected_pilot == $pilot->id ? 'selected' : '' }}>
                                                {{ $pilot->ident }} - {{ $pilot->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" style="margin-right:4px;">Filtrar</button>
                                    <a href="{{ route('DSpecial.assignments.admin') }}" class="btn btn-sm btn-default">Limpiar</a>
                                </form>
                                <div class="btn-group" role="group">
                                    <form method="post" action="{{ route('DSpecial.assignments_manual') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="curr_page" value="{{ route('DSpecial.assignments.admin') }}">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-plus"></i> Generar para TODOS ({{ $selected_month }}/{{ $selected_year }})
                                        </button>
                                    </form>
                                    
                                    <form method="post" action="{{ route('DSpecial.assignments_manual') }}" class="d-inline ms-2">
                                        @csrf
                                        <input type="hidden" name="curr_page" value="{{ route('DSpecial.assignments.admin') }}">
                                        <input type="hidden" name="resetmonth" value="true">
                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('¿Eliminar y regenerar para TODOS?')">
                                            <i class="fas fa-sync-alt"></i> Reasignar TODOS
                                        </button>
                                    </form>
                                    
                                    @if($selected_pilot)
                                    <form method="post" action="{{ route('DSpecial.assignments_manual') }}" class="d-inline ms-2">
                                        @csrf
                                        <input type="hidden" name="curr_page" value="{{ route('DSpecial.assignments.admin') }}">
                                        <input type="hidden" name="userid" value="{{ $selected_pilot }}">
                                        <input type="hidden" name="resetmonth" value="true">
                                        <button type="submit" class="btn btn-sm btn-info">
                                            <i class="fas fa-user"></i> Reasignar Generar solo este piloto
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Gráfico de progreso general - columna derecha --}}
                    <div class="col-md-5">
                        <div class="card h-100 bg-light">
                            <div class="card-header p-2 py-1">
                                <h6 class="m-0">
                                    Progreso General
                                    <i class="fas fa-chart-pie float-end"></i>
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                @php
                                    // Calcular estadísticas generales
                                    $total_assignments = 0;
                                    $total_completed = 0;
                                    $total_pilots = 0;
                                    
                                    foreach($assignments as $pilot_id => $pilot_assignments) {
                                        $total_pilots++;
                                        $total_assignments += $pilot_assignments->count();
                                        $total_completed += $pilot_assignments->where('completed', true)->count();
                                    }
                                    
                                    $overall_ratio = $total_assignments > 0 ? round(($total_completed / $total_assignments) * 100, 1) : 0;
                                    $pending = $total_assignments - $total_completed;
                                @endphp
                                
                                @if($total_assignments > 0)
                                    {{-- Barra de progreso principal --}}
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Progreso Total</span>
                                            <span><strong>{{ $overall_ratio }}%</strong> ({{ $total_completed }}/{{ $total_assignments }})</span>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: {{ $overall_ratio }}%;" 
                                                 aria-valuenow="{{ $overall_ratio }}" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                {{ $overall_ratio }}%
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Estadísticas rápidas --}}
                                    <div class="row text-center small mb-2">
                                        <div class="col-6">
                                            <div class="border rounded p-1">
                                                <div class="text-muted">Pilotos</div>
                                                <div class="h6 mb-0">{{ $total_pilots }}</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-1">
                                                <div class="text-muted">Asignaciones</div>
                                                <div class="h6 mb-0">{{ $total_assignments }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Gráfico de dona simple (CSS puro sin dependencias) --}}
                                    <div class="text-center">
                                        <div class="position-relative d-inline-block">
                                            <svg width="80" height="80" viewBox="0 0 120 120">
                                                <circle cx="60" cy="60" r="54" fill="none" stroke="#e9ecef" stroke-width="12"/>
                                                <circle cx="60" cy="60" r="54" fill="none" stroke="#28a745" 
                                                        stroke-width="12" 
                                                        stroke-dasharray="{{ ($overall_ratio * 339) / 100 }} 339" 
                                                        stroke-dashoffset="0"
                                                        transform="rotate(-90 60 60)"
                                                        stroke-linecap="round"/>
                                            </svg>
                                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                                <span class="fw-bold fs-6">{{ $overall_ratio }}%</span>
                                            </div>
                                        </div>
                                        <div class="small mt-1">
                                            <span class="text-success">●</span> {{ $total_completed }} completadas &nbsp;
                                            <span class="text-warning">●</span> {{ $pending }} pendientes
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center text-muted py-2">
                                        <i class="fas fa-chart-line fa-2x mb-1"></i>
                                        <p class="small mb-0">Sin asignaciones para mostrar</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resultados --}}
        @if(count($assignments) === 0)
            <div class="alert alert-info">
                No hay asignaciones para {{ $months[$selected_month] }} {{ $selected_year }}
                @if($selected_pilot) del piloto seleccionado @endif
            </div>
        @else
            @foreach($assignments as $pilot_id => $pilot_assignments)
              @php
                  $firstAssignment = $pilot_assignments->first();
                  if (!$firstAssignment) {
                      continue; // Saltar si no hay asignaciones (no debería ocurrir)
                  }
                  $pilot = $firstAssignment->user;
                  if (!$pilot) {
                      // Si el piloto no existe, mostrar mensaje o saltar
                      continue;
                  }
                  $completed = $pilot_assignments->where('completed', true)->count();
                  $total = $pilot_assignments->count();
                  $ratio = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
                  $earnings = $pilot ? round($completed * ($pilot->rank->acars_base_pay_rate * $reward_multiplier)) : 0;
              @endphp
                
                <div class="card mb-3">
                    <div class="card-header p-2">
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;">
                            <span style="font-size:1.35rem; font-weight:700; color:#fff; letter-spacing:0.02em;">
                                {{ $pilot->ident }} &mdash; {{ $pilot->name }}
                            </span>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span class="badge badge-default" style="font-size:0.85rem;">
                                    Completados: {{ $completed }}/{{ $total }} ({{ $ratio }}%)
                                </span>
                                @if($selected_pilot)
                                    <button type="button" class="btn btn-sm btn-success"
                                            onclick="openAddModal('{{ $pilot->id }}', '{{ $selected_year }}', '{{ $selected_month }}')">
                                        <i class="fas fa-plus-circle"></i> Añadir vuelo
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-striped mb-0 text-start align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Vuelo</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th class="text-center">Estado</th>
                                    <th>PIREP</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pilot_assignments->sortBy('assignment_order') as $as)
                                @php
                                    $linkedPirep = \App\Models\Pirep::with('aircraft')
                                        ->where([
                                            'user_id'   => $as->user_id,
                                            'flight_id' => $as->flight_id,
                                        ])->whereMonth('submitted_at', $as->assignment_month)
                                          ->whereYear('submitted_at', $as->assignment_year)
                                          ->where('state', \App\Models\Enums\PirepState::ACCEPTED)
                                          ->first();
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $as->assignment_order }}</td>
                                    <td>
                                        @if($as->flight)
                                            <a href="{{ route('frontend.flights.show', [$as->flight->id]) }}" target="_blank">
                                                {{ optional($as->flight->airline)->code }} {{ $as->flight->flight_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">Vuelo eliminado</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($as->flight)
                                            @php $dpt = $as->flight->dpt_airport; @endphp
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                @if($dpt && $dpt->country)
                                                    <span class="flag-icon flag-icon-{{ strtolower($dpt->country) }}" style="flex-shrink:0;"></span>
                                                @endif
                                                <div>
                                                    <a href="{{ route('frontend.airports.show', [$as->flight->dpt_airport_id]) }}" target="_blank" style="font-weight:600;">{{ $as->flight->dpt_airport_id }}</a>
                                                    @if($dpt)<br><small class="text-muted">{{ $dpt->name }}</small>@endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($as->flight)
                                            @php $arr = $as->flight->arr_airport; @endphp
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                @if($arr && $arr->country)
                                                    <span class="flag-icon flag-icon-{{ strtolower($arr->country) }}" style="flex-shrink:0;"></span>
                                                @endif
                                                <div>
                                                    <a href="{{ route('frontend.airports.show', [$as->flight->arr_airport_id]) }}" target="_blank" style="font-weight:600;">{{ $as->flight->arr_airport_id }}</a>
                                                    @if($arr)<br><small class="text-muted">{{ $arr->name }}</small>@endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($linkedPirep)
                                            <i class="fas fa-check-circle text-success" title="Completado"></i>
                                        @else
                                            <i class="fas fa-hourglass-half text-warning" title="Pendiente"></i>
                                        @endif
                                    </td>
                                    <td>
                                        @if($linkedPirep)
                                            <a href="{{ route('frontend.pireps.show', $linkedPirep->id) }}" target="_blank">{{ $linkedPirep->ident }}</a>
                                            @if($linkedPirep->aircraft)
                                                <br><small><a href="{{ route('DBasic.aircraft', $linkedPirep->aircraft->registration) }}" target="_blank">{{ $linkedPirep->aircraft->icao }}</a></small>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($linkedPirep)
                                            {{ $linkedPirep->submitted_at->format('Y/m/d') }}<br>
                                            <small class="text-muted">{{ $linkedPirep->submitted_at->utc()->format('H:i') }} UTC</small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">
                                      <button type="button"
                                              class="btn btn-sm btn-warning edit-assignment-btn"
                                              title="Editar asignación"
                                              data-assignment-id="{{ $as->id }}"
                                              data-flight-id="{{ $as->flight_id }}"
                                              data-flight-ident="{{ optional($as->flight)->ident }}"
                                              data-flight-route="{{ optional($as->flight)->dpt_airport_id }} → {{ optional($as->flight)->arr_airport_id }}">
                                          <i class="fas fa-edit"></i>
                                      </button>
                                      @if(!$linkedPirep)
                                      <form method="post" action="{{ route('DSpecial.assignments.delete') }}" style="display: inline-block;">
                                        @csrf
                                        <input type="hidden" name="assignment_id" value="{{ $as->id }}">
                                        <button type="button" class="btn btn-sm btn-danger" title="Eliminar asignación" onclick="if(confirm('¿Eliminar esta asignación?')) { this.closest('form').submit(); }">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                      </form>
                                      @endif
                                  </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="7" class="text-end">
                                        <small>
                                            <strong>Ganancia estimada:</strong> {{ money($earnings, setting('units.currency')) }}
                                            <span class="text-muted">({{ $completed }} × {{ $pilot->rank->acars_base_pay_rate }} × {{ $reward_multiplier }})</span>
                                        </small>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
{{-- Modal de edición de asignación --}}
<div class="modal fade" id="editAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: linear-gradient(135deg, #2a2633 0%, #1f1c27 100%); border: 1px solid #412c4d;">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="close" data-dismiss="modal" style="color:white;opacity:1;">&times;</button>
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square"></i>
                    Editar Asignación
                </h5>
            </div>
            <form id="editAssignmentForm" method="post" action="{{ route('DSpecial.assignments.edit') }}">
                @csrf
                <input type="hidden" name="assignment_id" id="edit_assignment_id">
                <div class="modal-body">
                    {{-- Información actual de la asignación --}}
                    <div class="card mb-3" style="background: rgba(65,44,77,0.2); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="card-header py-2">
                            <small class="text-muted">Asignación actual</small>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="text-muted small">Vuelo actual</div>
                                    <div class="fw-bold" id="current_flight_ident">--</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Ruta</div>
                                    <div id="current_flight_route">-- → --</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Selector de nuevo vuelo --}}
                    <div class="mb-3">
                        <label class="form-label">Buscar nuevo vuelo</label>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <input type="text" id="filter_airline" class="form-control form-control-sm" placeholder="Aerolínea">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="filter_dep" class="form-control form-control-sm" placeholder="Origen ICAO">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="filter_arr" class="form-control form-control-sm" placeholder="Destino ICAO">
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="searchFlightsBtn" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                        <select name="flight_id" id="flight_select" class="form-control" required>
                            <option value="">Selecciona un vuelo...</option>
                        </select>
                        <small class="text-muted">Puedes buscar por número de vuelo, aerolínea, origen o destino</small>
                    </div>
                    
                    {{-- Vista previa del vuelo seleccionado --}}
                    <div id="flight_preview" class="d-none mt-3">
                        <div class="card" style="background: rgba(65,44,77,0.2); border: 1px solid rgba(255,255,255,0.05);">
                            <div class="card-header py-2">
                                <small class="text-muted">Vuelo seleccionado</small>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-muted small">Vuelo</div>
                                        <div class="fw-bold" id="preview_ident">--</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Ruta</div>
                                        <div id="preview_route">-- → --</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Aerolínea</div>
                                        <div id="preview_airline">--</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success btn-sm" id="submitEditBtn" disabled>
                        <i class="fas fa-check"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

  {{-- Modal para añadir nueva asignación --}}
  <div class="modal fade" id="addAssignmentModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
          <div class="modal-content" style="background: linear-gradient(135deg, #2a2633 0%, #1f1c27 100%); border: 1px solid #412c4d;">
              <div class="modal-header border-0 pb-0">
                  <button type="button" class="close" data-dismiss="modal" style="color:white;opacity:1;">&times;</button>
                  <h5 class="modal-title">
                      <i class="fas fa-plus-circle"></i>
                      Añadir nuevo vuelo asignado
                  </h5>
              </div>
              <form method="post" action="{{ route('DSpecial.assignments.add') }}">
                  @csrf
                  <input type="hidden" name="user_id" id="add_user_id">
                  <input type="hidden" name="year" id="add_year">
                  <input type="hidden" name="month" id="add_month">
                  <div class="modal-body">
                      <div class="mb-3">
                          <label class="form-label">Buscar vuelo</label>
                          <div class="row g-2 mb-2">
                              <div class="col-md-3">
                                  <input type="text" id="add_filter_airline" class="form-control form-control-sm" placeholder="Aerolínea">
                              </div>
                              <div class="col-md-3">
                                  <input type="text" id="add_filter_dep" class="form-control form-control-sm" placeholder="Origen ICAO">
                              </div>
                              <div class="col-md-3">
                                  <input type="text" id="add_filter_arr" class="form-control form-control-sm" placeholder="Destino ICAO">
                              </div>
                              <div class="col-md-3">
                                  <button type="button" id="searchFlightsAddBtn" class="btn btn-primary btn-sm w-100">
                                      <i class="fas fa-search"></i> Buscar
                                  </button>
                              </div>
                          </div>
                          <select name="flight_id" id="add_flight_select" class="form-control" required>
                              <option value="">Selecciona un vuelo...</option>
                          </select>
                      </div>
                      <div id="add_flight_preview" class="d-none mt-3">
                          <div class="card" style="background: rgba(65,44,77,0.2); border: 1px solid rgba(255,255,255,0.05);">
                              <div class="card-header py-2">
                                  <small class="text-muted">Vuelo seleccionado</small>
                              </div>
                              <div class="card-body py-2">
                                  <div class="row">
                                      <div class="col-md-4">
                                          <div class="text-muted small">Vuelo</div>
                                          <div class="fw-bold" id="add_preview_ident">--</div>
                                      </div>
                                      <div class="col-md-4">
                                          <div class="text-muted small">Ruta</div>
                                          <div id="add_preview_route">-- → --</div>
                                      </div>
                                      <div class="col-md-4">
                                          <div class="text-muted small">Aerolínea</div>
                                          <div id="add_preview_airline">--</div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="modal-footer border-0">
                      <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-success btn-sm" id="addSubmitBtn" disabled>Guardar</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

@endsection
@section('scripts')
  @parent
  <script>
  // Mover modales a <body> para que el backdrop de Bootstrap 3 no los tape
  $(function() {
      $('#editAssignmentModal, #addAssignmentModal').appendTo('body');
  });

  document.addEventListener('DOMContentLoaded', function() {
      const flightSelect = document.getElementById('flight_select');
      const searchBtn = document.getElementById('searchFlightsBtn');
      const submitBtn = document.getElementById('submitEditBtn');
      const flightPreview = document.getElementById('flight_preview');
      
      let selectedFlightId = null;
      
      // Función para cargar vuelos
      function loadFlights() {
          const airline = document.getElementById('filter_airline').value;
          const dep = document.getElementById('filter_dep').value;
          const arr = document.getElementById('filter_arr').value;
          
          // Mostrar indicador de carga
          flightSelect.innerHTML = '<option value="">Buscando vuelos...</option>';
          
          // Construir URL con parámetros
          let url = '{{ route("DSpecial.assignments.flights.search") }}?';
          const params = [];
          if (airline) params.push(`airline_id=${encodeURIComponent(airline)}`);
          if (dep) params.push(`dep_airport=${encodeURIComponent(dep)}`);
          if (arr) params.push(`arr_airport=${encodeURIComponent(arr)}`);
          
          console.log('Buscando vuelos con:', { airline, dep, arr });
          
          fetch(url + params.join('&'), {
              headers: {
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              }
          })
          .then(response => {
              if (!response.ok) {
                  throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
          })
          .then(data => {
              console.log('Respuesta del servidor:', data);
              flightSelect.innerHTML = '<option value="">Selecciona un vuelo...</option>';
              if (data.results && data.results.length > 0) {
                  data.results.forEach(flight => {
                      const option = document.createElement('option');
                      option.value = flight.id;
                      option.textContent = flight.text;
                      option.dataset.ident   = flight.ident   ?? '';
                      option.dataset.dep     = flight.dep     ?? '';
                      option.dataset.arr     = flight.arr     ?? '';
                      option.dataset.airline = flight.airline ?? '';
                      flightSelect.appendChild(option);
                  });
              } else {
                  flightSelect.innerHTML = '<option value="">No se encontraron vuelos con esos criterios</option>';
              }
          })
          .catch(error => {
              console.error('Error cargando vuelos:', error);
              flightSelect.innerHTML = '<option value="">Error al cargar vuelos. Revisa la consola.</option>';
          });
      }
      
      // Mostrar preview cuando se selecciona un vuelo
      flightSelect.addEventListener('change', function() {
          const opt = this.options[this.selectedIndex];
          if (this.value && this.value !== '') {
              selectedFlightId = this.value;
              submitBtn.disabled = false;
              document.getElementById('preview_ident').textContent   = opt.dataset.ident;
              document.getElementById('preview_route').textContent   = opt.dataset.dep + ' → ' + opt.dataset.arr;
              document.getElementById('preview_airline').textContent = opt.dataset.airline;
              flightPreview.classList.remove('d-none');
          } else {
              selectedFlightId = null;
              submitBtn.disabled = true;
              flightPreview.classList.add('d-none');
          }
      });
      
      // Botón de búsqueda
      searchBtn.addEventListener('click', loadFlights);
      
      // Enter en los filtros también busca
      document.querySelectorAll('#filter_airline, #filter_dep, #filter_arr').forEach(input => {
          input.addEventListener('keypress', function(e) {
              if (e.key === 'Enter') {
                  loadFlights();
              }
          });
      });
      
      // Abrir modal con los datos de la asignación
      document.querySelectorAll('.edit-assignment-btn').forEach(btn => {
          btn.addEventListener('click', function() {
              const assignmentId = this.dataset.assignmentId;
              const currentFlightIdent = this.dataset.flightIdent;
              const currentFlightRoute = this.dataset.flightRoute;
              
              document.getElementById('edit_assignment_id').value = assignmentId;
              document.getElementById('current_flight_ident').textContent = currentFlightIdent;
              document.getElementById('current_flight_route').textContent = currentFlightRoute;
              
              // Resetear formulario
              flightSelect.value = '';
              document.getElementById('filter_airline').value = '';
              document.getElementById('filter_dep').value = '';
              document.getElementById('filter_arr').value = '';
              flightPreview.classList.add('d-none');
              submitBtn.disabled = true;
              selectedFlightId = null;
              
              // Cargar vuelos iniciales
              loadFlights();
              
              $('#editAssignmentModal').modal('show');
          });
      });



  });

// Elementos del modal de añadir
const addFlightSelect = document.getElementById('add_flight_select');
const addSearchBtn = document.getElementById('searchFlightsAddBtn');
const addSubmitBtn = document.getElementById('addSubmitBtn');
const addPreview = document.getElementById('add_flight_preview');
let selectedAddFlightId = null;

function loadAddFlights() {
    const airline = document.getElementById('add_filter_airline').value;
    const dep = document.getElementById('add_filter_dep').value;
    const arr = document.getElementById('add_filter_arr').value;
    addFlightSelect.innerHTML = '<option value="">Buscando vuelos...</option>';
    let url = '{{ route("DSpecial.assignments.flights.search") }}?';
    const params = [];
    if (airline) params.push(`airline_id=${encodeURIComponent(airline)}`);
    if (dep) params.push(`dep_airport=${encodeURIComponent(dep)}`);
    if (arr) params.push(`arr_airport=${encodeURIComponent(arr)}`);
    fetch(url + params.join('&'), {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
    })
    .then(response => response.json())
    .then(data => {
        addFlightSelect.innerHTML = '<option value="">Selecciona un vuelo...</option>';
        if (data.results && data.results.length > 0) {
            data.results.forEach(flight => {
                const option = document.createElement('option');
                option.value = flight.id;
                option.textContent = flight.text;
                option.dataset.ident   = flight.ident   ?? '';
                option.dataset.dep     = flight.dep     ?? '';
                option.dataset.arr     = flight.arr     ?? '';
                option.dataset.airline = flight.airline ?? '';
                addFlightSelect.appendChild(option);
            });
        } else {
            addFlightSelect.innerHTML = '<option value="">No se encontraron vuelos</option>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        addFlightSelect.innerHTML = '<option value="">Error al cargar vuelos</option>';
    });
}

addFlightSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (this.value && this.value !== '') {
        selectedAddFlightId = this.value;
        addSubmitBtn.disabled = false;
        document.getElementById('add_preview_ident').textContent   = opt.dataset.ident;
        document.getElementById('add_preview_route').textContent   = opt.dataset.dep + ' → ' + opt.dataset.arr;
        document.getElementById('add_preview_airline').textContent = opt.dataset.airline;
        addPreview.classList.remove('d-none');
    } else {
        selectedAddFlightId = null;
        addSubmitBtn.disabled = true;
        addPreview.classList.add('d-none');
    }
});

addSearchBtn.addEventListener('click', loadAddFlights);
document.querySelectorAll('#add_filter_airline, #add_filter_dep, #add_filter_arr').forEach(input => {
    input.addEventListener('keypress', function(e) { if (e.key === 'Enter') loadAddFlights(); });
});

function openAddModal(userId, year, month) {
    document.getElementById('add_user_id').value = userId;
    document.getElementById('add_year').value = year;
    document.getElementById('add_month').value = month;
    // Resetear formulario
    addFlightSelect.value = '';
    document.getElementById('add_filter_airline').value = '';
    document.getElementById('add_filter_dep').value = '';
    document.getElementById('add_filter_arr').value = '';
    addPreview.classList.add('d-none');
    addSubmitBtn.disabled = true;
    selectedAddFlightId = null;
    loadAddFlights();
    $('#addAssignmentModal').modal('show');
}

  </script>
@endsection