<?php
// Standar response JSON sesuai API standard.

class Response
{
    public static function success($data = [], int $code = 200): void
    {
        self::json(['success' => true, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400): void
    {
        self::json(['success' => false, 'message' => $message], $code);
    }

    public static function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
