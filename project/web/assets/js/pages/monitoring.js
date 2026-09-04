// Monitoring: list, buat IB, quick check, detail + map (polling 12 dtk).
Pages.monitoring = {
  timer: null, map: null, markers: {}, detailId: null,

  async render(el) {
    this.el = el;
    this.detailId = null;
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Daftar Monitoring</h3>
          <div class="toolbar">${canManage ? `
            <button class="btn primary" id="btn-ib" data-testid="create-ib-btn">+ BUAT IB</button>
            <button class="btn primary" id="btn-qc" data-testid="quick-check-btn">+ MONITORING CEPAT</button>` : ''}
          </div>
        </div>
        <div class="panel-body flush" id="mon-table" data-testid="monitoring-table"><div class="empty">Memuat...</div></div>
      </div>
      <div id="mon-detail"></div>`;
    if (canManage) {
      document.getElementById('btn-ib').onclick = () => this.ibModal();
      document.getElementById('btn-qc').onclick = () => this.qcModal();
    }
    await this.loadList();
  },

  async loadList() {
    try {
      const { items } = await Api.get('/monitoring');
      const box = document.getElementById('mon-table');
      const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
      box.innerHTML = items.length ? `<table class="tbl"><thead><tr>
          <th>Nama</th><th>Type</th><th>Jumlah Personel</th><th>Mulai</th><th>Selesai</th><th>Status</th><th></th>
        </tr></thead><tbody>${items.map(s => `<tr>
          <td>${UI.esc(s.name)}</td><td>${UI.statusBadge(s.type)}</td>
          <td>${s.personnel_count}</td><td>${UI.fmtDateTime(s.start_at)}</td>
          <td>${UI.fmtDateTime(s.end_at)}</td><td>${UI.statusBadge(s.status)}</td>
          <td>
            <button class="btn sm" data-detail="${s.id}" data-testid="detail-${s.id}">Detail</button>
            ${canManage && ['SCHEDULED', 'ACTIVE'].includes(s.status)
              ? `<button class="btn sm danger" data-cancel="${s.id}" data-testid="cancel-${s.id}">Batalkan</button>` : ''}
          </td></tr>`).join('')}</tbody></table>` : '<div class="empty">Belum ada monitoring.</div>';

      box.querySelectorAll('[data-detail]').forEach(b => b.onclick = () => this.showDetail(b.dataset.detail));
      box.querySelectorAll('[data-cancel]').forEach(b => b.onclick = () =>
        UI.confirm('Batalkan monitoring ini?', async () => {
          try { await Api.post(`/monitoring/${b.dataset.cancel}/cancel`); UI.toast('Dibatalkan.'); this.loadList(); }
          catch (e) { UI.toast(e.message, 'error'); }
        }));
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  // ---- Form target peserta (dipakai IB & Quick Check) ----
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
        m.remove(); UI.toast(`IB dibuat (${r.personnel_count} personel).`); this.loadList();
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
        m.remove(); UI.toast(`Quick Check aktif sampai ${UI.fmtDateTime(r.end_at)}.`); this.loadList();
      } catch (e) {
        m.querySelector('#qc-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  },

  async showDetail(id) {
    this.detailId = id;
    const box = document.getElementById('mon-detail');
    try {
      const { session } = await Api.get('/monitoring/' + id);
      box.innerHTML = `
        <div class="panel">
          <div class="panel-head"><h3 data-testid="monitoring-detail-title">${UI.esc(session.name)} ${UI.statusBadge(session.type)} ${UI.statusBadge(session.status)}</h3>
            <a class="btn sm" href="/api/reports/monitoring/${id}?format=csv" target="_blank" data-testid="export-csv">Export CSV</a></div>
          <div class="panel-body">
            <p class="muted mb16">${UI.fmtDateTime(session.start_at)} s/d ${UI.fmtDateTime(session.end_at)} &bull; Dibuat oleh ${UI.esc(session.created_by_name || '-')}</p>
            <div id="mon-map" data-testid="monitoring-map" class="map-box"></div>
            ${UI.legendHtml}
            <div id="mon-personnel" class="mt16"></div>
          </div>
        </div>`;
      box.scrollIntoView({ behavior: 'smooth' });
      if (this.map) { this.map.remove(); }
      this.map = UI.makeMap('mon-map');
      this.markers = {};
      clearInterval(this.timer);
      await this.refreshDetail();
      this.timer = setInterval(() => this.refreshDetail(), 12000); // polling tanpa reload halaman
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  async refreshDetail() {
    if (!this.detailId) return;
    try {
      const { markers } = await Api.get(`/monitoring/${this.detailId}/locations`);
      const seen = new Set();
      markers.forEach(mk => {
        if (mk.latitude === null) return;
        seen.add(mk.personnel_id);
        if (this.markers[mk.personnel_id]) {
          this.markers[mk.personnel_id].setLatLng([mk.latitude, mk.longitude]);
          this.markers[mk.personnel_id].setStyle({ fillColor: UI.markerColor(mk.status) });
        } else {
          this.markers[mk.personnel_id] = UI.addMarker(this.map, mk);
        }
      });
      const withLoc = markers.filter(x => x.latitude !== null);
      if (withLoc.length && !this._fitted) {
        this.map.fitBounds(withLoc.map(x => [x.latitude, x.longitude]), { padding: [30, 30] });
        this._fitted = true;
      }
      document.getElementById('mon-personnel').innerHTML = `<table class="tbl"><thead><tr>
          <th>NRP</th><th>Nama</th><th>Kompi</th><th>Peleton</th><th>Status</th><th>Battery</th><th>Update Terakhir</th>
        </tr></thead><tbody>${markers.map(x => `<tr>
          <td>${UI.esc(x.nrp)}</td><td>${UI.esc(x.name)}</td>
          <td>${UI.esc(x.company_name || '-')}</td><td>${UI.esc(x.platoon_name || '-')}</td>
          <td>${UI.statusBadge(x.status)}</td><td>${UI.esc(UI.batteryText(x.battery))}</td>
          <td>${UI.fmtTime(x.last_update)}</td></tr>`).join('')}</tbody></table>`;
    } catch (e) { /* map tetap tampil jika request gagal */ }
  },

  destroy() { clearInterval(this.timer); this.detailId = null; this._fitted = false; }
};
