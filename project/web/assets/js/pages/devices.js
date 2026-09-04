// Perangkat: pending approval, active (revoke), riwayat revoked.
Pages.devices = {
  async render(el) {
    this.el = el;
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Permintaan Persetujuan (Pending)</h3>
          <button class="btn sm" id="dev-refresh" data-testid="devices-refresh">Refresh</button></div>
        <div class="panel-body flush" id="pending-table" data-testid="pending-devices"><div class="empty">Memuat...</div></div>
      </div>
      <div class="panel">
        <div class="panel-head"><h3>Perangkat Terdaftar</h3>
          <div class="toolbar"><select id="dev-status" data-testid="device-status-filter">
            <option value="">Semua Status</option><option value="ACTIVE">ACTIVE</option>
            <option value="REVOKED">REVOKED</option></select></div>
        </div>
        <div class="panel-body flush" id="device-table" data-testid="device-table"><div class="empty">Memuat...</div></div>
      </div>`;
    document.getElementById('dev-refresh').onclick = () => this.load();
    document.getElementById('dev-status').onchange = () => this.load();
    this.canManage = canManage;
    await this.load();
  },

  async load() {
    try {
      const pending = (await Api.get('/devices/pending')).items;
      const pBox = document.getElementById('pending-table');
      pBox.innerHTML = pending.length ? `<table class="tbl"><thead><tr>
          <th>Personel</th><th>NRP</th><th>Platform</th><th>Model</th><th>App Ver</th><th>Waktu Request</th>${this.canManage ? '<th>Aksi</th>' : ''}
        </tr></thead><tbody>${pending.map(d => `<tr>
          <td>${UI.esc(d.personnel_name)}</td><td>${UI.esc(d.nrp)}</td>
          <td>${UI.esc(d.platform || '-')}</td><td>${UI.esc(d.model || '-')}</td>
          <td>${UI.esc(d.app_version || '-')}</td><td>${UI.fmtDateTime(d.created_at)}</td>
          ${this.canManage ? `<td>
            <button class="btn sm success" data-approve="${d.id}" data-testid="approve-${d.id}">SETUJUI</button>
            <button class="btn sm danger" data-reject="${d.id}" data-testid="reject-${d.id}">TOLAK</button></td>` : ''}
        </tr>`).join('')}</tbody></table>` : '<div class="empty">Tidak ada permintaan pending.</div>';

      pBox.querySelectorAll('[data-approve]').forEach(b => b.onclick = async () => {
        try { await Api.post(`/devices/${b.dataset.approve}/approve`); UI.toast('Perangkat disetujui.'); this.load(); }
        catch (e) { UI.toast(e.message, 'error'); }
      });
      pBox.querySelectorAll('[data-reject]').forEach(b => b.onclick = () =>
        UI.confirm('Tolak permintaan perangkat ini?', async () => {
          try { await Api.post(`/devices/${b.dataset.reject}/reject`); UI.toast('Ditolak.'); this.load(); }
          catch (e) { UI.toast(e.message, 'error'); }
        }));

      const status = document.getElementById('dev-status').value;
      const devices = (await Api.get('/devices' + (status ? '?status=' + status : ''))).items;
      const dBox = document.getElementById('device-table');
      dBox.innerHTML = devices.length ? `<table class="tbl"><thead><tr>
          <th>Personel</th><th>NRP</th><th>Platform</th><th>Model</th><th>Battery</th>
          <th>Last Seen</th><th>Koneksi</th><th>App Ver</th><th>Status</th>${this.canManage ? '<th>Aksi</th>' : ''}
        </tr></thead><tbody>${devices.map(d => `<tr>
          <td>${UI.esc(d.personnel_name)}</td><td>${UI.esc(d.nrp)}</td>
          <td>${UI.esc(d.platform || '-')}</td><td>${UI.esc(d.model || '-')}</td>
          <td>${UI.esc(UI.batteryText(d.last_battery))}</td>
          <td>${UI.fmtDateTime(d.last_seen_at)}</td>
          <td>${d.status === 'ACTIVE' ? UI.statusBadge(d.online_status) : '-'}</td>
          <td>${UI.esc(d.app_version || '-')}</td><td>${UI.statusBadge(d.status)}</td>
          ${this.canManage ? `<td>${d.status === 'ACTIVE'
            ? `<button class="btn sm danger" data-revoke="${d.id}" data-testid="revoke-${d.id}">REVOKE</button>` : ''}</td>` : ''}
        </tr>`).join('')}</tbody></table>` : '<div class="empty">Belum ada perangkat.</div>';

      dBox.querySelectorAll('[data-revoke]').forEach(b => b.onclick = () =>
        UI.confirm('REVOKE perangkat ini? Perangkat langsung tidak bisa mengirim GPS. Untuk penggantian HP, lakukan revoke lalu registrasi ulang dari HP baru.', async () => {
          try { await Api.post(`/devices/${b.dataset.revoke}/revoke`); UI.toast('Perangkat direvoke.'); this.load(); }
          catch (e) { UI.toast(e.message, 'error'); }
        }));
    } catch (e) { UI.toast(e.message, 'error'); }
  }
};
