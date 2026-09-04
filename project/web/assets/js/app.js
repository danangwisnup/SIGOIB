// Shell aplikasi: login, sidebar, navigasi halaman.
const App = {
  current: null,
  menus: [
    ['dashboard', 'Dashboard'],
    ['personnel', 'Personel'],
    ['devices', 'Perangkat'],
    ['monitoring', 'Monitoring'],
    ['alerts', 'Alert'],
    ['geofences', 'Area Terlarang'],
    ['history', 'Riwayat'],
    ['reports', 'Laporan'],
    ['settings', 'Pengaturan'],
  ],

  init() {
    if (Api.token && Api.user) this.renderShell();
    else this.renderLogin();
  },

  renderLogin() {
    document.getElementById('app').innerHTML = `
      <div class="login-wrap">
        <div class="login-card">
          <h1 data-testid="login-title">Monitoring IB & Quick Check</h1>
          <p>Masuk dengan akun admin / komandan.</p>
          <div id="login-error"></div>
          <form id="login-form">
            <div class="form-row"><label>Username</label>
              <input data-testid="login-username" id="login-username" autocomplete="username" required></div>
            <div class="form-row"><label>Password</label>
              <input data-testid="login-password" id="login-password" type="password" autocomplete="current-password" required></div>
            <button data-testid="login-submit" class="btn primary" style="width:100%" type="submit">Masuk</button>
          </form>
        </div>
      </div>`;
    document.getElementById('login-form').onsubmit = async (e) => {
      e.preventDefault();
      const err = document.getElementById('login-error');
      err.innerHTML = '';
      try {
        const data = await Api.post('/auth/login', {
          username: document.getElementById('login-username').value.trim(),
          password: document.getElementById('login-password').value,
        });
        Api.token = data.token;
        Api.user = data.user;
        this.renderShell();
      } catch (ex) {
        err.innerHTML = `<div class="alert-bar error" data-testid="login-error-msg">${UI.esc(ex.message)}</div>`;
      }
    };
  },

  renderShell() {
    const u = Api.user;
    document.getElementById('app').innerHTML = `
      <div class="layout">
        <aside class="sidebar">
          <div class="brand">MONITORING IB<br>& QUICK CHECK</div>
          <nav data-testid="sidebar-nav">${this.menus.map(([k, label]) =>
            `<a data-testid="menu-${k}" data-page="${k}">${label}</a>`).join('')}</nav>
          <div class="user-box">
            <div>${UI.esc(u.name)}</div>
            <div class="role">${UI.esc(u.role)}</div>
            <button data-testid="logout-btn" class="btn sm" id="logout-btn">Keluar</button>
          </div>
        </aside>
        <div class="main">
          <div class="topbar">
            <h2 id="page-title"></h2>
            <div class="server-time" id="server-time"></div>
          </div>
          <div class="content" id="content"></div>
        </div>
      </div>`;
    document.querySelectorAll('.sidebar nav a').forEach(a =>
      a.onclick = () => this.navigate(a.dataset.page));
    document.getElementById('logout-btn').onclick = async () => {
      try { await Api.post('/auth/logout'); } catch (e) {}
      Api.token = null; Api.user = null; this.renderLogin();
    };
    this.navigate(location.hash.replace('#', '') || 'dashboard');
  },

  navigate(page) {
    if (!Pages[page]) page = 'dashboard';
    if (this.current && Pages[this.current] && Pages[this.current].destroy) {
      Pages[this.current].destroy();
    }
    this.current = page;
    location.hash = page;
    document.querySelectorAll('.sidebar nav a').forEach(a =>
      a.classList.toggle('active', a.dataset.page === page));
    const menu = this.menus.find(([k]) => k === page);
    document.getElementById('page-title').textContent = menu ? menu[1] : '';
    Pages[page].render(document.getElementById('content'));
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
