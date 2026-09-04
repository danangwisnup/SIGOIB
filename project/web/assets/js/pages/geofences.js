// Area Terlarang (geofence circle) + picker lokasi via map.
Pages.geofences = {
  async render(el) {
    this.el = el;
    const canManage = ['ADMIN', 'KOMANDAN', 'WADAN'].includes(Api.user.role);
    this.canManage = canManage;
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Area Terlarang</h3>
          ${canManage ? '<button class="btn primary" id="gf-add" data-testid="add-geofence-btn">+ Tambah Area</button>' : ''}</div>
        <div class="panel-body flush" id="gf-table" data-testid="geofence-table"><div class="empty">Memuat...</div></div>
      </div>`;
    if (canManage) document.getElementById('gf-add').onclick = () => this.formModal(null);
    await this.load();
  },

  async load() {
    try {
      const { items } = await Api.get('/geofences');
      const box = document.getElementById('gf-table');
      box.innerHTML = items.length ? `<table class="tbl"><thead><tr>
          <th>Nama</th><th>Kategori</th><th>Koordinat</th><th>Radius</th><th>Status</th>${this.canManage ? '<th>Aksi</th>' : ''}
        </tr></thead><tbody>${items.map(g => `<tr>
          <td>${UI.esc(g.name)}</td><td>${UI.esc(g.category || '-')}</td>
          <td>${g.latitude}, ${g.longitude}</td><td>${g.radius} m</td>
          <td>${UI.statusBadge(g.status)}</td>
          ${this.canManage ? `<td>
            <button class="btn sm" data-edit="${g.id}" data-testid="gf-edit-${g.id}">Edit</button>
            <button class="btn sm danger" data-del="${g.id}" data-testid="gf-del-${g.id}">Hapus</button></td>` : ''}
        </tr>`).join('')}</tbody></table>` : '<div class="empty">Belum ada area terlarang.</div>';

      box.querySelectorAll('[data-edit]').forEach(b => b.onclick = () =>
        this.formModal(items.find(g => g.id == b.dataset.edit)));
      box.querySelectorAll('[data-del]').forEach(b => b.onclick = () =>
        UI.confirm('Hapus area ini? Alert historis tetap tersimpan.', async () => {
          try { await Api.del('/geofences/' + b.dataset.del); UI.toast('Area dihapus.'); this.load(); }
          catch (e) { UI.toast(e.message, 'error'); }
        }));
    } catch (e) { UI.toast(e.message, 'error'); }
  },

  formModal(g) {
    const isEdit = !!g;
    const m = UI.modal(isEdit ? 'Edit Area' : 'Tambah Area Terlarang', `
      <div id="gf-error"></div>
      <div class="form-grid">
        <div class="form-row"><label>Nama *</label><input id="gf-name" data-testid="gf-name" value="${UI.esc(g?.name || '')}" placeholder="cth: Club X"></div>
        <div class="form-row"><label>Kategori</label><input id="gf-cat" value="${UI.esc(g?.category || '')}" placeholder="cth: Tempat Hiburan"></div>
        <div class="form-row"><label>Latitude *</label><input id="gf-lat" data-testid="gf-lat" value="${g?.latitude ?? ''}"></div>
        <div class="form-row"><label>Longitude *</label><input id="gf-lng" data-testid="gf-lng" value="${g?.longitude ?? ''}"></div>
        <div class="form-row"><label>Radius (meter) *</label><input type="number" id="gf-radius" data-testid="gf-radius" value="${g?.radius ?? 300}"></div>
        ${isEdit ? `<div class="form-row"><label>Status</label><select id="gf-status">
          <option value="ACTIVE" ${g.status === 'ACTIVE' ? 'selected' : ''}>ACTIVE</option>
          <option value="INACTIVE" ${g.status === 'INACTIVE' ? 'selected' : ''}>INACTIVE</option></select></div>` : ''}
      </div>
      <p class="muted mb16">Klik pada peta untuk memilih titik tengah area.</p>
      <div id="gf-map" class="map-box sm"></div>
      <div class="form-actions mt16">
        <button class="btn" data-act="cancel">Batal</button>
        <button class="btn primary" data-act="save" data-testid="gf-save">Simpan</button>
      </div>`, { wide: true });

    const lat = parseFloat(g?.latitude) || -2.5, lng = parseFloat(g?.longitude) || 118.0;
    const zoom = g ? 15 : 5;
    const map = UI.makeMap('gf-map', [lat, lng], zoom);
    let circle = null;
    const drawCircle = (la, ln, r) => {
      if (circle) map.removeLayer(circle);
      circle = L.circle([la, ln], { radius: r, color: '#dc2626' }).addTo(map);
    };
    if (g) drawCircle(lat, lng, parseInt(g.radius));
    map.on('click', (e) => {
      m.querySelector('#gf-lat').value = e.latlng.lat.toFixed(7);
      m.querySelector('#gf-lng').value = e.latlng.lng.toFixed(7);
      drawCircle(e.latlng.lat, e.latlng.lng, parseInt(m.querySelector('#gf-radius').value) || 300);
    });

    m.querySelector('[data-act=cancel]').onclick = () => m.remove();
    m.querySelector('[data-act=save]').onclick = async () => {
      const body = {
        name: m.querySelector('#gf-name').value.trim(),
        category: m.querySelector('#gf-cat').value.trim(),
        latitude: parseFloat(m.querySelector('#gf-lat').value),
        longitude: parseFloat(m.querySelector('#gf-lng').value),
        radius: parseInt(m.querySelector('#gf-radius').value),
      };
      if (isEdit) body.status = m.querySelector('#gf-status').value;
      try {
        if (isEdit) await Api.put('/geofences/' + g.id, body);
        else await Api.post('/geofences', body);
        m.remove(); UI.toast('Area tersimpan.'); this.load();
      } catch (e) {
        m.querySelector('#gf-error').innerHTML = `<div class="alert-bar error">${UI.esc(e.message)}</div>`;
      }
    };
  }
};
