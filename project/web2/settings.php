<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$isAdmin = ($user['role'] ?? '') === 'ADMIN';

$pageTitle = 'Pengaturan';
$activeMenu = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'password') {
        $r = api_put('/auth/password', [
            'current_password' => (string)($_POST['current_password'] ?? ''),
            'new_password' => (string)($_POST['new_password'] ?? ''),
        ]);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Password berhasil diubah.' : ($r['message'] ?: 'Password gagal diubah.'));
        header('Location: settings.php');
        exit;
    }

    if ($action === 'user_create' && $isAdmin) {
        $r = api_post('/users', [
            'name' => trim((string)$_POST['name']),
            'username' => trim((string)$_POST['username']),
            'password' => (string)$_POST['password'],
            'role' => (string)$_POST['role'],
            'organization_id' => $_POST['organization_id'] !== '' ? (int)$_POST['organization_id'] : null,
        ]);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Akun berhasil dibuat.' : ($r['message'] ?: 'Akun gagal dibuat.'));
        header('Location: settings.php');
        exit;
    }

    if ($action === 'user_update' && $isAdmin) {
        $body = [
            'name' => trim((string)$_POST['name']),
            'role' => (string)$_POST['role'],
            'organization_id' => $_POST['organization_id'] !== '' ? (int)$_POST['organization_id'] : null,
            'status' => in_array($_POST['status'] ?? '', ['ACTIVE', 'INACTIVE'], true) ? $_POST['status'] : 'ACTIVE',
        ];
        if (trim((string)$_POST['password']) !== '') $body['password'] = (string)$_POST['password'];
        $r = api_put('/users/' . (int)$_POST['user_id'], $body);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Akun berhasil diperbarui.' : ($r['message'] ?: 'Akun gagal diperbarui.'));
        header('Location: settings.php');
        exit;
    }
}

$users = [];
$orgs = [];
if ($isAdmin) {
    $uRes = api_get('/users');
    $users = $uRes['ok'] ? ($uRes['data']['items'] ?? []) : [];
    $oRes = api_get('/organizations');
    $orgs = array_values(array_filter(
        $oRes['ok'] ? ($oRes['data']['items'] ?? []) : [],
        fn($o) => $o['type'] !== 'BATALYON'
    ));
}
$editUser = null;
if ($isAdmin && !empty($_GET['edit_user'])) {
    foreach ($users as $u) {
        if ((int)$u['id'] === (int)$_GET['edit_user']) $editUser = $u;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="split-even">
    <div class="panel">
        <div class="panel-head"><h2>Akun Saya</h2></div>
        <div class="panel-body">
            <dl class="kv mb16">
                <dt>Nama</dt><dd><b><?= e($user['name']) ?></b></dd>
                <dt>Username</dt><dd><?= e($user['username']) ?></dd>
                <dt>Role</dt><dd><?= badge('IB') === '' ? '' : '' ?><?= e($user['role']) ?></dd>
            </dl>
            <h3 class="mb16">Ganti Password</h3>
            <form method="post" data-testid="password-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <div class="form-row"><label>Password Saat Ini</label>
                    <input type="password" name="current_password" required data-testid="pw-current"></div>
                <div class="form-row"><label>Password Baru (min. 6 karakter)</label>
                    <input type="password" name="new_password" minlength="6" required data-testid="pw-new"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-testid="pw-save">GANTI PASSWORD</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="panel">
        <div class="panel-head"><h2><?= $editUser ? 'Edit Akun' : 'Tambah Akun' ?></h2></div>
        <div class="panel-body">
            <form method="post" data-testid="user-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editUser ? 'user_update' : 'user_create' ?>">
                <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-row"><label>Nama *</label>
                        <input name="name" value="<?= e($editUser['name'] ?? '') ?>" required data-testid="uf-name"></div>
                    <div class="form-row"><label>Username *</label>
                        <input name="username" value="<?= e($editUser['username'] ?? '') ?>" <?= $editUser ? 'disabled' : 'required' ?>></div>
                    <div class="form-row"><label>Role *</label>
                        <select name="role" data-testid="uf-role">
                            <?php foreach (['ADMIN', 'KOMANDAN', 'WADAN', 'DANKI', 'DANTON'] as $role): ?>
                            <option <?= ($editUser['role'] ?? '') === $role ? 'selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-row"><label>Organisasi (DANKI/DANTON)</label>
                        <select name="organization_id">
                            <option value="">-</option>
                            <?php foreach ($orgs as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= (int)($editUser['organization_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>>
                                <?= e($o['name']) ?> (<?= e($o['type']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-row"><label><?= $editUser ? 'Password Baru (kosongkan bila tidak diganti)' : 'Password *' ?></label>
                        <input type="password" name="password" <?= $editUser ? '' : 'required' ?> minlength="6"></div>
                    <?php if ($editUser): ?>
                    <div class="form-row"><label>Status</label>
                        <select name="status">
                            <option value="ACTIVE" <?= $editUser['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                            <option value="INACTIVE" <?= $editUser['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                        </select></div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <?php if ($editUser): ?><a class="btn" href="settings.php">BATAL</a><?php endif; ?>
                    <button type="submit" class="btn btn-primary" data-testid="uf-save">SIMPAN AKUN</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<div class="panel">
    <div class="panel-head"><h2>Manajemen Akun</h2></div>
    <div class="panel-body flush table-scroll">
        <table class="tbl" data-testid="user-table">
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Organisasi</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><b><?= e($u['name']) ?></b></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['role']) ?></td>
                    <td><?= e($u['organization_name'] ?? '-') ?></td>
                    <td><?= badge($u['status']) ?></td>
                    <td><a class="btn btn-sm" href="settings.php?edit_user=<?= (int)$u['id'] ?>" data-testid="user-edit-<?= (int)$u['id'] ?>">EDIT</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
