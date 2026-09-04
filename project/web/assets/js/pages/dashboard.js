// Dashboard: cards + map utama + daftar monitoring aktif. Polling 15 dtk.
Pages.dashboard = {
  timer: null, map: null, markers: {},

  async render(el) {
    el.innerHTML = `
      <div class="cards" data-testid="dashboard-cards">
        <div class="stat-card blue"><div class="label">Total Personel</div><div class="value" data-testid="stat-personnel" id="st-personnel">-</div></div>
        <div class="stat-card green"><div class="label">Sedang Tracking</div><div class="value" data-testid="stat-tracking" id="st-tracking">-</div></div>
        <div class="stat-card green"><div class="label">Online</div><div class="value" data-testid="stat-online" id="st-online">-</div></div>
        <div class="stat-card yellow"><div class="label">Offline / Terlambat</div><div class="value" data-testid="stat-offline" id="st-offline">-</div></div>
        <div class="stat-card red"><div class="label">Alert Terbuka</div><div class="value" data-testid="stat-alerts" id="st-alerts">-</div></div>
      </div>
      <div class="split">
        <div class="panel">
          <div class="panel-head"><h3>Peta Posisi Personel</h3></div>
          <div class="panel-body">
            <div id="dash-map" data-testid="dashboard-map" class="map-box"></div>
            ${UI.legendHtml}
          </div>
        </div>
        <div class="panel">
          <div class="panel-head"><h3>Monitoring Aktif</h3></div>
          <div class="panel-body flush" id="dash-sessions" data-testid="active-sessions"><div class="empty">Memuat...</div></div>
        </div>
      </div>`;
    this.map = UI.makeMap('dash-map');
    this.markers = {};
    await this.refresh();
    this.timer = setInterval(() => this.refresh(), 15000);
  },

  async refresh() {
    try {
      const stats = await Api.get('/dashboard');
      document.getElementById('st-personnel').textContent = stats.total_personnel;
      document.getElementById('st-tracking').textContent = stats.tracking;
      document.getElementById('st-online').textContent = stats.online;
      document.getElementById('st-offline').textContent = stats.offline;
      document.getElementById('st-alerts').textContent = stats.open_alerts;
      document.getElementById('server-time').textContent = 'Server: ' + UI.fmtDateTime(stats.server_time);

      const loc = await Api.get('/dashboard/locations');
      const seen = new Set();
      (loc.markers || []).forEach(m => {
        if (m.latitude === null) return;
        seen.add(m.personnel_id);
        if (this.markers[m.personnel_id]) {
          const mk = this.markers[m.personnel_id];
          mk.setLatLng([m.latitude, m.longitude]);
          mk.setStyle({ fillColor: UI.markerColor(m.status) });
        } else {
          this.markers[m.personnel_id] = UI.addMarker(this.map, m);
        }
      });
      Object.keys(this.markers).forEach(pid => {
        if (!seen.has(parseInt(pid))) {
          this.map.removeLayer(this.markers[pid]);
          delete this.markers[pid];
        }
      });
      const withLoc = (loc.markers || []).filter(m => m.latitude !== null);
      if (withLoc.length && !this._fitted) {
        this.map.fitBounds(withLoc.map(m => [m.latitude, m.longitude]), { padding: [30, 30] });
        this._fitted = true;
      }

      const box = document.getElementById('dash-sessions');
      box.innerHTML = (loc.active_sessions || []).length ? `<table class="tbl"><thead>
          <tr><th>Nama</th><th>Type</th><th>Personel</th><th>Selesai</th></tr></thead><tbody>
          ${loc.active_sessions.map(s => `<tr>
            <td><a href="#monitoring" data-sid="${s.id}">${UI.esc(s.name)}</a></td>
            <td>${UI.statusBadge(s.type)}</td>
            <td>${s.personnel_count}</td>
            <td>${UI.fmtDateTime(s.end_at)}</td></tr>`).join('')}
          </tbody></table>` : '<div class="empty">Tidak ada monitoring aktif.</div>';
    } catch (e) { /* jangan hancurkan map jika update gagal */ }
  },

  destroy() { clearInterval(this.timer); this._fitted = false; }
};
