// Laporan: pilih session -> ringkasan per personel, export CSV (Excel), print -> PDF.
Pages.reports = {
  async render(el) {
    el.innerHTML = `
      <div class="panel">
        <div class="panel-head"><h3>Laporan Monitoring</h3></div>
        <div class="panel-body">
          <div class="toolbar mb16">
            <select id="rp-session" data-testid="report-session" style="min-width:280px"><option value="">Memuat session...</option></select>
            <button class="btn primary" id="rp-load" data-testid="report-load">Tampilkan</button>
          </div>
          <div id="rp-result" data-testid="report-result"></div>
        </div>
      </div>`;
    try {
      const { items } = await Api.get('/monitoring');
      document.getElementById('rp-session').innerHTML =
        '<option value="">Pilih monitoring...</option>' +
        items.map(s => `<option value="${s.id}">${UI.esc(s.name)} (${s.type}, ${s.status})</option>`).join('');
    } catch (e) { UI.toast(e.message, 'error'); }
    document.getElementById('rp-load').onclick = () => this.load();
  },

  async load() {
    const id = document.getElementById('rp-session').value;
    if (!id) { UI.toast('Pilih session dulu.', 'error'); return; }
    try {
      const data = await Api.get('/reports/monitoring/' + id);
      const s = data.session;
      document.getElementById('rp-result').innerHTML = `
        <div class="mb16">
          <h4>${UI.esc(s.name)}</h4>
          <p class="muted">${s.type} &bull; ${UI.fmtDateTime(s.start_at)} s/d ${UI.fmtDateTime(s.end_at)} &bull; Status: ${s.status}</p>
        </div>
        <div class="toolbar mb16">
          <button class="btn" id="rp-csv" data-testid="report-export-excel">Export Excel (CSV)</button>
          <button class="btn" onclick="window.print()" data-testid="report-export-pdf">Export PDF (Print)</button>
        </div>
        <table class="tbl"><thead><tr>
          <th>NRP</th><th>Nama</th><th>Pangkat</th><th>Kompi</th><th>Peleton</th>
          <th>GPS Point</th><th>GPS Pertama</th><th>GPS Terakhir</th><th>Alert</th>
        </tr></thead><tbody>${data.rows.map(r => `<tr>
          <td>${UI.esc(r.nrp)}</td><td>${UI.esc(r.name)}</td><td>${UI.esc(r.rank || '-')}</td>
          <td>${UI.esc(r.company || '-')}</td><td>${UI.esc(r.platoon || '-')}</td>
          <td>${r.points}</td><td>${UI.fmtDateTime(r.first_at)}</td><td>${UI.fmtDateTime(r.last_at)}</td>
          <td>${r.alerts}</td></tr>`).join('')}</tbody></table>`;
      document.getElementById('rp-csv').onclick = async () => {
        try { await UI.downloadFile(`/reports/monitoring/${id}?format=csv`, `laporan_${id}.csv`); }
        catch (e) { UI.toast(e.message, 'error'); }
      };
    } catch (e) { UI.toast(e.message, 'error'); }
  }
};
