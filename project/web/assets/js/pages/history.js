// Riwayat: pilih personel + tanggal + session -> route map, statistik, alert.
Pages.history = {
  map: null,

  async render(el) {
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Riwayat Pergerakan</h3></div>
        <div class="panel-body">
          <div class="toolbar mb16">
            <input id="hs-search" data-testid="history-search" placeholder="Cari NRP / Nama personel..." style="width:240px">
            <input type="date" id="hs-date" data-testid="history-date">
            <select id="hs-session" data-testid="history-session" style="display:none"></select>
            <button class="btn primary" id="hs-load" data-testid="history-load" style="display:none">Tampilkan</button>
          </div>
          <div class="search-results" id="hs-results" style="display:none"></div>
          <div id="hs-personel" class="mb16"></div>
          <div id="hs-stats" class="mb16"></div>
          <div id="hs-map-box" style="display:none">
            <div id="hs-map" data-testid="history-map" class="map-box"></div>
          </div>
          <div id="hs-alerts" class="mt16"></div>
          <p class="muted mt16">Detail GPS disimpan sekitar 90 hari.</p>
        </div>
      </div>`;
    this.selected = null;
    let deb;
    document.getElementById('hs-search').oninput = (e) => {
      clearTimeout(deb);
      const q = e.target.value.trim();
      const res = document.getElementById('hs-results');
      if (q.length < 2) { res.style.display = 'none'; return; }
      deb = setTimeout(async () => {
        const data = await Api.get('/personnel?per_page=10&q=' + encodeURIComponent(q));
        res.style.display = '';
        res.innerHTML = data.items.map(p =>
          `<div data-pick="${p.id}" data-label="${UI.esc(p.nrp)} - ${UI.esc(p.name)}">${UI.esc(p.nrp)} - ${UI.esc(p.name)} (${UI.esc(p.company_name || '-')})</div>`).join('')
          || '<div>Tidak ditemukan.</div>';
        res.querySelectorAll('[data-pick]').forEach(d => d.onclick = () => {
          this.selected = d.dataset.pick;
          document.getElementById('hs-personel').innerHTML = `<b>Personel:</b> ${UI.esc(d.dataset.label)}`;
          res.style.display = 'none';
          document.getElementById('hs-load').style.display = '';
          this.loadSessions();
        });
      }, 350);
    };
    document.getElementById('hs-load').onclick = () => this.loadHistory();
  },

  async loadSessions() {
    const sel = document.getElementById('hs-session');
    sel.style.display = 'none';
    sel.innerHTML = '';
    this._sessionList = [];
    // session list diambil bersama history pertama kali; gunakan tanpa filter dulu
  },

  async loadHistory() {
    if (!this.selected) { UI.toast('Pilih personel dulu.', 'error'); return; }
    const date = document.getElementById('hs-date').value;
    const sel = document.getElementById('hs-session');
    let url = '/history/personnel/' + this.selected + '?';
    if (date) { url += `from=${date} 00:00:00&to=${date} 23:59:59&`; }
    if (sel.value) url += 'session_id=' + sel.value;
    try {
      const data = await Api.get(url);
      // isi dropdown session sekali
      if (!sel.options.length && data.sessions.length) {
        sel.innerHTML = '<option value="">Semua Session</option>' + data.sessions.map(s =>
          `<option value="${s.id}">${UI.esc(s.name)} (${UI.esc(s.type)})</option>`).join('');
        sel.style.display = '';
      }
      document.getElementById('hs-stats').innerHTML = `
        <div class="cards" style="margin-bottom:0">
          <div class="stat-card blue"><div class="label">Total GPS Point</div><div class="value">${data.total_points}</div></div>
          <div class="stat-card"><div class="label">Durasi</div><div class="value" style="font-size:20px">${UI.fmtDuration(data.duration_seconds)}</div></div>
          <div class="stat-card red"><div class="label">Alert</div><div class="value">${data.alerts.length}</div></div>
        </div>`;

      const mapBox = document.getElementById('hs-map-box');
      if (data.points.length) {
        mapBox.style.display = '';
        if (this.map) this.map.remove();
        this.map = UI.makeMap('hs-map');
        const latlngs = data.points.map(p => [p.latitude, p.longitude]);
        L.polyline(latlngs, { color: '#2563eb', weight: 3 }).addTo(this.map);
        L.circleMarker(latlngs[0], { radius: 7, fillColor: '#16a34a', color: '#fff', weight: 2, fillOpacity: 1 })
          .addTo(this.map).bindPopup('Awal: ' + UI.fmtTime(data.points[0].recorded_at));
        L.circleMarker(latlngs[latlngs.length - 1], { radius: 7, fillColor: '#dc2626', color: '#fff', weight: 2, fillOpacity: 1 })
          .addTo(this.map).bindPopup('Akhir: ' + UI.fmtTime(data.points[data.points.length - 1].recorded_at));
        this.map.fitBounds(latlngs, { padding: [30, 30] });
      } else {
        mapBox.style.display = 'none';
      }

      document.getElementById('hs-alerts').innerHTML = data.alerts.length ? `
        <h4 class="mb16">Alert pada rentang ini</h4>
        <table class="tbl"><thead><tr><th>Waktu</th><th>Jenis</th><th>Area</th></tr></thead>
        <tbody>${data.alerts.map(a => `<tr>
          <td>${UI.fmtDateTime(a.occurred_at)}</td><td>${UI.statusBadge(a.type)}</td>
          <td>${UI.esc(a.geofence_name || '-')}</td></tr>`).join('')}</tbody></table>` : '';
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  destroy() { if (this.map) { this.map.remove(); this.map = null; } }
};
