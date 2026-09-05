</main>
</div>
<?php if (!empty($needMap)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/map.js"></script>
<?php endif; ?>
<script>window.WEB2_CSRF = "<?= csrf_token() ?>";</script>
<script src="assets/js/live.js"></script>
<div id="toastWrap" class="toast-wrap"></div>
<script>
// JS minimal: sidebar drawer/collapse, countdown refresh, modal, konfirmasi.
(function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('drawer-open');
                overlay.classList.toggle('show');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('drawer-open');
            overlay.classList.remove('show');
        });
    }

    var cd = document.getElementById('refreshCountdown');
    if (cd) {
        var s = parseInt(cd.textContent, 10) || 10;
        setInterval(function () {
            s = s > 1 ? s - 1 : <?= WEB2_REFRESH_SECONDS ?>;
            cd.textContent = s;
        }, 1000);
    }

    // Modal open/close via [data-modal-open="id"] / [data-modal-close]
    document.querySelectorAll('[data-modal-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            var m = document.getElementById(b.getAttribute('data-modal-open'));
            if (m) m.classList.add('show');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (b) {
        b.addEventListener('click', function () {
            var m = b.closest('.modal-backdrop');
            if (m) m.classList.remove('show');
        });
    });
    document.querySelectorAll('.modal-backdrop').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) m.classList.remove('show');
        });
    });

    // Konfirmasi aksi berisiko global (event delegation -> robust untuk konten apa pun):
    // <form class="confirm-form" data-confirm="Judul|Pesan|Label Tombol">
    // Delegasi di document memastikan tombol (mis. REVOKE) selalu berfungsi walau ada
    // banyak form / form ditambahkan setelah load. Bila markup modal tidak lengkap,
    // submit native tetap berjalan sehingga tombol TIDAK pernah "mati".
    var pendingForm = null;
    var cfModal = document.getElementById('confirmModal');
    var cfTitle = document.getElementById('cfTitle');
    var cfBody = document.getElementById('cfBody');
    var cfYes = document.getElementById('cfYes');

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.classList || !f.classList.contains('confirm-form')) return;
        if (!cfModal || !cfTitle || !cfBody || !cfYes) return; // fallback: submit native
        e.preventDefault();
        pendingForm = f;
        var parts = (f.getAttribute('data-confirm') || 'Konfirmasi?|Lanjutkan?|YA').split('|');
        cfTitle.textContent = parts[0];
        cfBody.textContent = parts[1] || '';
        cfYes.textContent = parts[2] || 'YA';
        cfModal.classList.add('show');
    });

    if (cfYes) {
        cfYes.addEventListener('click', function () {
            var f = pendingForm;
            pendingForm = null;
            if (cfModal) cfModal.classList.remove('show');
            if (!f) return;
            // HTMLFormElement.submit() melewati listener submit -> tidak memicu modal lagi.
            if (typeof f.submit === 'function') f.submit();
            else f.dispatchEvent(new Event('submit'));
        });
    }
})();
</script>
<div class="modal-backdrop" id="confirmModal" data-testid="confirm-modal">
    <div class="modal">
        <h3 id="cfTitle">Konfirmasi</h3>
        <p id="cfBody" class="mb16"></p>
        <div class="form-actions">
            <button class="btn" data-modal-close>BATAL</button>
            <button class="btn btn-danger" id="cfYes" data-testid="confirm-yes">YA, LANJUTKAN</button>
        </div>
    </div>
</div>
</body>
</html>
