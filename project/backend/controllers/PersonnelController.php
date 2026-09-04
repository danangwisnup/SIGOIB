<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../services/ImportService.php';
require_once __DIR__ . '/../services/AuditService.php';

class PersonnelController
{
    public static function index(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $page = max(1, (int)Request::query('page', 1));
        $perPage = min(100, max(5, (int)Request::query('per_page', 20)));
        $filters = [
            'q' => Request::query('q'),
            'company_id' => Request::query('company_id'),
            'platoon_id' => Request::query('platoon_id'),
            'status' => Request::query('status'),
        ];
        Response::success(Personnel::search($scope, $filters, $page, $perPage));
    }

    public static function show(array $params): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $p = Personnel::find((int)$params['id']);
        if (!$p || !Scope::canAccessPersonnel($scope, $p)) {
            Response::error('Personel tidak ditemukan.', 404);
        }
        Response::success(['personnel' => $p]);
    }

    public static function create(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN', 'KOMANDAN', 'WADAN']);
        $b = Request::body();
        if (empty($b['nrp']) || empty($b['name'])) {
            Response::error('NRP dan Nama wajib diisi.', 422);
        }
        if (Personnel::findByNrp(trim($b['nrp']))) {
            Response::error('NRP sudah terdaftar.', 422);
        }
        $id = Personnel::create([
            'nrp' => trim($b['nrp']),
            'name' => trim($b['name']),
            'rank' => $b['rank'] ?? null,
            'position' => $b['position'] ?? null,
            'company_id' => $b['company_id'] ?? null,
            'platoon_id' => $b['platoon_id'] ?? null,
            'photo' => $b['photo'] ?? null,
        ]);
        AuditService::log((int)$user['id'], 'create_personnel', 'personnel', $id, 'Tambah personel ' . $b['nrp']);
        Response::success(['id' => $id], 201);
    }

    public static function update(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN', 'KOMANDAN', 'WADAN']);
        $p = Personnel::find((int)$params['id']);
        if (!$p) {
            Response::error('Personel tidak ditemukan.', 404);
        }
        $b = Request::body();
        if (empty($b['nrp']) || empty($b['name'])) {
            Response::error('NRP dan Nama wajib diisi.', 422);
        }
        $existing = Personnel::findByNrp(trim($b['nrp']));
        if ($existing && (int)$existing['id'] !== (int)$params['id']) {
            Response::error('NRP sudah digunakan personel lain.', 422);
        }
        Personnel::update((int)$params['id'], [
            'nrp' => trim($b['nrp']),
            'name' => trim($b['name']),
            'rank' => $b['rank'] ?? null,
            'position' => $b['position'] ?? null,
            'company_id' => $b['company_id'] ?? null,
            'platoon_id' => $b['platoon_id'] ?? null,
            'photo' => $b['photo'] ?? ($p['photo'] ?? null),
            'status' => $b['status'] ?? $p['status'],
        ]);
        AuditService::log((int)$user['id'], 'edit_personnel', 'personnel', $params['id'], 'Edit personel ' . $b['nrp']);
        Response::success(['message' => 'Personel diperbarui.']);
    }

    // POST /api/personnel/import (multipart, field: file, mode: preview|commit)
    public static function import(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN', 'KOMANDAN', 'WADAN']);

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('File CSV wajib diupload.', 422);
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Response::error('Format file harus CSV. Dari Excel gunakan "Save As -> CSV".', 422);
        }

        $parsed = ImportService::parseAndValidate($_FILES['file']['tmp_name']);
        if (!empty($parsed['fatal'])) {
            Response::error($parsed['fatal'], 422);
        }
        $rows = $parsed['rows'];
        $valid = count(array_filter($rows, fn($r) => !$r['errors']));

        $mode = $_POST['mode'] ?? 'preview';
        if ($mode === 'preview') {
            Response::success([
                'mode' => 'preview',
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => count($rows) - $valid,
                'rows' => $rows,
            ]);
        }

        $result = ImportService::commit($rows);
        AuditService::log((int)$user['id'], 'import_personnel', 'personnel', null,
            "Import personel: {$result['imported']} masuk, {$result['skipped']} dilewati");
        Response::success([
            'mode' => 'commit',
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'rows' => array_values(array_filter($rows, fn($r) => $r['errors'])),
        ]);
    }
}
