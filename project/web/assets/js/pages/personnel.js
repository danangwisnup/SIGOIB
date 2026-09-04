// Personel: tabel, search, filter, pagination, import CSV, add/edit.
Pages.personnel = {
  state: { q: '', company_id: '', platoon_id: '', page: 1 },

  async render(el) {
    this.el = el;
    const orgs = await UI.loadOrganizations();
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head">
          <h3>Data Personel</h3>
          <div class="toolbar">
            ${canManage ? `<button class="btn primary" data-testid="import-btn" id="btn-import">Import CSV/Excel</button>
            <button class="btn" data-testid="add-personnel-btn" id="btn-add">+ Tambah</button>` : ''}
          </div>
        </div>
        <div class="panel-body">
          <div class="toolbar mb16">
            <input data-testid="search-input" id="f-q" placeholder="Cari NRP / Nama" style="width:220px">
            <select data-testid="filter-company" id="f-company"><option value="">Semua Kompi</option>${UI.orgOptions(orgs, 'KOMPI')}</select>
            <select data-testid="filter-platoon" id="f-platoon"><option value="">Semua Peleton</option>${UI.orgOptions(orgs, 'PELETON')}</select>
            <button class="btn" id="btn-search" data-testid="search-btn">Cari</button>
          </div>
          <div id="personnel-table" data-testid="personnel-table"><div class="empty">Memuat...</div></div>
          <div class="pagination" id="personnel-pager"></div>
        </div>
      </div>`;
    document.getElementById('btn-search').onclick = () => {
      this.state.q = document.getElementById('f-q').value.trim();
      this.state.company_id = document.getElementById('f-company').value;
      this.state.platoon_id = document.getElementById('f-platoon').value;
      this.state.page = 1;
      this.load();
    };
    if (canManage) {
      document.getElementById('btn-add').onclick = () => this.formModal(null, orgs);
      document.getElementById('btn-import').onclick = () => this.importModal();
    }
    await this.load();
  },

  async load() {
    const s = this.state;
    try {
      const data = await Api.get(`/personnel?page=${s.page}&q=${encodeURIComponent(s.q)}&company_id=${s.company_id}&platoon_id=${s.platoon_id}`);
      const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
      const box = document.getElementById('personnel-table');
      if (!data.items.length) { box.innerHTML = '<div class="empty">Tidak ada data.</div>'; }
      else {
        box.innerHTML = `<table class="tbl"><thead><tr>
          <th>NRP</th><th>Nama</th><th>Pangkat</th><th>Jabatan</th><th>Kompi</th><th>Peleton</th><th>Status</th>${canManage ? '<th></th>' : ''}
          </tr></thead><tbody>${data.items.map(p => `<tr>
            <td>${UI.esc(p.nrp)}</td><td>${UI.esc(p.name)}</td><td>${UI.esc(p.rank || '-')}</td>
            <td>${UI.esc(p.position || '-')}</td><td>${UI.esc(p.company_name || '-')}</td>
            <td>${UI.esc(p.platoon_name || '-')}</td><td>${UI.statusBadge(p.status)}</td>
            ${canManage ? `<td><button class="btn sm" data-edit="${p.id}" data-testid="edit-${p.id}">Edit</button></td>` : ''}
          </tr>`).join('')}</tbody></table>`;
        box.querySelectorAll('[data-edit]').forEach(b => b.onclick = async () => {
          const orgs = await UI.loadOrganizations();
          const { personnel } = await Api.get('/personnel/' + b.dataset.edit);
          this.formModal(personnel, orgs);
        });
      }
      UI.pagination(document.getElementById('personnel-pager'), data, p => { this.state.page = p; this.load(); });
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  formModal(p, orgs) {
    const isEdit = !!p;
    const m = UI.modal(isEdit ? 'Edit Personel' : 'Tambah Personel', `
      <div id="form-error"></div>
      <div class="form-grid">
        <div class="form-row"><label>NRP *</label><input data-testid="form-nrp" id="pf-nrp" value="${UI.esc(p?.nrp || '')}"></div>
        <div class="form-row"><label>Nama *</label><input data-testid="form-name" id="pf-name" value="${UI.esc(p?.name || '')}"></div>
        <div class="form-row"><label>Pangkat</label><input id="pf-rank" value="${UI.esc(p?.rank || '')}"></div>
        <div class="form-row"><label>Jabatan</label><input id="pf-position" value="${UI.esc(p?.position || '')}"></div>
        <div class="form-row"><label>Kompi</label><select id="pf-company"><option value="">-</option>${UI.orgOptions(orgs, 'KOMPI', p?.company_id)}</select></div>
        <div class="form-row"><label>Peleton</label><select id="pf-platoon"><option value="">-</option>${UI.orgOptions(orgs, 'PELETON', p?.platoon_id)}</select></div>
        ${isEdit ? `<div class="form-row"><label>Status</label><select id="pf-status">
          <option value="ACTIVE" ${p.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
          <option value="INACTIVE" ${p.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option></select></div>` : ''}
      </div>
      <div class="form-actions">
        <button class="btn" data-act="cancel">Batal</button>
        <button class="btn primary" data-testid="form-save" data-act="save">Simpan</button>
      </div>`);
    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=save]').onclick = async () => {
      const body = {
        nrp: m.querySelector('#pf-nrp').value.trim(),
        name: m.querySelector('#pf-name').value.trim(),
        rank: m.querySelector('#pf-rank').value.trim(),
        position: m.querySelector('#pf-position').value.trim(),
        company_id: m.querySelector('#pf-company').value || null,
        platoon_id: m.querySelector('#pf-platoon').value || null,
      };
      if (isEdit) body.status = m.querySelector('#pf-status').value;
      try {
        if (isEdit) await Api.put('/personnel/' + p.id, body);
        else await Api.post('/personnel', body);
        m.remove(); UI.toast('Personel tersimpan.'); this.load();
      } catch (e) {
        m.querySelector('#form-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  },

  importModal() {
    const m = UI.modal('Import Personel (CSV)', `
      <p class="muted mb16">Format kolom: <b>NRP, Nama, Pangkat, Jabatan, Kompi, Peleton, Foto(optional)</b>.<br>
      Dari Excel: File &rarr; Save As &rarr; CSV.</p>
      <div id="imp-error"></div>
      <div class="form-row"><input type="file" id="imp-file" data-testid="import-file" accept=".csv"></div>
      <div class="form-actions">
        <button class="btn" data-act="cancel">Tutup</button>
        <button class="btn" data-testid="import-preview-btn" data-act="preview">Preview</button>
        <button class="btn primary" data-testid="import-commit-btn" data-act="commit" disabled>Import</button>
      </div>
      <div id="imp-result" class="mt16"></div>`, { wide: true });

    const fileInput = () => m.querySelector('#imp-file').files[0];
    const upload = async (mode) => {
      if (!fileInput()) { UI.toast('Pilih file CSV dulu.', 'error'); return null; }
      const fd = new FormData();
      fd.append('file', fileInput());
      fd.append('mode', mode);
      return Api.postForm('/personnel/import', fd);
    };

    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=preview]').onclick = async () => {
      const res = m.querySelector('#imp-result');
      try {
        const data = await upload('preview');
        if (!data) return;
        res.innerHTML = `<p class="mb16">Total ${data.total} baris: <b>${data.valid} valid</b>, <b style="color:var(--red)">${data.invalid} bermasalah</b>.</p>
          <div class="import-preview"><table class="tbl"><thead><tr><th>Baris</th><th>NRP</th><th>Nama</th><th>Kompi</th><th>Peleton</th><th>Error</th></tr></thead>
          <tbody>${data.rows.map(r => `<tr>
            <td>${r.row}</td><td>${UI.esc(r.data.nrp)}</td><td>${UI.esc(r.data.name)}</td>
            <td>${UI.esc(r.data.company_name || '-')}</td><td>${UI.esc(r.data.platoon_name || '-')}</td>
            <td class="row-errors">${r.errors.map(UI.esc).join('<br>')}</td></tr>`).join('')}
          </tbody></table></div>`;
        m.querySelector('[data-act=commit]').disabled = data.valid === 0;
      } catch (e) {
        m.querySelector('#imp-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
    m.querySelector('[data-act=commit]').onclick = async () => {
      try {
        const data = await upload('commit');
        if (!data) return;
        UI.toast(`Import selesai: ${data.imported} masuk, ${data.skipped} dilewati.`);
        m.remove();
        this.load();
      } catch (e) {
        m.querySelector('#imp-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  }
};
