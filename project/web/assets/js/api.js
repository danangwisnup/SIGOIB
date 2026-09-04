// Wrapper fetch ke REST API. Token disimpan di localStorage (web admin internal).
const Api = {
  get token() { return localStorage.getItem('mb_token'); },
  set token(v) { v ? localStorage.setItem('mb_token', v) : localStorage.removeItem('mb_token'); },
  get user() { try { return JSON.parse(localStorage.getItem('mb_user')); } catch (e) { return null; } },
  set user(v) { v ? localStorage.setItem('mb_user', JSON.stringify(v)) : localStorage.removeItem('mb_user'); },

  async request(method, path, body, isForm) {
    const opt = { method, headers: {} };
    if (this.token) opt.headers['Authorization'] = 'Bearer ' + this.token;
    if (body && !isForm) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    if (body && isForm) opt.body = body;
    let res;
    try {
      res = await fetch('/api' + path, opt);
    } catch (e) {
      throw new Error('Tidak dapat terhubung ke server.');
    }
    let json;
    try { json = await res.json(); } catch (e) { throw new Error('Response server tidak valid (HTTP ' + res.status + ').'); }
    if (!json.success) {
      if (res.status === 401 && !path.startsWith('/auth/login')) {
        Api.token = null; Api.user = null; location.reload();
      }
      throw new Error(json.message || 'Terjadi kesalahan.');
    }
    return json.data;
  },
  get(p) { return this.request('GET', p); },
  post(p, b) { return this.request('POST', p, b || {}); },
  put(p, b) { return this.request('PUT', p, b || {}); },
  del(p) { return this.request('DELETE', p); },
  postForm(p, formData) { return this.request('POST', p, formData, true); }
};
