// Helper UI bersama: modal, badge, escape, format waktu, map markers.
const UI = {
  esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); },

  modal(title, bodyHtml, opts = {}) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `<div class="modal ${opts.wide ? 'wide' : ''}"><h3>${UI.esc(title)}</h3><div class="modal-content">${bodyHtml}</div></div>`;
    backdrop.addEventListener('click', e => { if (e.target === backdrop) backdrop.remove(); });
    document.body.appendChild(backdrop);
    return backdrop;
  },

  confirm(message, onYes, danger = true) {
    const m = UI.modal('Konfirmasi', `
      <p style="margin-bottom:16px">${UI.esc(message)}</p>
      <div class="form-actions">
        <button class="btn" data-act="no">Batal</button>
        <button class="btn ${danger ? 'danger' : 'primary'}" data-act="yes">Ya, Lanjutkan</button>
      </div>`);
    m.querySelector('[data-act=no]').onclick = () => m.remove();
    m.querySelector('[data-act=yes]').onclick = () => { m.remove(); onYes(); };
  },

  toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = 'alert-bar ' + (type === 'error' ? 'error' : 'success');
    el.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2000;box-shadow:0 2px 12px rgba(0,0,0,.15);max-width:360px';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  },

  badge(text, color) { return `<span class="badge ${color}">${UI.esc(text)}</span>`; },

  statusBadge(s) {
    const map = { ACTIVE: ['green','AKTIF'], SCHEDULED: ['blue','TERJADWAL'], COMPLETED: ['gray','SELESAI'],
      CANCELLED: ['gray','DIBATALKAN'], PENDING: ['yellow','PENDING'], REVOKED: ['red','REVOKED'],
      INACTIVE: ['gray','NONAKTIF'], OPEN: ['red','OPEN'], ACKNOWLEDGED: ['yellow','DIPROSES'], RESOLVED: ['green','SELESAI'],
      ONLINE: ['green','ONLINE'], TERLAMBAT: ['yellow','TERLAMBAT'], OFFLINE: ['gray','OFFLINE'],
      TRACKING: ['green','TRACKING'], ALERT: ['red','ALERT'], NO_DEVICE: ['gray','TANPA PERANGKAT'],
      IB: ['blue','IB'], QUICK_CHECK: ['yellow','QUICK CHECK'],
      ENTER: ['red','MASUK'], INSIDE: ['yellow','DI DALAM'], EXIT: ['blue','KELUAR'] };
    const [c, label] = map[s] || ['gray', s];
    return UI.badge(label || s, c);
  },

  fmtDateTime(s) { if (!s) return '-'; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); },
  fmtTime(s) { if (!s) return '-'; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleTimeString('id-ID'); },
  fmtDuration(sec) { if (!sec) return '-'; const h = Math.floor(sec / 3600), m = Math.round((sec % 3600) / 60); return h ? `${h} jam ${m} mnt` : `${m} mnt`; },
  batteryText(b) { if (b === null || b === undefined) return '-'; return b + '%' + (b <= 8 ? ' (KRITIS)' : b <= 15 ? ' (RENDAH)' : ''); },

  markerColor(status) {
    return { TRACKING: '#16a34a', ONLINE: '#16a34a', TERLAMBAT: '#ca8a04', ALERT: '#dc2626' }[status] || '#9ca3af';
  },

  addMarker(map, m) {
    if (m.latitude === null || m.longitude === null) return null;
    const marker = L.circleMarker([m.latitude, m.longitude], {
      radius: 8, color: '#fff', weight: 2, fillColor: UI.markerColor(m.status), fillOpacity: 1
    }).addTo(map);
    marker.bindPopup(`
      <b>${UI.esc(m.name)}</b><br>
      NRP: ${UI.esc(m.nrp)}<br>
      Pangkat: ${UI.esc(m.rank || '-')}<br>
      ${UI.esc(m.company_name || '-')} / ${UI.esc(m.platoon_name || '-')}<br>
      Status: ${UI.esc(m.status)}<br>
      Battery: ${UI.esc(UI.batteryText(m.battery))}<br>
      Akurasi: ${m.accuracy ? Math.round(m.accuracy) + ' m' : '-'}<br>
      Update: ${UI.fmtTime(m.last_update)}`);
    return marker;
  },

  makeMap(elId, center = [-2.5, 118.0], zoom = 5) {
    const map = L.map(elId).setView(center, zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    return map;
  },

  legendHtml: `<div class="legend">
    <span class="l-green">Online / Tracking</span>
    <span class="l-yellow">Terlambat</span>
    <span class="l-red">Alert</span>
    <span class="l-white">Standby / Inactive</span>
  </div>`,

  // Pagination renderer: onGo(page)
  pagination(container, data, onGo) {
    const pages = Math.max(1, Math.ceil(data.total / data.per_page));
    container.innerHTML = `Total ${data.total} data &nbsp;
      <button class="btn sm" ${data.page <= 1 ? 'disabled' : ''} data-p="${data.page - 1}">&laquo;</button>
      <span>Hal ${data.page} / ${pages}</span>
      <button class="btn sm" ${data.page >= pages ? 'disabled' : ''} data-p="${data.page + 1}">&raquo;</button>`;
    container.querySelectorAll('button[data-p]').forEach(b => b.onclick = () => onGo(parseInt(b.dataset.p)));
  },

  async loadOrganizations() {
    if (!UI._orgs) { UI._orgs = (await Api.get('/organizations')).items; }
    return UI._orgs;
  },
  orgOptions(orgs, type, selectedId) {
    return orgs.filter(o => o.type === type)
      .map(o => `<option value="${o.id}" ${String(o.id) === String(selectedId) ? 'selected' : ''}>${UI.esc(o.name)}</option>`).join('');
  }
};
window.Pages = {};
