<div class="modal fade" id="simbriefDispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:var(--vh-surface); color:var(--vh-text); border:1px solid var(--vh-primary);">
            <div class="modal-header" style="background:var(--vh-primary); border-bottom:1px solid rgba(255,255,255,0.1);">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cloud-upload me-2"></i>SimBrief Dispatch
                    <span class="ms-2 fw-normal" style="color:rgba(255,255,255,0.6); font-size:0.9rem;" id="sb-modal-ident"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white sb-close-btn"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-center gap-4 mb-4 p-3"
                     style="background:var(--vh-primary-active); border-radius:10px;">
                    <div class="text-center">
                        <div style="font-size:2rem; font-weight:700; color:var(--vh-white);" id="sb-orig-code"></div>
                        <div class="text-muted small" id="sb-orig-name-disp"></div>
                    </div>
                    <i class="bi bi-arrow-right" style="font-size:1.5rem; color:var(--vh-silver-dim);"></i>
                    <div class="text-center">
                        <div style="font-size:2rem; font-weight:700; color:var(--vh-white);" id="sb-dest-code"></div>
                        <div class="text-muted small" id="sb-dest-name-disp"></div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">VUELO</label>
                        <input type="text" class="form-control form-control-sm sb-readonly" id="sb-fltnum" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">TIPO AERONAVE</label>
                        <input type="text" class="form-control form-control-sm sb-readonly" id="sb-actype" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">MATRÍCULA</label>
                        <input type="text" class="form-control form-control-sm sb-readonly" id="sb-acreg" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">COMANDANTE</label>
                        <input type="text" class="form-control form-control-sm sb-readonly" id="sb-cpt" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">SALIDA UTC</label>
                        <input type="time" class="form-control form-control-sm sb-editable" id="sb-deptm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">NIVEL DE VUELO (ft)</label>
                        <input type="number" class="form-control form-control-sm sb-editable" id="sb-fl"
                               min="1000" max="60000" step="1000" placeholder="ej. 36000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">COST INDEX</label>
                        <input type="number" class="form-control form-control-sm sb-editable" id="sb-ci" value="30" min="0" max="999">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">TIPO VUELO</label>
                        <select class="form-select form-select-sm sb-editable" id="sb-flighttype">
                            <option value="s">Scheduled</option>
                            <option value="c">Charter</option>
                            <option value="m">Military</option>
                            <option value="x">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">PAX</label>
                        <input type="number" class="form-control form-control-sm sb-editable" id="sb-pax" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">CARGO (kgs)</label>
                        <input type="number" class="form-control form-control-sm sb-editable" id="sb-cargo" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">PISTA SALIDA</label>
                        <input type="text" class="form-control form-control-sm sb-editable" id="sb-deprwy" placeholder="ej. 03L">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">PISTA LLEGADA</label>
                        <input type="text" class="form-control form-control-sm sb-editable" id="sb-arrrwy" placeholder="ej. 21R">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">RUTA</label>
                        <textarea class="form-control form-control-sm sb-editable" id="sb-route" rows="2"
                                  style="resize:vertical;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,0.1);">
                <button type="button" class="btn btn-sm btn-secondary sb-close-btn">Cerrar</button>
                <button type="button" class="btn btn-sm btn-primary" id="sb-generate-btn">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Generar OFP
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.sb-readonly { background:var(--vh-primary-active) !important; color:var(--vh-text-muted) !important; border-color:var(--vh-primary) !important; cursor:default !important; }
.sb-editable { background:#28212F80 !important; color:var(--vh-text) !important; border-color:var(--vh-primary) !important; }
.sb-editable:focus { background:var(--vh-surface-2) !important; border-color:var(--vh-silver-dim) !important; box-shadow:0 0 0 2px var(--vh-focus-ring) !important; }
</style>

@push('scripts')
<script>
(function () {
    if (window._sbDispatchInit) return;
    window._sbDispatchInit = true;

    const sbModalEl = document.getElementById('simbriefDispatchModal');
    const sbModal   = new bootstrap.Modal(sbModalEl);
    const sbCaptain = @json(Auth::user()->name ?? '');

    // Close buttons — explicit hide() avoids data-bs-dismiss conflicts
    sbModalEl.querySelectorAll('.sb-close-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { sbModal.hide(); });
    });

    function sbDefaultPax(type) {
        type = (type || '').toUpperCase();
        if (/^(A38|B74)/.test(type))               return 400 + Math.floor(Math.random() * 60);
        if (/^(A35|B78)/.test(type))               return 270 + Math.floor(Math.random() * 40);
        if (/^(A33|B76|B77)/.test(type))           return 220 + Math.floor(Math.random() * 50);
        if (/^(A32|B73)/.test(type))               return 140 + Math.floor(Math.random() * 30);
        if (/^(AT[457]|E[12]|CRJ|DH8)/.test(type)) return 50  + Math.floor(Math.random() * 30);
        return 120 + Math.floor(Math.random() * 50);
    }

    function sbDefaultFL(type) {
        type = (type || '').toUpperCase();
        if (/^(A38|B74|A35|B78|A33|B76|B77)/.test(type)) return 36000;
        if (/^(A32|B73)/.test(type))                      return 35000;
        if (/^(AT[457]|DH8)/.test(type))                  return 18000;
        if (/^(E[12]|CRJ)/.test(type))                    return 28000;
        return 33000;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sb-dispatch-btn');
        if (!btn) return;
        const d = btn.dataset;

        document.getElementById('sb-modal-ident').textContent    = (d.airline || '') + (d.fltnum || '');
        document.getElementById('sb-orig-code').textContent      = d.orig || '';
        document.getElementById('sb-dest-code').textContent      = d.dest || '';
        document.getElementById('sb-orig-name-disp').textContent = d.origName || '';
        document.getElementById('sb-dest-name-disp').textContent = d.destName || '';
        document.getElementById('sb-fltnum').value = (d.airline || '') + (d.fltnum || '');
        document.getElementById('sb-actype').value = d.actype || '';
        document.getElementById('sb-acreg').value  = d.acreg  || '';
        document.getElementById('sb-cpt').value    = sbCaptain;

        const t = new Date(Date.now() + 40 * 60000);
        document.getElementById('sb-deptm').value =
            String(t.getUTCHours()).padStart(2,'0') + ':' + String(t.getUTCMinutes()).padStart(2,'0');

        document.getElementById('sb-fl').value = sbDefaultFL(d.actype);

        const pax = sbDefaultPax(d.actype);
        document.getElementById('sb-pax').value   = pax;
        document.getElementById('sb-cargo').value = Math.round(pax * 25 + Math.floor(Math.random() * pax * 7.5));
        document.getElementById('sb-deprwy').value = '';
        document.getElementById('sb-arrrwy').value = '';
        document.getElementById('sb-route').value  = d.route || '';

        const gen = document.getElementById('sb-generate-btn');
        gen.dataset.airline = d.airline || '';
        gen.dataset.fltnum  = d.fltnum  || '';
        gen.dataset.orig    = d.orig    || '';
        gen.dataset.dest    = d.dest    || '';

        sbModal.show();
    });

    document.getElementById('sb-generate-btn').addEventListener('click', function () {
        const d  = this.dataset;
        const tm = (document.getElementById('sb-deptm').value || '00:00').split(':');
        const flFt = parseInt(document.getElementById('sb-fl').value, 10) || 0;
        const params = new URLSearchParams({
            airline:    d.airline,
            fltnum:     d.fltnum,
            orig:       d.orig,
            dest:       d.dest,
            type:       document.getElementById('sb-actype').value,
            reg:        document.getElementById('sb-acreg').value,
            cpt:        document.getElementById('sb-cpt').value,
            civalue:    document.getElementById('sb-ci').value || '30',
            units:      'kgs',
            maps:       'detailed',
            static_url: '1',
            deph:       tm[0] || '00',
            depm:       tm[1] || '00',
            extrarmk:   'OPR/' + d.airline + ' CS/VHOLAR IVAOVA/' + d.airline,
            flighttype: document.getElementById('sb-flighttype').value,
        });
        if (flFt > 0) params.append('fl', Math.round(flFt / 100));
        const pax    = document.getElementById('sb-pax').value;
        const cargo  = document.getElementById('sb-cargo').value;
        const deprwy = document.getElementById('sb-deprwy').value.trim();
        const arrrwy = document.getElementById('sb-arrrwy').value.trim();
        const route  = document.getElementById('sb-route').value.trim();
        if (pax)    params.append('pax',    pax);
        if (cargo)  params.append('cargo',  cargo);
        if (deprwy) params.append('origrwy', deprwy);
        if (arrrwy) params.append('destrwy', arrrwy);
        if (route)  params.append('route',  route);

        window.open('https://dispatch.simbrief.com/options/custom?' + params.toString(), '_blank');
        sbModal.hide();
    });
})();
</script>
@endpush
