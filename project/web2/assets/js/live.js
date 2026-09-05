// SIGoIB WEB2 - live layer: polling async (fetch), TANPA reload halaman.
// - Satu timer per key (tidak ada timer ganda / memory leak).
// - Pause otomatis saat tab tidak aktif (Page Visibility API); resume + refresh saat kembali.
// - Aksi (approve/reject/revoke/alert/cancel) via fetch POST ke api/action.php (CSRF).
(function (w) {
  var timers = {};

  function poll(key, urlFn, intervalMs, onData) {
    if (timers[key]) {
      return timers[key];
    }
    var stopped = false;
    async function tick() {
      if (stopped || document.hidden) {
        return;
      }
      try {
        var res = await fetch(urlFn(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        var ct = res.headers.get('content-type') || '';
        if (!res.ok || ct.indexOf('application/json') === -1) {
          throw new Error('bad response');
        }
        var data = await res.json();
        onData(data, true);
      } catch (e) {
        onData(null, false);
      }
    }
    var h = setInterval(tick, intervalMs);
    var onVis = function () { if (!document.hidden) { tick(); } };
    document.addEventListener('visibilitychange', onVis);
    timers[key] = {
      stop: function () { stopped = true; clearInterval(h); document.removeEventListener('visibilitychange', onVis); },
      now: tick,
    };
    tick();
    return timers[key];
  }

  function toast(msg, type) {
    var c = document.getElementById('toastWrap');
    if (!c) { return; }
    var t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'info');
    t.textContent = msg;
    c.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 300);
    }, 4000);
  }

  async function action(payload) {
    var res = await fetch('api/action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': w.WEB2_CSRF || '' },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ _csrf: w.WEB2_CSRF || '' }, payload)),
    });
    try {
      return await res.json();
    } catch (e) {
      return { ok: false, message: 'Tidak dapat terhubung ke server.' };
    }
  }

  // Format waktu relatif ringkas dari timestamp "Y-m-d H:i:s" (server) -> "20 detik lalu".
  function ago(ts) {
    if (!ts) { return '-'; }
    var t = Date.parse(ts.replace(' ', 'T'));
    if (isNaN(t)) { return ts; }
    var s = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (s < 60) { return s + ' detik lalu'; }
    if (s < 3600) { return Math.floor(s / 60) + ' menit lalu'; }
    if (s < 86400) { return Math.floor(s / 3600) + ' jam lalu'; }
    return Math.floor(s / 86400) + ' hari lalu';
  }

  function connFromSeen(ts) {
    if (!ts) { return 'OFFLINE'; }
    var t = Date.parse(ts.replace(' ', 'T'));
    if (isNaN(t)) { return 'OFFLINE'; }
    var s = (Date.now() - t) / 1000;
    if (s < 120) { return 'ONLINE'; }
    if (s <= 300) { return 'TERLAMBAT'; }
    return 'OFFLINE';
  }

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  w.Web2Live = { poll: poll, toast: toast, action: action, ago: ago, connFromSeen: connFromSeen, esc: esc };
})(window);
