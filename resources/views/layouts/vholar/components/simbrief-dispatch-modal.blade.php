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
                {{-- Sugerido de PAX y carga: lo rellena el endpoint de despacho --}}
                <div id="sb-suggestion" class="mb-3" style="display:none;">
                    <div class="d-flex align-items-start gap-2 p-3"
                         style="background:var(--vh-primary-active); border:1px solid var(--vh-primary); border-radius:10px;">
                        <i class="bi bi-calculator" id="sb-suggestion-icon"
                           style="color:var(--vh-silver-dim); font-size:1.05rem; line-height:1.3;"></i>
                        <div style="font-size:0.84rem; line-height:1.45; min-width:0;">
                            <div id="sb-suggestion-title" class="fw-bold" style="color:var(--vh-white);"></div>
                            <div id="sb-suggestion-notes" style="color:var(--vh-text);"></div>
                        </div>
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

    // --- Sugerido de PAX y carga -------------------------------------------------
    // Lo calcula el servidor (DispatchSuggestionService) con el coste real del
    // libro; aqui solo se pinta y se rellenan los campos. Si falla, se mantiene
    // la carga por defecto de sbDefaultPax, para que el despacho nunca se rompa.
    const SB_SUGGESTION_URL = @json(route('vholar.dispatch.suggestion'));
    const sbNoteColors = { ok: 'var(--vh-success)', warn: 'var(--vh-warning)', danger: 'var(--vh-danger)', info: 'var(--vh-silver-dim)' };
    const sbNoteIcons  = { ok: 'bi-check-circle-fill', warn: 'bi-exclamation-triangle-fill', danger: 'bi-x-octagon-fill', info: 'bi-info-circle-fill' };
    let sbRequestSeq = 0;
    // `type` que se manda a SimBrief: el airframe del piloto/avion si el endpoint
    // lo resuelve, y mientras tanto (o si el endpoint falla) el ICAO del boton.
    let sbSimBriefType = '';

    function sbSetSuggestion(title, notes, icon) {
        const box     = document.getElementById('sb-suggestion');
        const titleEl = document.getElementById('sb-suggestion-title');
        const notesEl = document.getElementById('sb-suggestion-notes');
        const iconEl  = document.getElementById('sb-suggestion-icon');

        if (!title && (!notes || notes.length === 0)) {
            box.style.display = 'none';
            return;
        }

        iconEl.className = 'bi ' + (icon || 'bi-calculator');
        titleEl.textContent = title || '';
        titleEl.style.display = title ? '' : 'none';

        notesEl.innerHTML = '';
        (notes || []).forEach(function (n) {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-start gap-1 mt-1';

            const i = document.createElement('i');
            i.className = 'bi ' + (sbNoteIcons[n.level] || sbNoteIcons.info);
            i.style.color = sbNoteColors[n.level] || sbNoteColors.info;
            i.style.fontSize = '0.8rem';
            i.style.lineHeight = '1.45';
            i.style.flex = '0 0 auto';

            const span = document.createElement('span');
            span.textContent = n.text;

            row.appendChild(i);
            row.appendChild(span);
            notesEl.appendChild(row);
        });

        box.style.display = '';
    }

    function sbMoney(value) {
        if (value === null || value === undefined) return '';
        return '$' + Math.round(value).toLocaleString('es-CO');
    }

    function sbApplySuggestion(data) {
        if (!data || data.ok === false) {
            sbSetSuggestion('', [{ level: 'info', text: 'No se pudo calcular el sugerido.' }]);
            return;
        }

        // El airframe lo resuelve el servidor (campo de perfil del piloto, luego
        // el del avion/subflota, luego el ICAO). Si no viene, se queda el del boton.
        if (data.simbrief && data.simbrief.type) {
            sbSimBriefType = data.simbrief.type;
        }

        if (data.applicable === false) {
            // Las operaciones no regulares (CH/CA/PS/FR) traen su codigo de ruta;
            // el numero de vuelo de esas operaciones es el id del piloto.
            const title = data.route_code
                ? ('Operacion no regular (' + data.route_code + ')')
                : (data.flight_number ? ('Vuelo ' + data.flight_number) : 'Sin sugerido');
            sbSetSuggestion(title, data.notes || [], 'bi-info-circle');
            return;
        }

        const s = data.suggestion || {};

        document.getElementById('sb-pax').value   = s.pax !== undefined ? s.pax : '';
        document.getElementById('sb-cargo').value = s.cargo !== undefined ? s.cargo : '';

        const parts = [s.pax + ' pax'];
        if (s.cargo > 0) parts.push(Math.round(s.cargo).toLocaleString('es-CO') + ' kg');

        sbSetSuggestion(
            'Sugerido: ' + parts.join(' + ') + ' (objetivo ' + sbMoney(s.target) + ')',
            data.notes || [],
            'bi-calculator'
        );
    }

    function sbLoadSuggestion(d) {
        const seq = ++sbRequestSeq;

        if (!d.flightId) {
            sbSetSuggestion('', [{ level: 'info', text: 'Sin datos del vuelo: se mantiene la carga por defecto.' }]);
            return;
        }

        sbSetSuggestion('Calculando el sugerido…', [], 'bi-hourglass-split');

        const params = new URLSearchParams({
            flight_id:   d.flightId,
            aircraft_id: d.aircraftId || '',
            actype:      d.actype || '',
            acreg:       d.acreg || '',
        });

        fetch(SB_SUGGESTION_URL + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (seq !== sbRequestSeq) return;   // respuesta obsoleta
                sbApplySuggestion(data);
            })
            .catch(function () {
                if (seq !== sbRequestSeq) return;
                sbSetSuggestion('', [{ level: 'info', text: 'No se pudo calcular el sugerido; se mantiene la carga por defecto.' }]);
            });
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

        // Hasta que responda el endpoint, se manda el tipo del boton.
        sbSimBriefType = d.actype || '';

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

        sbLoadSuggestion(d);
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
            type:       sbSimBriefType || document.getElementById('sb-actype').value,
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
        // `fl` va en PIES, no en centenas: la tabla oficial de parametros de
        // SimBrief da "34000, FL340" como valores validos (y "altn_#_fl" si es
        // el nivel en centenas). La API v1 de phpVMS tambien manda el nivel del
        // vuelo tal cual, en pies. Mandar 330 para 33.000 ft planificaba mal.
        if (flFt > 0) params.append('fl', flFt);
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
