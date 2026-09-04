<?php
// Koneksi PDO (prepared statements di semua query).

require_once __DIR__ . '/env.php';

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME');
            $user = env('DB_USER');
            $pass = env('DB_PASS');
            if (!$name || !$user) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Konfigurasi database belum lengkap (.env).']);
                exit;
            }
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Selaraskan timezone MySQL dengan APP_TIMEZONE PHP agar NOW()
            // konsisten untuk expiry token, throttle alert, dan status online.
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }
}
