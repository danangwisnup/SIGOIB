<?php
// ============================================================
// FRONT CONTROLLER API — Sistem Monitoring IB & Quick Check
// Semua request /api/* diarahkan ke file ini (.htaccess / nginx).
// Alur: REQUEST -> ROUTE -> AUTH -> AUTHORIZATION -> CONTROLLER
//       -> SERVICE -> MODEL/DATABASE -> JSON
// ============================================================

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../services/SessionService.php';

foreach (glob(__DIR__ . '/../controllers/*.php') as $f) {
    require_once $f;
}

// Lifecycle session mengikuti waktu server di setiap request.
try {
    SessionService::refresh();
} catch (Throwable $e) {
    Response::error('Koneksi database gagal: ' . $e->getMessage(), 500);
}

$router = new Router();

// AUTH
$router->add('POST', '/api/auth/login', ['AuthController', 'login']);
$router->add('POST', '/api/auth/logout', ['AuthController', 'logout']);
$router->add('GET', '/api/auth/me', ['AuthController', 'me']);
$router->add('PUT', '/api/auth/password', ['AuthController', 'changePassword']);

// DEVICE (mobile, publik/token perangkat)
$router->add('POST', '/api/device/register', ['DevicePublicController', 'register']);
$router->add('GET', '/api/device/status', ['DevicePublicController', 'status']);
$router->add('POST', '/api/device/event', ['DevicePublicController', 'event']);

// PERSONNEL (web admin)
$router->add('GET', '/api/personnel', ['PersonnelController', 'index']);
$router->add('POST', '/api/personnel', ['PersonnelController', 'create']);
$router->add('GET', '/api/personnel/{id}', ['PersonnelController', 'show']);
$router->add('PUT', '/api/personnel/{id}', ['PersonnelController', 'update']);
$router->add('POST', '/api/personnel/import', ['PersonnelController', 'import']);

// DEVICES (web admin)
$router->add('GET', '/api/devices', ['DevicesController', 'index']);
$router->add('GET', '/api/devices/pending', ['DevicesController', 'pending']);
$router->add('POST', '/api/devices/{id}/approve', ['DevicesController', 'approve']);
$router->add('POST', '/api/devices/{id}/reject', ['DevicesController', 'reject']);
$router->add('POST', '/api/devices/{id}/revoke', ['DevicesController', 'revoke']);

// MONITORING
$router->add('GET', '/api/monitoring', ['MonitoringController', 'index']);
$router->add('POST', '/api/monitoring/ib', ['MonitoringController', 'createIb']);
$router->add('POST', '/api/monitoring/quick-check', ['MonitoringController', 'quickCheck']);
$router->add('GET', '/api/monitoring/{id}', ['MonitoringController', 'show']);
$router->add('PUT', '/api/monitoring/{id}', ['MonitoringController', 'update']);
$router->add('POST', '/api/monitoring/{id}/cancel', ['MonitoringController', 'cancel']);
$router->add('GET', '/api/monitoring/{id}/locations', ['MonitoringController', 'locations']);

// LOCATION (mobile)
$router->add('POST', '/api/location/sync', ['LocationController', 'sync']);

// GEOFENCE
$router->add('GET', '/api/geofences', ['GeofenceController', 'index']);
$router->add('POST', '/api/geofences', ['GeofenceController', 'create']);
$router->add('PUT', '/api/geofences/{id}', ['GeofenceController', 'update']);
$router->add('DELETE', '/api/geofences/{id}', ['GeofenceController', 'delete']);

// ALERT
$router->add('GET', '/api/alerts', ['AlertController', 'index']);
$router->add('PUT', '/api/alerts/{id}/status', ['AlertController', 'updateStatus']);

// DASHBOARD
$router->add('GET', '/api/dashboard', ['DashboardController', 'index']);
$router->add('GET', '/api/dashboard/locations', ['DashboardController', 'locations']);

// HISTORY
$router->add('GET', '/api/history/personnel/{id}', ['HistoryController', 'personnel']);

// REPORT
$router->add('GET', '/api/reports/monitoring/{id}', ['ReportController', 'monitoring']);

// ORGANIZATIONS (dropdown filter)
$router->add('GET', '/api/organizations', ['OrganizationController', 'index']);

// USERS + AUDIT (ADMIN)
$router->add('GET', '/api/users', ['UserController', 'index']);
$router->add('POST', '/api/users', ['UserController', 'create']);
$router->add('PUT', '/api/users/{id}', ['UserController', 'update']);
$router->add('GET', '/api/audit-logs', ['UserController', 'auditLogs']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    Response::error('Terjadi kesalahan server.', 500);
}
