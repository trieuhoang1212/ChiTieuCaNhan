<?php
// Simple PDO connection helper for XAMPP (MySQL on localhost)
// Adjust DB credentials if your local setup differs.

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Production credentials (match mysqli helper below)
    $host = 'localhost';
    $dbname = 'sql_nhom11_itimi';
    $user = 'sql_nhom11_itimi';
    $pass = '32dc07642ece1';
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
<?php
// Compatibility helper for scripts using mysqli-based connection (e.g., chitieu.php)
if (!function_exists('get_db_connection')) {
    function get_db_connection(): mysqli {
        $servername = 'localhost';
        $username   = 'sql_nhom11_itimi';
        $password   = '32dc07642ece1';
        $dbname     = 'sql_nhom11_itimi';

        $conn = @new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kết nối DB thất bại: ' . $conn->connect_error
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        // Use utf8mb4 for full Unicode support
        if (method_exists($conn, 'set_charset')) {
            $conn->set_charset('utf8mb4');
        }
        return $conn;
    }
}