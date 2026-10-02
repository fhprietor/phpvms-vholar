<style>
.vholar-logbook-wrap {
  border-radius: 10px;
  overflow: hidden;
  background-color: var(--vh-bg) !important;
}
.vholar-logbook {
  margin-bottom: 0;
  border-collapse: collapse;
}
.vholar-logbook thead tr {
  border-bottom: 1px solid rgba(255,255,255,0.10);
  background: rgba(0,0,0,0.28);
}
.vholar-logbook thead th {
  font-size: 0.65rem;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: var(--vh-text-muted);
  padding: 10px 14px;
  font-weight: 700;
  white-space: nowrap;
  border: none;
  background: transparent;
}
.vholar-logbook tbody tr {
  border-bottom: 1px solid rgba(255,255,255,0.07);
  transition: background 0.12s;
}
.vholar-logbook tbody tr:last-child {
  border-bottom: none;
}
.vholar-logbook tbody tr:hover {
  background: rgba(255,255,255,0.05) !important;
}
.vholar-logbook tbody td {
  padding: 11px 14px;
  vertical-align: middle;
  border: none;
}
.lb-date { min-width: 56px; }
.lb-date .lb-day {
  display: block;
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1;
  color: var(--vh-text);
  font-variant-numeric: tabular-nums;
}
.lb-date .lb-monyear {
  display: block;
  font-size: 0.65rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--vh-text-muted);
  margin-top: 2px;
}
.lb-fltnum {
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-decoration: none;
  color: var(--vh-text) !important;
}
.lb-fltnum:hover { color: var(--vh-silver) !important; text-decoration: none; }
.lb-airline {
  display: block;
  font-size: 0.65rem;
  color: var(--vh-text-muted);
  letter-spacing: 0.04em;
  margin-top: 1px;
}
.lb-route {
  display: flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}
.lb-icao {
  font-family: 'Courier New', monospace;
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--vh-text);
}
.lb-route-arrow {
  display: flex;
  align-items: center;
  /* Era #7878a0 sobre fondo oscuro: 4.45:1, bajo AA para 12px. */
  color: var(--vh-silver-dim);
  font-size: 0.75rem;
  flex-shrink: 0;
}
.lb-cities {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 3px;
}
.lb-cities small {
  font-size: 0.62rem;
  color: var(--vh-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  max-width: 110px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.lb-ac-type {
  display: inline-block;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  background: var(--vh-primary-soft);
  color: var(--vh-text-muted);
  border-radius: 4px;
  padding: 2px 6px;
  text-transform: uppercase;
  vertical-align: middle;
}
.lb-ac-reg {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--vh-text-muted);
  margin-top: 2px;
  letter-spacing: 0.04em;
}
.lb-stat-val {
  font-size: 1rem;
  font-weight: 800;
  color: var(--vh-text);
  font-variant-numeric: tabular-nums;
}
.lb-time {
  font-family: 'Courier New', monospace;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--vh-text-muted);
  letter-spacing: 0.03em;
}
.lb-blocktime { white-space: nowrap; }
.lb-blocktime-val {
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: var(--vh-text);
  font-variant-numeric: tabular-nums;
}
.lb-blocktime-icon {
  color: var(--vh-text-muted);
  font-size: 0.75rem;
  margin-right: 4px;
}
.lb-score {
  font-size: 0.9rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
.lb-score-good { color: var(--vh-success); }
.lb-score-ok   { color: var(--vh-warning); }
.lb-score-bad  { color: var(--vh-danger); }
.lb-state {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 3px 9px;
  border-radius: 20px;
}
.lb-edit-btn {
  font-size: 0.7rem;
  padding: 3px 10px;
  border-radius: 6px;
  white-space: nowrap;
}
.lb-totals-row { border-top: 1px solid rgba(255,255,255,0.10) !important; }
.lb-totals-row td {
  padding: 10px 14px;
  font-size: 0.72rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--vh-text-muted);
}
.lb-totals-row .lb-total-val {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--vh-text);
  letter-spacing: 0.02em;
}
@media (max-width: 768px) {
  .lb-cities, .lb-airline { display: none; }
}
</style>
