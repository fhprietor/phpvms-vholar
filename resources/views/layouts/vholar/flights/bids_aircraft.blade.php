<div class="modal fade" id="bidModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="addBidLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content vholar-modal">
            <div class="modal-header vholar-modal-header">
                <h5 class="modal-title" id="bidModalLabel">
                    <i class="bi bi-airplane-fill me-2"></i>
                    {{ __('flights.aircraftbooking') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" id="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body vholar-modal-body">
                <p class="modal-description">Selecciona la aeronave que deseas reservar para este vuelo:</p>
                <select name="" id="aircraft_select" class="bid_aircraft form-control vholar-select"></select>
            </div>
            <div class="modal-footer vholar-modal-footer">
                {{-- Solo mostrar el botón "Sin aeronave" si la configuración lo permite --}}
                {{-- 
                @if(setting('bids.allow_without_aircraft', true))
                    <button type="button" id="without_aircraft" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> {{ __('flights.dontbookaircraft') }}
                    </button>
                @endif
                 --}}
                <button type="button" id="with_aircraft" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-check-circle"></i> {{ __('flights.bookaircraft') }}
                </button>
            </div>
        </div>
    </div>
</div>