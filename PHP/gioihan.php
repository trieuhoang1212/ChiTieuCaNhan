<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
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

$method = $_SERVER['REQUEST_METHOD'];
$data = read_request_data();
$month = $_GET['thang_nam'] ?? $data['thang_nam'] ?? date('Y-m');

// Hàm cập nhật số dư (ghi vào naprut)
function updateBalance($conn, $amount, $action = 'subtract') {
    // Lấy số dư hiện tại
    $res = $conn->query("SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $current = $row ? (int)$row['so_du_sau'] : 0;

    // Nếu trừ mà không đủ số dư thì không thực hiện
    if ($action === 'subtract' && $amount > $current) {
        return false;
    }

    $new = ($action === 'subtract') ? $current - $amount : $current + $amount;
    $ngay = date('Y-m-d H:i:s');
    // Sử dụng enum hợp lệ của bảng `naprut`: 'Nạp' hoặc 'Rút'
    $loai = ($action === 'subtract') ? 'Rút' : 'Nạp';
    $mo_ta = ($action === 'subtract') ? 'Đặt giới hạn tháng' : 'Bỏ giới hạn tháng';

    $stmt = $conn->prepare("INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $ngay, $loai, $mo_ta, $amount, $new);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT so_tien FROM gioihan WHERE thang_nam=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    echo json_encode([
        "success" => true,
        "limit" => $row ? (int)$row['so_tien'] : 0
    ]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    $so_tien = $data['so_tien'] ?? 0;
    if ($so_tien <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ"]);
        $conn->close();
        exit();
    }
    // Trừ số dư (ghi vào naprut)
    $ok = updateBalance($conn, $so_tien, 'subtract');
    if ($ok === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Số dư không đủ để đặt giới hạn"]);
        $conn->close();
        exit();
    }
    // Lưu giới hạn
    $stmt = $conn->prepare("SELECT id FROM gioihan WHERE thang_nam=?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt2 = $conn->prepare("UPDATE gioihan SET so_tien=? WHERE id=?");
        $stmt2->bind_param("ii", $so_tien, $row['id']);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt2 = $conn->prepare("INSERT INTO gioihan (so_tien, thang_nam) VALUES (?, ?)");
        $stmt2->bind_param("is", $so_tien, $month);
        $stmt2->execute();
        $stmt2->close();
    }
    $stmt->close();
    echo json_encode(["success" => true, "message" => "Giới hạn đã lưu và số dư đã trừ"]);
    $conn->close();
    exit();
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare("SELECT id, so_tien FROM gioihan WHERE thang_nam=? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row && $row['so_tien'] > 0) {
        // Hoàn tiền lại số dư (ghi vào naprut)
        updateBalance($conn, $row['so_tien'], 'add');
        // Xoá giới hạn
        $stmt2 = $conn->prepare("UPDATE gioihan SET so_tien=0 WHERE id=?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $stmt2->close();
        echo json_encode(["success" => true, "message" => "Đã bỏ giới hạn và hoàn tiền"]);
    } else {
        echo json_encode(["success" => false, "message" => "Không có giới hạn để bỏ"]);
    }
    $conn->close();
    exit();
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Phương thức không hỗ trợ"]);
$conn->close();
exit();