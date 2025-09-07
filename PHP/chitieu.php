<?php
// filepath: c:\xampp\htdocs\ChiTieuCaNhan\PHP\chitieu.php
// Bootstrap API giống naprut.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}
// Hàm đọc dữ liệu JSON hoặc form (an toàn nếu đã khai báo ở nơi khác)
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
// Bảo đảm có kết nối DB
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/db.php';
    if (function_exists('get_db_connection')) {
        $conn = get_db_connection();
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Lấy danh sách chi tiêu
    $stmt = $conn->prepare("SELECT id, ngay, danh_muc, so_tien FROM chitieu ORDER BY id DESC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi SQL: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $expenses = [];
    while ($row = $res->fetch_assoc()) {
        $expenses[] = $row;
    }
    $stmt->close();
    echo json_encode(["success" => true, "expenses" => $expenses]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $danh_muc = $data['danh_muc'] ?? '';
    $so_tien = isset($data['so_tien']) ? (int)$data['so_tien'] : 0;
    $ngay = date('Y-m-d H:i:s');
    $month = date('Y-m');

    if (empty($danh_muc) || $so_tien <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
        $conn->close();
        exit();
    }

    // Lấy giới hạn tháng hiện tại (nếu có)
    $stmtLimit = $conn->prepare("SELECT id, so_tien FROM gioihan WHERE thang_nam=? ORDER BY id DESC LIMIT 1");
    $stmtLimit->bind_param("s", $month);
    $stmtLimit->execute();
    $resLimit = $stmtLimit->get_result();
    $rowLimit = $resLimit->fetch_assoc();
    $stmtLimit->close();

    if ($rowLimit) {
        // Có thiết lập giới hạn: kiểm tra và trừ
        if ($rowLimit['so_tien'] < $so_tien) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Vượt quá giới hạn tháng!"]);
            $conn->close();
            exit();
        }
        $newLimit = $rowLimit['so_tien'] - $so_tien;
        $stmtUpdateLimit = $conn->prepare("UPDATE gioihan SET so_tien=? WHERE id=?");
        $stmtUpdateLimit->bind_param("ii", $newLimit, $rowLimit['id']);
        $stmtUpdateLimit->execute();
        $stmtUpdateLimit->close();
    }

    // Thêm khoản chi vào chitieu
    $stmt = $conn->prepare("INSERT INTO chitieu (ngay, danh_muc, so_tien) VALUES (?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi SQL: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("ssi", $ngay, $danh_muc, $so_tien);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Thêm khoản chi thành công."]);
    $conn->close();
    exit();
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Thiếu id khoản chi"]);
        $conn->close();
        exit();
    }

    // Lấy khoản chi cần xoá
    $stmt = $conn->prepare("SELECT so_tien, ngay FROM chitieu WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Không tìm thấy khoản chi"]);
        $conn->close();
        exit();
    }

    $so_tien = (int)$row['so_tien'];
    $month = substr($row['ngay'], 0, 7); // Lấy tháng từ ngày khoản chi

    // Hoàn lại vào giới hạn tháng
    $stmtLimit = $conn->prepare("SELECT id, so_tien FROM gioihan WHERE thang_nam=? ORDER BY id DESC LIMIT 1");
    $stmtLimit->bind_param("s", $month);
    $stmtLimit->execute();
    $resLimit = $stmtLimit->get_result();
    $rowLimit = $resLimit->fetch_assoc();
    $stmtLimit->close();

    if ($rowLimit) {
        $newLimit = $rowLimit['so_tien'] + $so_tien;
        $stmtUpdateLimit = $conn->prepare("UPDATE gioihan SET so_tien=? WHERE id=?");
        $stmtUpdateLimit->bind_param("ii", $newLimit, $rowLimit['id']);
        $stmtUpdateLimit->execute();
        $stmtUpdateLimit->close();
    }

    // Xoá khoản chi
    $stmtDel = $conn->prepare("DELETE FROM chitieu WHERE id=?");
    $stmtDel->bind_param("i", $id);
    $stmtDel->execute();
    $stmtDel->close();

    echo json_encode(["success" => true, "message" => "Đã xoá khoản chi và hoàn lại giới hạn"]);
    $conn->close();
    exit();
}

// Phương thức không được hỗ trợ
http_response_code(405);
header("Allow: GET, POST, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();