// Alert: list + filter status + acknowledge/resolve + lihat lokasi di map.
Pages.alerts = {
  state: { status: '', page: 1 },

  async render(el) {
    this.el = el;
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Alert Geofence</h3>
          <div class="toolbar">
            <select id="al-status" data-testid="alert-status-filter">
              <option value="">Semua Status</option>
              <option value="OPEN">OPEN</option>
              <option value="ACKNOWLEDGED">DIPROSES</option>
              <option value="RESOLVED">SELESAI</option>
            </select>
          </div>
        </div>
        <div class="panel-body flush" id="alert-table" data-testid="alert-table"><div class="empty">Memuat...</div></div>
        <div class="pagination" id="alert-pager"></div>
      </div>`;
    document.getElementById('al-status').onchange = (e) => {
      this.state.status = e.target.value; this.state.page = 1; this.load();
    };
    await this.load();
  },

  typeLabel(t) {
    return { ENTER: 'Memasuki Area Terlarang', INSIDE: 'Sedang berada di Area Terlarang', EXIT: 'Keluar dari Area Terlarang' }[t] || t;
  },

  async load() {
    try {
      const s = this.state;
      const data = await Api.get(`/alerts?page=${s.page}&status=${s.status}`);
      const box = document.getElementById('alert-table');
      box.innerHTML = data.items.length ? `<table class="tbl"><thead><tr>
          <th>Personel</th><th>NRP</th><th>Jenis</th><th>Area</th><th>Waktu</th><th>Status</th><th>Aksi</th>
        </tr></thead><tbody>${data.items.map(a => `<tr>
          <td>${UI.esc(a.personnel_name)}</td><td>${UI.esc(a.nrp)}</td>
          <td>${UI.statusBadge(a.type)} ${UI.esc(this.typeLabel(a.type))}</td>
          <td>${UI.esc(a.geofence_name || '-')}</td>
          <td>${UI.fmtDateTime(a.occurred_at)}</td>
          <td>${UI.statusBadge(a.status)}</td>
          <td>
            ${a.latitude ? `<button class="btn sm" data-map="${a.id}" data-lat="${a.latitude}" data-lng="${a.longitude}" data-testid="alert-map-${a.id}">Map</button>` : ''}
            ${a.status === 'OPEN' ? `<button class="btn sm" data-ack="${a.id}" data-testid="alert-ack-${a.id}">Proses</button>` : ''}
            ${a.status !== 'RESOLVED' ? `<button class="btn sm success" data-resolve="${a.id}" data-testid="alert-resolve-${a.id}">Selesai</button>` : ''}
          </td></tr>`).join('')}</tbody></table>` : '<div class="empty">Tidak ada alert.</div>';

      box.querySelectorAll('[data-ack]').forEach(b => b.onclick = () => this.setStatus(b.dataset.ack, 'ACKNOWLEDGED'));
      box.querySelectorAll('[data-resolve]').forEach(b => b.onclick = () => this.setStatus(b.dataset.resolve, 'RESOLVED'));
      box.querySelectorAll('[data-map]').forEach(b => b.onclick = () => {
        const m = UI.modal('Lokasi Alert', `<div id="alert-map" class="map-box sm"></div>
          <div class="form-actions"><button class="btn" data-act="x">Tutup</button></div>`);
        m.querySelector('[data-act=x]').onclick = () => m.remove();
        const map = UI.makeMap('alert-map', [parseFloat(b.dataset.lat), parseFloat(b.dataset.lng)], 16);
        L.marker([parseFloat(b.dataset.lat), parseFloat(b.dataset.lng)]).addTo(map);
      });
      UI.pagination(document.getElementById('alert-pager'), data, p => { this.state.page = p; this.load(); });
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  async setStatus(id, status) {
    try { await Api.put(`/alerts/${id}/status`, { status }); this.load(); }
    catch (e) { UI.toast(e.message, 'error'); }
  }
};
