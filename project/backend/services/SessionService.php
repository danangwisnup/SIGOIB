<?php
// Lifecycle session berdasarkan waktu server (dipanggil di setiap request API).

require_once __DIR__ . '/../models/MonitoringSession.php';

class SessionService
{
    public static function refresh(): void
    {
        MonitoringSession::refreshStatuses();
    }
}
