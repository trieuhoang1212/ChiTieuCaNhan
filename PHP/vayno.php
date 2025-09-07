<?php
// vayno.php - hỗ trợ GET, POST, DELETE
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Preflight
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
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Thiếu hàm kết nối DB."]);
        exit();
    }
}

// Hàm lấy số dư hiện tại (từ giao dịch mới nhất trong bảng naprut)
function getCurrentBalance($conn) {
    $sql = "SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['so_du_sau'];
    }
    return 0;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Kiểm tra bảng vayno có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'vayno'");
    if ($checkTable->num_rows === 0) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Bảng 'vayno' không tồn tại trong cơ sở dữ liệu."]);
        $conn->close();
        exit();
    }

    // Lấy danh sách khoản vay
    $stmt = $conn->prepare("SELECT id, ten_khoan_vay, so_tien, so_thang, ngay_bat_dau FROM vayno ORDER BY id DESC");
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
    $loans = [];
    while ($row = $res->fetch_assoc()) {
        $loans[] = [
            "id" => (int)$row["id"],
            "loanName" => $row["ten_khoan_vay"],
            "amount" => (int)$row["so_tien"],
            "months" => (int)$row["so_thang"],
            "startDate" => $row["ngay_bat_dau"]
        ];
    }
    $stmt->close();
    echo json_encode(["success" => true, "loans" => $loans]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    // Thêm khoản vay mới
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
        $conn->close();
        exit();
    }

    $loanName = isset($data['loanName']) ? trim($data['loanName']) : '';
    $amount = isset($data['amount']) ? (int)$data['amount'] : 0;
    $months = isset($data['months']) ? (int)$data['months'] : 0;
    $startDate = isset($data['startDate']) ? trim($data['startDate']) : date('Y-m-d');

    if ($amount <= 0 || $months <= 0 || empty($loanName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Thông tin khoản vay không hợp lệ."]);
        $conn->close();
        exit();
    }

    // Lấy số dư hiện tại
    $currentBalance = getCurrentBalance($conn);
    $newBalance = $currentBalance + $amount; // Cộng số tiền vay vào số dư

    // Tính tiền trả mỗi tháng theo quy tắc đơn giản
    $monthlyPayment = (int)ceil($amount / max(1, $months));

    // Thêm khoản vay vào bảng vayno (bao gồm cột NOT NULL tien_tra_moi_thang)
    $stmt = $conn->prepare("INSERT INTO vayno (ten_khoan_vay, so_tien, so_thang, tien_tra_moi_thang, ngay_bat_dau) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("siiis", $loanName, $amount, $months, $monthlyPayment, $startDate);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi thêm khoản vay: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $insertedId = $stmt->insert_id;
    $stmt->close();

    // Cập nhật số dư mới vào bảng naprut
    $ngay = date('Y-m-d H:i:s');
    $stmt2 = $conn->prepare("INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, 'Nạp', 'Cộng tiền vay', ?, ?)");
    $stmt2->bind_param("sii", $ngay, $amount, $newBalance);

    if (!$stmt2->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật số dư: " . $stmt2->error]);
        $stmt2->close();
        $conn->close();
        exit();
    }

    $stmt2->close();

    echo json_encode(["success" => true, "message" => "Khoản vay được thêm thành công.", "loanId" => $insertedId, "newBalance" => $newBalance]);
    $conn->close();
    exit();
}

if ($method === 'DELETE') {
    // Xóa khoản vay
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data) || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
        $conn->close();
        exit();
    }

    $id = (int)$data['id'];
    $stmt = $conn->prepare("DELETE FROM vayno WHERE id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi xóa khoản vay: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->close();
    echo json_encode(["success" => true, "message" => "Khoản vay được xóa thành công."]);
    $conn->close();
    exit();
}

// Nếu method khác GET/POST/DELETE
http_response_code(405);
header("Allow: GET, POST, DELETE, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();