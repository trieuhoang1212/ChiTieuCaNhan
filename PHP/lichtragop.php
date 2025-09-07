<?php
// lichtragop.php - API để lấy danh sách lịch trả góp
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

if (!function_exists('read_request_data')) {
    function read_request_data(): array {
        $raw = file_get_contents("php://input");
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = null;
        if (stripos($contentType, 'application/json') !== false || (strlen(trim($raw)) > 0 && in_array(trim($raw)[0], ['{','[']))) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
        if (!is_array($data) || empty($data)) {
            if (!empty($_POST)) $data = $_POST;
        }
        return is_array($data) ? $data : [];
    }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/db.php';
    if (function_exists('get_db_connection')) {
        $conn = get_db_connection();
    }
}

// Kết nối DB
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "quanlychitieu";

// Kết nối DB
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Kết nối DB thất bại: " . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Kiểm tra bảng lichtragop có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'lichtragop'");
    if ($checkTable->num_rows === 0) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Bảng 'lichtragop' không tồn tại trong cơ sở dữ liệu."]);
        $conn->close();
        exit();
    }

    // Lấy danh sách lịch trả góp
    $stmt = $conn->prepare("SELECT id, vayno_id, ky_thu, ngay_den_han, so_tien_tra, da_tra, ngay_tra_thuc_te FROM lichtragop ORDER BY ngay_den_han ASC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi thực thi truy vấn: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $res = $stmt->get_result();
    $schedules = [];
    while ($row = $res->fetch_assoc()) {
        $schedules[] = [
            "id" => (int)$row["id"],
            "loanId" => (int)$row["vayno_id"],
            "installment" => (int)$row["ky_thu"],
            "dueDate" => $row["ngay_den_han"],
            "amount" => (int)$row["so_tien_tra"],
            "paid" => (bool)$row["da_tra"],
            "actualPaymentDate" => $row["ngay_tra_thuc_te"]
        ];
    }
    $stmt->close();

    echo json_encode(["success" => true, "schedules" => $schedules]);
    $conn->close();
    exit();
}

// Nếu method khác GET
http_response_code(405);
header("Allow: GET, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();