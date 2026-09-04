// Pengaturan: ganti password, manajemen akun (ADMIN), audit log (ADMIN/KOMANDAN).
Pages.settings = {
  async render(el) {
    const u = Api.user;
    const isAdmin = u.role === 'ADMIN';
    const canAudit = ['ADMIN', 'KOMANDAN'].includes(u.role);
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Akun Saya</h3></div>
        <div class="panel-body">
          <p class="mb16"><b>${UI.esc(u.name)}</b> (${UI.esc(u.username)}) &bull; Role: ${UI.esc(u.role)}</p>
          <div id="pw-msg"></div>
          <div class="form-grid" style="max-width:520px">
            <div class="form-row"><label>Password Saat Ini</label><input type="password" id="pw-cur" data-testid="pw-current"></div>
            <div class="form-row"><label>Password Baru</label><input type="password" id="pw-new" data-testid="pw-new"></div>
          </div>
          <button class="btn primary" id="pw-save" data-testid="pw-save">Ganti Password</button>
        </div>
      </div>
      ${isAdmin ? `
      <div class="panel">
        <div class="panel-head"><h3>Manajemen Akun</h3>
          <button class="btn primary" id="usr-add" data-testid="add-user-btn">+ Tambah Akun</button></div>
        <div class="panel-body flush" id="usr-table" data-testid="user-table"><div class="empty">Memuat...</div></div>
      </div>` : ''}
      ${canAudit ? `
      <div class="panel">
        <div class="panel-head"><h3>Audit Log</h3></div>
        <div class="panel-body flush" id="audit-table" data-testid="audit-table"><div class="empty">Memuat...</div></div>
      </div>` : ''}`;

    document.getElementById('pw-save').onclick = async () => {
      const msg = document.getElementById('pw-msg');
      try {
        await Api.put('/auth/password', {
          current_password: document.getElementById('pw-cur').value,
          new_password: document.getElementById('pw-new').value,
        });
        msg.innerHTML = '<div class="alert-bar success">Password berhasil diubah.</div>';
      } catch (e) {
        msg.innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };

    if (isAdmin) {
      document.getElementById('usr-add').onclick = () => this.userModal(null);
      this.loadUsers();
    }
    if (canAudit) this.loadAudit();
  },

  async loadUsers() {
    try {
      const { items } = await Api.get('/users');
      const box = document.getElementById('usr-table');
      box.innerHTML = `<table class="tbl"><thead><tr>
          <th>Nama</th><th>Username</th><th>Role</th><th>Organisasi</th><th>Status</th><th></th>
        </tr></thead><tbody>${items.map(u => `<tr>
          <td>${UI.esc(u.name)}</td><td>${UI.esc(u.username)}</td><td>${UI.badge(u.role, 'blue')}</td>
          <td>${UI.esc(u.organization_name || '-')}</td><td>${UI.statusBadge(u.status)}</td>
          <td><button class="btn sm" data-edit="${u.id}" data-testid="user-edit-${u.id}">Edit</button></td>
        </tr>`).join('')}</tbody></table>`;
      box.querySelectorAll('[data-edit]').forEach(b => b.onclick = () =>
        this.userModal(items.find(u => u.id == b.dataset.edit)));
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  async userModal(usr) {
    const isEdit = !!usr;
    const orgs = await UI.loadOrganizations();
    const m = UI.modal(isEdit ? 'Edit Akun' : 'Tambah Akun', `
      <div id="usr-error"></div>
      <div class="form-grid">
        <div class="form-row"><label>Nama *</label><input id="uf-name" data-testid="uf-name" value="${UI.esc(usr?.name || '')}"></div>
        <div class="form-row"><label>Username *</label><input id="uf-username" data-testid="uf-username" value="${UI.esc(usr?.username || '')}" ${isEdit ? 'disabled' : ''}></div>
        <div class="form-row"><label>Role *</label>
          <select id="uf-role" data-testid="uf-role">
            ${['ADMIN', 'KOMANDAN', 'WADAN', 'DANKI', 'DANTON'].map(r =>
              `<option ${usr?.role === r ? 'selected' : ''}>${r}</option>`).join('')}
          </select></div>
        <div class="form-row"><label>Organisasi (untuk DANKI/DANTON)</label>
          <select id="uf-org"><option value="">-</option>
            ${orgs.filter(o => o.type !== 'BATALYON').map(o =>
              `<option value="${o.id}" ${usr?.organization_id == o.id ? 'selected' : ''}>${UI.esc(o.name)} (${o.type})</option>`).join('')}
          </select></div>
        <div class="form-row"><label>${isEdit ? 'Password Baru (kosongkan jika tidak diganti)' : 'Password *'}</label>
          <input type="password" id="uf-password" data-testid="uf-password"></div>
        ${isEdit ? `<div class="form-row"><label>Status</label>
          <select id="uf-status">
            <option value="ACTIVE" ${usr.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
            <option value="INACTIVE" ${usr.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option>
          </select></div>` : ''}
      </div>
      <div class="form-actions">
        <button class="btn" data-act="cancel">Batal</button>
        <button class="btn primary" data-act="save" data-testid="uf-save">Simpan</button>
      </div>`);
    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=save]').onclick = async () => {
      const body = {
        name: m.querySelector('#uf-name').value.trim(),
        role: m.querySelector('#uf-role').value,
        organization_id: m.querySelector('#uf-org').value || null,
        password: m.querySelector('#uf-password').value,
      };
      try {
        if (isEdit) {
          body.status = m.querySelector('#uf-status').value;
          if (!body.password) delete body.password;
          await Api.put('/users/' + usr.id, body);
        } else {
          body.username = m.querySelector('#uf-username').value.trim();
          await Api.post('/users', body);
        }
        m.remove(); UI.toast('Akun tersimpan.'); this.loadUsers();
      } catch (e) {
        m.querySelector('#usr-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  },

  async loadAudit() {
    try {
      const { items } = await Api.get('/audit-logs');
      document.getElementById('audit-table').innerHTML = items.length ? `
        <table class="tbl"><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Deskripsi</th></tr></thead>
        <tbody>${items.map(a => `<tr>
          <td>${UI.fmtDateTime(a.created_at)}</td><td>${UI.esc(a.user_name || '-')}</td>
          <td>${UI.esc(a.action)}</td><td>${UI.esc(a.description || '-')}</td></tr>`).join('')}
        </tbody></table>` : '<div class="empty">Belum ada log.</div>';
    } catch (e) { /* abaikan untuk non-admin */ }
  }
};
