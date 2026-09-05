<?php
// PHP API client (cURL) ke API existing. Token disimpan server-side di session.
require_once __DIR__ . '/config.php';

function api_call(string $method, string $path, ?array $body = null, array $opts = []): array
{
    $ch = curl_init(web2_api_base() . $path);
    $headers = ['Accept: application/json'];
    if (!empty($_SESSION['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['api_token'];
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if (!empty($opts['multipart'])) {
        $options[CURLOPT_POSTFIELDS] = $opts['multipart'];
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $status === 0) {
        return ['ok' => false, 'status' => 0, 'data' => null, 'message' => 'Tidak dapat mengambil data dari server.'];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'message' => 'Response server tidak valid.'];
    }
    if ($status === 401 && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'login.php') {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Sesi berakhir. Silakan login kembali.'];
        header('Location: login.php');
        exit;
    }
    return [
        'ok' => !empty($json['success']),
        'status' => $status,
        'data' => $json['data'] ?? null,
        'message' => $json['message'] ?? '',
    ];
}

function api_get(string $path): array { return api_call('GET', $path); }
function api_post(string $path, array $body = []): array { return api_call('POST', $path, $body); }
function api_put(string $path, array $body = []): array { return api_call('PUT', $path, $body); }
function api_delete(string $path): array { return api_call('DELETE', $path); }

function api_stream_csv(string $path, string $filename): void
{
    $ch = curl_init(web2_api_base() . $path);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . ($_SESSION['api_token'] ?? '')],
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) { echo $chunk; return strlen($chunk); },
    ]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    curl_exec($ch);
    curl_close($ch);
    exit;
}
