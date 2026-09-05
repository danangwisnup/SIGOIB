// Monitoring — CONTROL CENTER: kiri list prajurit + kanan map besar.
// Data digabung di layer web (client-side): /personnel + /devices + /dashboard/locations
// + membership session aktif. Route personel dimuat inline saat dipilih (/history/personnel/{id}).
// Live polling 10 dtk, SATU loop, map dibuat SEKALI, marker di-upsert. Tanpa reload halaman.
Pages.monitoring = {
  timer: null, map: null, markers: {}, routeLayer: null,
  people: [], pmap: {}, basePersonnel: [], serverTime: null,
  selectedId: null, quick: 'ALL', sessionFilter: 0, sessions: [],
  stats: { ib: 0, qc: 0, monitored: 0, scope: 0 },
  routeReqId: 0, _fitted: false, _visHandler: null, _pollCount: 0,

  async render(el) {
    this.el = el;
    this.markers = {}; this.people = []; this.pmap = {}; this.basePersonnel = [];
    this.selectedId = null; this.quick = 'ALL'; this.sessionFilter = 0; this._fitted = false; this._pollCount = 0;
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);

    el.innerHTML = `
      <div class="mon-topbar">
        <div id="mon-banner" class="sess-banner sess-none" data-testid="session-banner">
          <span class="sb-dot"></span>
          <div class="sb-main"><div class="sb-title" id="mon-sb-title">Memuat status sesi…</div>
            <div class="sb-sub muted" id="mon-sb-sub">—</div></div>
          <div class="sb-count"><b id="mon-sb-mon">0</b> dimonitor · <span id="mon-sb-scope">0</span> personel</div>
        </div>
        <div class="mon-actions">
          <span class="muted" id="mon-clock">-</span>
          ${canManage ? `
            <button class="btn primary sm" id="btn-ib" data-testid="create-ib-btn">+ BUAT IB</button>
            <button class="btn primary sm" id="btn-qc" data-testid="quick-check-btn">+ MONITORING CEPAT</button>` : ''}
        </div>
      </div>

      <div id="mon-filterchip"></div>

      <div class="panel mon-panel" data-testid="monitoring-live">
        <div class="mon-cc">
          <div class="mon-side">
            <div class="mon-side-head">
              <input id="mon-search" class="mon-search" placeholder="🔎 Cari nama / NRP / pangkat / kompi / peleton…" autocomplete="off" data-testid="monitoring-search">
              <div class="seg" id="mon-quick">
                <button type="button" class="seg-btn active" data-f="ALL">Semua</button>
                <button type="button" class="seg-btn" data-f="ONLINE">Online</button>
                <button type="button" class="seg-btn" data-f="MONITORED">Dimonitor</button>
                <button type="button" class="seg-btn" data-f="OFFLINE">Offline</button>
              </div>
              <div class="toolbar mon-filters">
                <select id="mon-company"><option value="">Semua Kompi</option></select>
                <select id="mon-platoon"><option value="">Semua Peleton</option></select>
                <span class="view-toggle">
                  <button type="button" id="mon-view-map" class="vt active">Peta</button>
                  <button type="button" id="mon-view-list" class="vt">Daftar</button>
                </span>
              </div>
            </div>
            <div class="mon-list" id="mon-list" data-testid="monitoring-personnel"><div class="empty">Memuat personel…</div></div>
          </div>
          <div class="mon-main">
            <div id="mon-map" class="map-box" data-testid="monitoring-map"></div>
            ${UI.legendHtml}
            <aside id="mon-detail" class="mon-detail" data-testid="monitoring-detail" aria-hidden="true"></aside>
          </div>
        </div>
      </div>

      <details class="panel mon-manage">
        <summary>Kelola Sesi Monitoring</summary>
        <div class="panel-body flush" id="mon-sessions" data-testid="monitoring-table"><div class="empty">Memuat…</div></div>
      </details>`;

    if (canManage) {
      document.getElementById('btn-ib').onclick = () => this.ibModal();
      document.getElementById('btn-qc').onclick = () => this.qcModal();
    }
    // bindings filter (realtime)
    ['mon-search', 'mon-company', 'mon-platoon'].forEach(id => {
      const e = document.getElementById(id);
      e.addEventListener('input', () => this.renderList());
      e.addEventListener('change', () => this.renderList());
    });
    document.querySelectorAll('#mon-quick .seg-btn').forEach(b => b.onclick = () => {
      this.quick = b.dataset.f;
      document.querySelectorAll('#mon-quick .seg-btn').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      this.renderList();
    });
    document.getElementById('mon-view-map').onclick = () => this.setView('map');
    document.getElementById('mon-view-list').onclick = () => this.setView('list');

    // Map dibuat SEKALI.
    this.map = UI.makeMap('mon-map');
    this.map.onMarkerClick = null;

    await this.loadAll(true);
    this.startPolling();
  },

  setView(mode) {
    const panel = this.el.querySelector('.mon-panel');
    if (mode === 'list') {
      panel.classList.add('list-mode');
      document.getElementById('mon-view-list').classList.add('active');
      document.getElementById('mon-view-map').classList.remove('active');
    } else {
      panel.classList.remove('list-mode');
      document.getElementById('mon-view-map').classList.add('active');
      document.getElementById('mon-view-list').classList.remove('active');
      setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 60);
    }
  },

  // ---------- DATA MERGE (client layer) ----------
  async fetchAllPersonnel() {
    const all = [];
    let page = 1, total = 0;
    do {
      const d = await Api.get('/personnel?per_page=100&page=' + page);
      (d.items || []).forEach(p => all.push(p));
      total = d.total || all.length;
      page++;
    } while (all.length < total && page <= 20);
    return all;
  },

  async loadAll(withPersonnel) {
    try {
      if (withPersonnel || !this.basePersonnel.length) {
        this.basePersonnel = await this.fetchAllPersonnel();
      }
      const [devRes, dashRes] = await Promise.all([
        Api.get('/devices').catch(() => ({ items: [] })),
        Api.get('/dashboard/locations').catch(() => ({ markers: [], active_sessions: [] })),
      ]);
      const devices = devRes.items || [];
      const dashMarkers = dashRes.markers || [];
      const activeSessions = dashRes.active_sessions || [];
      this.serverTime = dashRes.server_time || null;

      // membership pid -> session (untuk badge DIMONITOR) dari tiap sesi aktif
      const sessByPid = {};
      const memberBySession = {};
      await Promise.all(activeSessions.map(async s => {
        try {
          const r = await Api.get('/monitoring/' + s.id + '/locations');
          memberBySession[s.id] = new Set();
          (r.markers || []).forEach(mk => {
            memberBySession[s.id].add(mk.personnel_id);
            if (!sessByPid[mk.personnel_id]) sessByPid[mk.personnel_id] = { id: s.id, name: s.name, type: s.type };
          });
        } catch (e) { /* skip */ }
      }));
      this._memberBySession = memberBySession;

      const devByPid = {};
      devices.forEach(d => { if (d.status === 'ACTIVE') devByPid[d.personnel_id] = d; });
      const markByPid = {};
      dashMarkers.forEach(mk => { markByPid[mk.personnel_id] = mk; });

      this.people = this.basePersonnel.map(p => {
        const pid = p.id;
        const mk = markByPid[pid] || null;
        const dev = devByPid[pid] || null;
        const monitored = !!sessByPid[pid];
        const lastSeen = (mk && mk.last_seen_at) || (dev && dev.last_seen_at) || null;
        let conn;
        if (mk && mk.status) {
          conn = (mk.status === 'TRACKING' || mk.status === 'ALERT') ? 'ONLINE' : mk.status;
        } else if (dev) {
          conn = this.connFromLastSeen(dev.last_seen_at);
        } else {
          conn = 'NO_DEVICE';
        }
        const hasPos = !!(mk && mk.latitude !== null && mk.latitude !== undefined);
        return {
          personnel_id: pid, nrp: p.nrp, name: p.name, rank: p.rank || null,
          company_name: p.company_name || null, platoon_name: p.platoon_name || null,
          conn, monitored,
          session_id: monitored ? sessByPid[pid].id : null,
          session_name: monitored ? sessByPid[pid].name : null,
          session_type: monitored ? sessByPid[pid].type : null,
          open_alerts: mk ? (mk.open_alerts || 0) : 0,
          battery: mk && mk.battery != null ? mk.battery : (dev && dev.last_battery != null ? dev.last_battery : null),
          accuracy: mk ? mk.accuracy : null,
          last_seen_at: lastSeen, last_update: mk ? mk.last_update : null,
          latitude: hasPos ? mk.latitude : null, longitude: hasPos ? mk.longitude : null,
          has_position: hasPos,
        };
      });
      this.pmap = {};
      this.people.forEach(m => { this.pmap[m.personnel_id] = m; });

      const ib = activeSessions.filter(s => s.type === 'IB');
      const qc = activeSessions.filter(s => s.type === 'QUICK_CHECK');
      this.stats = { ib: ib.length, qc: qc.length, monitored: Object.keys(sessByPid).length, scope: this.basePersonnel.length };
      this.activeSessions = activeSessions;

      document.getElementById('mon-clock').textContent = 'Update ' + (this.serverTime || '-');
      this.renderBanner();
      this.fillFilterOptions();
      this.renderList();
      this.upsertMarkers();
      this.loadSessions();

      // pertahankan pilihan; perbarui detail + route (jika dimonitor)
      if (this.selectedId != null) {
        const m = this.pmap[this.selectedId];
        if (m) { this.updateDetailLive(m); if (m.monitored) this.loadRoute(m, false); }
        else { this.closeDetail(); }
      }
    } catch (e) {
      document.getElementById('mon-clock').textContent = 'server bermasalah';
    }
  },

  connFromLastSeen(lastSeen) {
    if (!lastSeen) return 'OFFLINE';
    const now = this.serverTime ? new Date(this.serverTime.replace(' ', 'T')) : new Date();
    const t = new Date(String(lastSeen).replace(' ', 'T'));
    if (isNaN(t)) return 'OFFLINE';
    const diff = (now - t) / 1000;
    if (diff < 120) return 'ONLINE';
    if (diff <= 300) return 'TERLAMBAT';
    return 'OFFLINE';
  },

  // ---------- BANNER ----------
  renderBanner() {
    const b = document.getElementById('mon-banner');
    const title = document.getElementById('mon-sb-title');
    const sub = document.getElementById('mon-sb-sub');
    document.getElementById('mon-sb-mon').textContent = this.stats.monitored;
    document.getElementById('mon-sb-scope').textContent = this.stats.scope;
    b.className = 'sess-banner';
    const s = this.activeSessions || [];
    if (this.stats.ib > 0) {
      const first = s.find(x => x.type === 'IB');
      b.classList.add('sess-ib');
      title.textContent = '🟢 IB AKTIF' + (this.stats.ib > 1 ? ' (' + this.stats.ib + ')' : '');
      sub.textContent = this.sessionSub(first);
    } else if (this.stats.qc > 0) {
      const first = s.find(x => x.type === 'QUICK_CHECK');
      b.classList.add('sess-qc');
      title.textContent = '🟠 QUICK CHECK AKTIF' + (this.stats.qc > 1 ? ' (' + this.stats.qc + ')' : '');
      sub.textContent = this.sessionSub(first);
    } else {
      b.classList.add('sess-none');
      title.textContent = '⚪ TIDAK ADA SESI MONITORING AKTIF';
      sub.textContent = 'Tracking dikendalikan server saat IB / Quick Check berjalan.';
    }
  },
  sessionSub(s) {
    if (!s) return '';
    return UI.esc(s.name) + ' · ' + UI.fmtDateTime(s.start_at) + ' — ' + UI.fmtDateTime(s.end_at) + ' · ' + (s.personnel_count || 0) + ' personel';
  },

  // ---------- FILTER + LIST ----------
  fillFilterOptions() {
    const comp = new Set(), plat = new Set();
    this.people.forEach(m => { if (m.company_name) comp.add(m.company_name); if (m.platoon_name) plat.add(m.platoon_name); });
    this.syncSelect('mon-company', [...comp].sort());
    this.syncSelect('mon-platoon', [...plat].sort());
  },
  syncSelect(id, values) {
    const el = document.getElementById(id);
    const cur = el.value;
    const base = el.querySelector('option').outerHTML;
    el.innerHTML = base + values.map(v => `<option value="${UI.esc(v)}">${UI.esc(v)}</option>`).join('');
    el.value = cur;
  },
  applyFilter() {
    const q = (document.getElementById('mon-search').value || '').toLowerCase().trim();
    const c = document.getElementById('mon-company').value;
    const p = document.getElementById('mon-platoon').value;
    const member = this.sessionFilter && this._memberBySession ? this._memberBySession[this.sessionFilter] : null;
    return this.people.filter(m => {
      if (member && !member.has(m.personnel_id)) return false;
      if (q) {
        const hay = (m.nrp + ' ' + m.name + ' ' + (m.rank || '') + ' ' + (m.company_name || '') + ' ' + (m.platoon_name || '')).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      if (c && (m.company_name || '') !== c) return false;
      if (p && (m.platoon_name || '') !== p) return false;
      if (this.quick === 'ONLINE' && m.conn !== 'ONLINE') return false;
      if (this.quick === 'OFFLINE' && m.conn !== 'OFFLINE') return false;
      if (this.quick === 'MONITORED' && !m.monitored) return false;
      return true;
    });
  },
  connClass(c) { return c === 'ONLINE' ? 'online' : c === 'TERLAMBAT' ? 'terlambat' : c === 'NO_DEVICE' ? 'nodev' : 'offline'; },
  connLabel(c) { return c === 'ONLINE' ? 'ONLINE' : c === 'TERLAMBAT' ? 'TERLAMBAT' : c === 'NO_DEVICE' ? 'TANPA PERANGKAT' : 'OFFLINE'; },

  renderList() {
    const box = document.getElementById('mon-list');
    if (!box) return;
    const top = box.scrollTop;
    const list = this.applyFilter();
    if (!list.length) {
      box.innerHTML = '<div class="empty">Tidak ada personel cocok.</div>';
      return;
    }
    box.innerHTML = list.map(m => {
      const cc = this.connClass(m.conn);
      const monChip = m.monitored
        ? '<span class="chip chip-mon">🟢 DIMONITOR</span>'
        : '<span class="chip chip-unmon">⚪ TIDAK DIMONITOR</span>';
      const nopos = m.has_position ? '' : '<span class="pr-nopos">tanpa koordinat</span>';
      return `<div class="person-row${m.personnel_id === this.selectedId ? ' active' : ''}" data-pid="${m.personnel_id}" data-testid="person-${m.personnel_id}">
        <span class="sdot sdot-${cc}"></span>
        <div class="pr-body">
          <div class="pr-name">${UI.esc(m.name)}${m.rank ? ` <span class="pr-rank">${UI.esc(m.rank)}</span>` : ''}</div>
          <div class="pr-meta">NRP ${UI.esc(m.nrp)} · ${UI.esc(m.company_name || '-')} / ${UI.esc(m.platoon_name || '-')}</div>
          <div class="pr-line"><span class="chip chip-${cc}">${this.connLabel(m.conn)}</span> ${monChip} ${nopos}</div>
          <div class="pr-line pr-meta">${m.last_seen_at ? 'Update ' + UI.fmtTime(m.last_seen_at) : 'Belum ada update'}${m.battery != null ? ' · 🔋 ' + m.battery + '%' : ''}</div>
        </div></div>`;
    }).join('');
    box.querySelectorAll('.person-row').forEach(row =>
      row.onclick = () => this.selectPerson(parseInt(row.dataset.pid), true));
    box.scrollTop = top;
  },

  // ---------- MAP MARKERS (upsert + remove stale) ----------
  upsertMarkers() {
    const present = new Set();
    this.people.forEach(m => {
      if (!m.has_position) return;
      present.add(m.personnel_id);
      const color = UI.markerColor(m.conn);
      if (this.markers[m.personnel_id]) {
        const mk = this.markers[m.personnel_id];
        mk.setLatLng([m.latitude, m.longitude]);
        mk.setStyle({ fillColor: color });
        mk.setPopupContent(this.markerPopupHtml(m));
      } else {
        const mk = L.circleMarker([m.latitude, m.longitude], {
          radius: m.personnel_id === this.selectedId ? 11 : 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1
        }).addTo(this.map);
        mk.bindPopup(this.markerPopupHtml(m));
        mk.on('click', () => this.selectPerson(m.personnel_id, false));
        this.markers[m.personnel_id] = mk;
      }
    });
    // hapus marker yang tak lagi punya koordinat / keluar scope
    Object.keys(this.markers).forEach(pid => {
      if (!present.has(parseInt(pid)) && !present.has(pid)) {
        this.map.removeLayer(this.markers[pid]); delete this.markers[pid];
      }
    });
    if (!this._fitted && present.size) {
      const pts = this.people.filter(m => m.has_position).map(m => [m.latitude, m.longitude]);
      if (pts.length) { this.map.fitBounds(pts, { padding: [40, 40], maxZoom: 15 }); this._fitted = true; }
    }
  },
  markerPopupHtml(m) {
    return `<b>${UI.esc(m.name)}</b><br>NRP: ${UI.esc(m.nrp)}<br>
      ${UI.esc(m.company_name || '-')} / ${UI.esc(m.platoon_name || '-')}<br>
      Status: ${this.connLabel(m.conn)}<br>
      Monitoring: ${m.monitored ? 'DIMONITOR' + (m.session_name ? ' (' + UI.esc(m.session_name) + ')' : '') : 'TIDAK DIMONITOR'}<br>
      Battery: ${UI.esc(UI.batteryText(m.battery))}<br>
      Akurasi: ${m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-'}<br>
      <a target="_blank" rel="noopener" href="${this.gmaps(m.latitude, m.longitude)}"><b>BUKA DI GOOGLE MAPS</b></a>`;
  },
  gmaps(lat, lng) { return 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng; },

  // ---------- SELECT + DETAIL + ROUTE ----------
  selectPerson(id, focus) {
    this.selectedId = id;
    const m = this.pmap[id];
    document.querySelectorAll('#mon-list .person-row').forEach(r =>
      r.classList.toggle('active', parseInt(r.dataset.pid) === id));
    Object.keys(this.markers).forEach(pid =>
      this.markers[pid].setStyle({ radius: parseInt(pid) === id ? 11 : 8 }));
    if (!m) return;
    if (m.has_position) {
      if (focus) this.map.flyTo([m.latitude, m.longitude], 16);
      else this.map.setView([m.latitude, m.longitude], 16, { animate: true });
      if (this.markers[id]) this.markers[id].openPopup();
    }
    this.openDetail(m);
    this.loadRoute(m, true);
  },

  statusBlock(m) {
    const cc = this.connClass(m.conn);
    const mon = m.monitored
      ? `<span class="chip chip-mon">🟢 DIMONITOR</span>${m.session_name ? ` <span class="muted">${UI.esc(m.session_name)}</span>` : ''}`
      : '<span class="chip chip-unmon">⚪ TIDAK DIMONITOR</span>';
    return `<span class="chip chip-${cc}">${this.connLabel(m.conn)}</span> ${mon}`;
  },

  openDetail(m) {
    const d = document.getElementById('mon-detail');
    const gmap = m.has_position
      ? `<a class="btn primary sm" target="_blank" rel="noopener" href="${this.gmaps(m.latitude, m.longitude)}" data-testid="detail-gmaps">📍 BUKA POSISI DI GOOGLE MAPS</a>`
      : '<div class="muted">Posisi belum tersedia (belum ada koordinat dari perangkat).</div>';
    const coord = m.has_position ? `${Number(m.latitude).toFixed(6)}, ${Number(m.longitude).toFixed(6)}` : '-';
    d.innerHTML = `
      <div class="md-head">
        <div><div class="md-name">${UI.esc(m.name)}</div>
          <div class="md-sub">NRP ${UI.esc(m.nrp)}${m.rank ? ' · ' + UI.esc(m.rank) : ''}</div>
          <div class="md-sub">${UI.esc(m.company_name || '-')} / ${UI.esc(m.platoon_name || '-')}</div></div>
        <button type="button" class="md-close" id="mon-md-close" data-testid="detail-close">&times;</button>
      </div>
      <div class="md-status" id="mon-md-status">${this.statusBlock(m)}</div>
      <div class="md-grid">
        <div><div class="md-k">Update</div><div class="md-v" id="mon-md-update">${UI.esc(m.last_seen_at || '-')}</div></div>
        <div><div class="md-k">Baterai</div><div class="md-v" id="mon-md-batt">${m.battery != null ? m.battery + '%' : '-'}</div></div>
        <div><div class="md-k">Akurasi</div><div class="md-v" id="mon-md-acc">${m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-'}</div></div>
        <div><div class="md-k">Koordinat</div><div class="md-v md-coord" id="mon-md-coord">${coord}</div></div>
      </div>
      <div class="md-pos" id="mon-md-pos">${gmap}</div>
      <div class="md-sesswrap" id="mon-md-sess"></div>
      <div class="md-route-h">PERJALANAN</div>
      <div class="route-list" id="mon-md-route" data-testid="route-list"><div class="muted" style="padding:12px">Memuat perjalanan…</div></div>`;
    d.classList.add('open');
    d.setAttribute('aria-hidden', 'false');
    document.getElementById('mon-md-close').onclick = () => this.closeDetail();
  },

  updateDetailLive(m) {
    const st = document.getElementById('mon-md-status'); if (st) st.innerHTML = this.statusBlock(m);
    const up = document.getElementById('mon-md-update'); if (up) up.textContent = m.last_seen_at || '-';
    const bt = document.getElementById('mon-md-batt'); if (bt) bt.textContent = m.battery != null ? m.battery + '%' : '-';
    const ac = document.getElementById('mon-md-acc'); if (ac) ac.textContent = m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-';
    const co = document.getElementById('mon-md-coord'); if (co) co.textContent = m.has_position ? `${Number(m.latitude).toFixed(6)}, ${Number(m.longitude).toFixed(6)}` : '-';
    const pos = document.getElementById('mon-md-pos');
    if (pos) pos.innerHTML = m.has_position
      ? `<a class="btn primary sm" target="_blank" rel="noopener" href="${this.gmaps(m.latitude, m.longitude)}" data-testid="detail-gmaps">📍 BUKA POSISI DI GOOGLE MAPS</a>`
      : '<div class="muted">Posisi belum tersedia (belum ada koordinat dari perangkat).</div>';
  },

  closeDetail() {
    this.selectedId = null;
    this.clearRoute();
    const d = document.getElementById('mon-detail');
    if (d) { d.classList.remove('open'); d.setAttribute('aria-hidden', 'true'); }
    document.querySelectorAll('#mon-list .person-row').forEach(r => r.classList.remove('active'));
    Object.keys(this.markers).forEach(pid => this.markers[pid].setStyle({ radius: 8 }));
  },

  async loadRoute(m, fit) {
    const reqId = ++this.routeReqId;
    let path = '/history/personnel/' + m.personnel_id;
    if (m.session_id) path += '?session_id=' + m.session_id;
    try {
      const data = await Api.get(path);
      if (reqId !== this.routeReqId || this.selectedId !== m.personnel_id) return;
      this.renderRoute(m, data, fit);
    } catch (e) {
      if (reqId !== this.routeReqId) return;
      const box = document.getElementById('mon-md-route');
      if (box) box.innerHTML = '<div class="muted" style="padding:12px">Gagal memuat perjalanan.</div>';
    }
  },

  renderRoute(m, data, fit) {
    const box = document.getElementById('mon-md-route');
    if (!box) return;
    const pts = (data.points || []).map(p => ({
      lat: parseFloat(p.latitude), lng: parseFloat(p.longitude),
      recorded_at: p.recorded_at, accuracy: p.accuracy != null ? p.accuracy : null, battery: p.battery != null ? p.battery : null,
    }));
    // dropdown sesi (opsional)
    const sw = document.getElementById('mon-md-sess');
    if (sw && data.sessions && data.sessions.length) {
      sw.innerHTML = `<select id="mon-md-session" class="md-session" data-testid="detail-session"><option value="">Semua sesi</option>${
        data.sessions.map(s => `<option value="${s.id}" ${m.session_id === s.id ? 'selected' : ''}>${UI.esc(s.name)} (${UI.esc(s.type)})</option>`).join('')}</select>`;
      sw.querySelector('#mon-md-session').onchange = (ev) => {
        const sid = ev.target.value ? parseInt(ev.target.value) : null;
        this.loadRoute(Object.assign({}, m, { session_id: sid }), true);
      };
    } else if (sw) { sw.innerHTML = ''; }

    if (!pts.length) {
      box.innerHTML = '<div class="empty">Belum ada titik GPS pada rentang ini.</div>';
      this.clearRoute();
      return;
    }
    this.showRoute(pts, !!m.monitored, fit !== false);
    const last = pts.length - 1;
    box.innerHTML = pts.map((p, i) => {
      const isStart = i === 0, isEnd = i === last;
      const icon = isStart ? '🔵' : (isEnd ? (m.monitored ? '🟢' : '🔴') : '📍');
      const label = isStart ? 'Titik Awal' : (isEnd ? (m.monitored ? 'Posisi Sekarang' : 'Titik Akhir') : 'Perjalanan');
      return `<div class="route-row" data-i="${i}" data-testid="route-point-${i}">
        <div><div class="rr-time">${icon} ${UI.esc(p.recorded_at || '-')}</div>
        <div class="rr-pos">${label} · ${p.lat.toFixed(5)}, ${p.lng.toFixed(5)}</div></div>
        <div class="rr-act"><a target="_blank" rel="noopener" href="${this.gmaps(p.lat, p.lng)}">Maps</a></div></div>`;
    }).join('');
    box.querySelectorAll('.route-row').forEach(row => row.onclick = (ev) => {
      if (ev.target && ev.target.tagName === 'A') return;
      const p = pts[parseInt(row.dataset.i)];
      this.map.setView([p.lat, p.lng], 17, { animate: true });
      L.popup().setLatLng([p.lat, p.lng]).setContent(
        `${UI.esc(p.recorded_at || '')}<br>${p.lat.toFixed(6)}, ${p.lng.toFixed(6)}` +
        (p.accuracy != null ? `<br>Akurasi: ${Math.round(p.accuracy)} m` : '') +
        (p.battery != null ? `<br>Baterai: ${p.battery}%` : '') +
        `<br><a target="_blank" rel="noopener" href="${this.gmaps(p.lat, p.lng)}"><b>BUKA DI GOOGLE MAPS</b></a>`
      ).openOn(this.map);
    });
  },

  showRoute(pts, live, fit) {
    this.clearRoute();
    if (!pts.length) return;
    const latlngs = pts.map(p => [p.lat, p.lng]);
    const grp = L.layerGroup();
    if (latlngs.length > 1) L.polyline(latlngs, { color: '#2563eb', weight: 4, opacity: .85 }).addTo(grp);
    for (let i = 1; i < pts.length - 1; i++) {
      L.circleMarker(latlngs[i], { radius: 4, color: '#2563eb', weight: 1, fillColor: '#60a5fa', fillOpacity: 1 }).addTo(grp);
    }
    L.circleMarker(latlngs[0], { radius: 8, color: '#fff', weight: 2, fillColor: '#2563eb', fillOpacity: 1 })
      .addTo(grp).bindPopup('<b>TITIK AWAL</b><br>' + UI.esc(pts[0].recorded_at || ''));
    if (latlngs.length > 1) {
      const lp = pts[pts.length - 1];
      L.circleMarker(latlngs[latlngs.length - 1], { radius: 9, color: '#fff', weight: 2, fillColor: live ? '#16a34a' : '#dc2626', fillOpacity: 1 })
        .addTo(grp).bindPopup(`<b>${live ? 'POSISI SEKARANG' : 'TITIK AKHIR'}</b><br>` + UI.esc(lp.recorded_at || ''));
    }
    grp.addTo(this.map);
    this.routeLayer = grp;
    if (fit) this.map.fitBounds(latlngs, { padding: [45, 45], maxZoom: 17 });
  },
  clearRoute() { if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; } },

  // ---------- SESSIONS MANAGEMENT (dipertahankan) ----------
  async loadSessions() {
    const box = document.getElementById('mon-sessions');
    if (!box) return;
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
    try {
      const { items } = await Api.get('/monitoring');
      this.sessions = items || [];
      box.innerHTML = items.length ? `<table class="tbl"><thead><tr>
          <th>Nama</th><th>Type</th><th>Personel</th><th>Mulai</th><th>Selesai</th><th>Status</th><th></th>
        </tr></thead><tbody>${items.map(s => `<tr>
          <td>${UI.esc(s.name)}</td><td>${UI.statusBadge(s.type)}</td>
          <td>${s.personnel_count}</td><td>${UI.fmtDateTime(s.start_at)}</td>
          <td>${UI.fmtDateTime(s.end_at)}</td><td>${UI.statusBadge(s.status)}</td>
          <td>
            ${s.status === 'ACTIVE' ? `<button class="btn sm" data-view="${s.id}">Lihat di peta</button>` : ''}
            <button class="btn sm" data-export="${s.id}" data-testid="detail-${s.id}">Export CSV</button>
            ${canManage && ['SCHEDULED', 'ACTIVE'].includes(s.status)
              ? `<button class="btn sm danger" data-cancel="${s.id}" data-testid="cancel-${s.id}">Batalkan</button>` : ''}
          </td></tr>`).join('')}</tbody></table>` : '<div class="empty">Belum ada monitoring.</div>';

      box.querySelectorAll('[data-view]').forEach(b => b.onclick = () => this.setSessionFilter(parseInt(b.dataset.view)));
      box.querySelectorAll('[data-export]').forEach(b => b.onclick = async () => {
        try { await UI.downloadFile(`/reports/monitoring/${b.dataset.export}?format=csv`, `laporan_${b.dataset.export}.csv`); }
        catch (e) { UI.toast(e.message, 'error'); }
      });
      box.querySelectorAll('[data-cancel]').forEach(b => b.onclick = () =>
        UI.confirm('Batalkan monitoring ini?', async () => {
          try { await Api.post(`/monitoring/${b.dataset.cancel}/cancel`); UI.toast('Dibatalkan.'); this.loadAll(true); }
          catch (e) { UI.toast(e.message, 'error'); }
        }));
    } catch (e) { box.innerHTML = `<div class="empty">${UI.esc(e.message)}</div>`; }
  },

  setSessionFilter(id) {
    this.sessionFilter = this.sessionFilter === id ? 0 : id;
    const chip = document.getElementById('mon-filterchip');
    if (this.sessionFilter) {
      const s = (this.sessions || []).find(x => x.id === id);
      chip.innerHTML = `<div class="filter-chip">Filter sesi: <b>${UI.esc(s ? s.name : id)}</b> <button class="btn sm" id="mon-clear-filter">Tampilkan semua</button></div>`;
      document.getElementById('mon-clear-filter').onclick = () => this.setSessionFilter(id);
    } else { chip.innerHTML = ''; }
    this.renderList();
    // fokus map ke anggota sesi bila ada koordinat
    if (this.sessionFilter && this._memberBySession && this._memberBySession[this.sessionFilter]) {
      const pts = this.people.filter(m => m.has_position && this._memberBySession[this.sessionFilter].has(m.personnel_id))
        .map(m => [m.latitude, m.longitude]);
      if (pts.length) this.map.fitBounds(pts, { padding: [40, 40], maxZoom: 15 });
    }
  },

  // ---- Form target peserta (dipakai IB & Quick Check) — TIDAK diubah ----
  targetPickerHtml(orgs) {
    return `
      <div class="form-row"><label>Target Peserta</label>
        <select id="tp-type" data-testid="target-type">
          <option value="SEMUA">SEMUA PERSONEL</option>
          <option value="KOMPI">KOMPI</option>
          <option value="PELETON">PELETON</option>
          <option value="INDIVIDUAL">INDIVIDUAL (cari NRP/Nama)</option>
        </select></div>
      <div class="form-row" id="tp-org" style="display:none"><label>Pilih</label>
        <select id="tp-org-select" multiple size="5"></select></div>
      <div class="form-row" id="tp-individual" style="display:none">
        <label>Cari Personel</label>
        <input id="tp-search" data-testid="target-search" placeholder="Ketik NRP / Nama...">
        <div class="search-results" id="tp-results" style="display:none"></div>
        <div class="chip-list" id="tp-chips"></div>
      </div>`;
  },

  bindTargetPicker(m, orgs) {
    const selected = new Map();
    const typeSel = m.querySelector('#tp-type');
    const orgBox = m.querySelector('#tp-org');
    const orgSel = m.querySelector('#tp-org-select');
    const indBox = m.querySelector('#tp-individual');
    const renderChips = () => {
      m.querySelector('#tp-chips').innerHTML = [...selected.values()].map(p =>
        `<span class="chip">${UI.esc(p.nrp)} - ${UI.esc(p.name)} <b data-rm="${p.id}">&times;</b></span>`).join('');
      m.querySelectorAll('[data-rm]').forEach(x => x.onclick = () => { selected.delete(parseInt(x.dataset.rm)); renderChips(); });
    };
    typeSel.onchange = () => {
      orgBox.style.display = ['KOMPI', 'PELETON'].includes(typeSel.value) ? '' : 'none';
      indBox.style.display = typeSel.value === 'INDIVIDUAL' ? '' : 'none';
      orgSel.innerHTML = UI.orgOptions(orgs, typeSel.value);
    };
    let deb;
    m.querySelector('#tp-search').oninput = (e) => {
      clearTimeout(deb);
      const q = e.target.value.trim();
      if (q.length < 2) { m.querySelector('#tp-results').style.display = 'none'; return; }
      deb = setTimeout(async () => {
        const data = await Api.get('/personnel?per_page=10&q=' + encodeURIComponent(q));
        const res = m.querySelector('#tp-results');
        res.style.display = '';
        res.innerHTML = data.items.map(p =>
          `<div data-add="${p.id}" data-nrp="${UI.esc(p.nrp)}" data-name="${UI.esc(p.name)}">${UI.esc(p.nrp)} - ${UI.esc(p.name)}</div>`).join('')
          || '<div>Tidak ditemukan.</div>';
        res.querySelectorAll('[data-add]').forEach(d => d.onclick = () => {
          selected.set(parseInt(d.dataset.add), { id: parseInt(d.dataset.add), nrp: d.dataset.nrp, name: d.dataset.name });
          renderChips(); res.style.display = 'none'; m.querySelector('#tp-search').value = '';
        });
      }, 350);
    };
    return () => {
      const type = typeSel.value;
      let ids = [];
      if (['KOMPI', 'PELETON'].includes(type)) ids = [...orgSel.selectedOptions].map(o => parseInt(o.value));
      if (type === 'INDIVIDUAL') ids = [...selected.keys()];
      return { target_type: type, target_ids: ids };
    };
  },

  async ibModal() {
    const orgs = await UI.loadOrganizations();
    const m = UI.modal('Buat IB (Izin Bermalam)', `
      <div id="ib-error"></div>
      <div class="form-row"><label>Nama IB *</label>
        <input id="ib-name" data-testid="ib-name" placeholder="cth: IB Akhir Pekan 12-14 Jun"></div>
      <div class="form-grid">
        <div class="form-row"><label>Mulai *</label><input type="datetime-local" id="ib-start" data-testid="ib-start"></div>
        <div class="form-row"><label>Selesai *</label><input type="datetime-local" id="ib-end" data-testid="ib-end"></div>
      </div>
      ${this.targetPickerHtml(orgs)}
      <div class="form-actions">
        <button class="btn" data-act="cancel">Batal</button>
        <button class="btn primary" data-act="save" data-testid="ib-submit">Buat IB</button>
      </div>`, { wide: true });
    const getTarget = this.bindTargetPicker(m, orgs);
    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=save]').onclick = async () => {
      const dt = v => v ? v.replace('T', ' ') + (v.length === 16 ? ':00' : '') : '';
      const body = {
        name: m.querySelector('#ib-name').value.trim(),
        start_at: dt(m.querySelector('#ib-start').value),
        end_at: dt(m.querySelector('#ib-end').value),
        ...getTarget(),
      };
      try {
        const r = await Api.post('/monitoring/ib', body);
        m.remove(); UI.toast(`IB dibuat (${r.personnel_count} personel).`); this.loadAll(true);
      } catch (e) {
        m.querySelector('#ib-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  },

  async qcModal() {
    const orgs = await UI.loadOrganizations();
    const m = UI.modal('Monitoring Cepat (Quick Check)', `
      <div id="qc-error"></div>
      <div class="form-row"><label>Nama (opsional)</label>
        <input id="qc-name" data-testid="qc-name" placeholder="cth: Quick Check Kompi A"></div>
      ${this.targetPickerHtml(orgs)}
      <div class="form-row"><label>Durasi</label>
        <select id="qc-duration" data-testid="qc-duration">
          <option value="30">30 menit</option>
          <option value="60" selected>1 jam</option>
          <option value="120">2 jam</option>
          <option value="custom">Custom (menit)</option>
        </select></div>
      <div class="form-row" id="qc-custom-row" style="display:none"><label>Durasi Custom (menit)</label>
        <input type="number" id="qc-custom" min="1" max="1440" value="45"></div>
      <p class="muted">Quick Check dimulai SEKARANG. Personel yang sedang IB tetap tracking.</p>
      <div class="form-actions">
        <button class="btn" data-act="cancel">Batal</button>
        <button class="btn primary" data-act="save" data-testid="qc-submit">Aktifkan Sekarang</button>
      </div>`, { wide: true });
    const getTarget = this.bindTargetPicker(m, orgs);
    m.querySelector('#qc-duration').onchange = (e) =>
      m.querySelector('#qc-custom-row').style.display = e.target.value === 'custom' ? '' : 'none';
    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=save]').onclick = async () => {
      const durSel = m.querySelector('#qc-duration').value;
      const duration = durSel === 'custom' ? parseInt(m.querySelector('#qc-custom').value) : parseInt(durSel);
      try {
        const r = await Api.post('/monitoring/quick-check', {
          name: m.querySelector('#qc-name').value.trim(),
          duration_minutes: duration, ...getTarget(),
        });
        m.remove(); UI.toast(`Quick Check aktif sampai ${UI.fmtDateTime(r.end_at)}.`); this.loadAll(true);
      } catch (e) {
        m.querySelector('#qc-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  },

  // ---------- POLLING (satu loop + Page Visibility) ----------
  startPolling() {
    this.stopPolling();
    this.timer = setInterval(() => this.tick(), 10000);
    this._visHandler = () => {
      if (document.hidden) { clearInterval(this.timer); this.timer = null; }
      else if (!this.timer) { this.loadAll(false); this.timer = setInterval(() => this.tick(), 10000); }
    };
    document.addEventListener('visibilitychange', this._visHandler);
  },
  tick() {
    this._pollCount++;
    // refetch daftar personel tiap 6 poll (~60 dtk) agar personel baru muncul dinamis
    this.loadAll(this._pollCount % 6 === 0);
  },
  stopPolling() {
    if (this.timer) { clearInterval(this.timer); this.timer = null; }
    if (this._visHandler) { document.removeEventListener('visibilitychange', this._visHandler); this._visHandler = null; }
  },

  destroy() {
    this.stopPolling();
    if (this.map) { this.map.remove(); this.map = null; }
    this.markers = {}; this.routeLayer = null; this.selectedId = null; this._fitted = false;
  }
};
