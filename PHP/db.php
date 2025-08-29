<?php
// Simple PDO connection helper for XAMPP (MySQL on localhost)
// Adjust DB credentials if your local setup differs.

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $dbname = 'quanlychitieu'; // Change if your database name is different
    $user = 'root';            // Default XAMPP user
    $pass = '';                // Default XAMPP has empty password
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'DB_CONNECT_FAILED', 'message' => $e->getMessage()]);
        exit;
    }
    return $pdo;
}

function json_ok($data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}

function json_err(string $error, string $message = '', int $code = 400, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['ok' => false, 'error' => $error];
    if ($message !== '') $payload['message'] = $message;
    if ($extra) $payload['extra'] = $extra;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function body_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

?>
