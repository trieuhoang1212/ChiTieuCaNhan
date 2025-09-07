<?php
// tietkiem.php - API để quản lý sổ tiết kiệm
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
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper: kiểm tra cột có tồn tại không để truy vấn linh hoạt (tự lấy tên DB hiện tại)
function hasColumn($conn, $table, $column) {
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db'] : '';
    if ($dbName === '') return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    if (!$stmt = $conn->prepare($sql)) {
        return false;
    }
    $stmt->bind_param("sss", $dbName, $table, $column);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

if ($method === 'GET') {
    // Lấy danh sách sổ tiết kiệm
    // Lưu ý: tên bảng trên host là 'sotietkiem' (chữ thường) -> dùng đúng tên để tránh lỗi case-sensitive
    $hasPayout = hasColumn($conn, 'sotietkiem', 'phuong_thuc_lai');
    $selectSql = $hasPayout
        ? "SELECT ma_so, ten_so, so_tien, ky_han, lai_suat, ngay_gui, ngay_dao_han, trang_thai, phuong_thuc_lai FROM sotietkiem ORDER BY ngay_gui DESC"
        : "SELECT ma_so, ten_so, so_tien, ky_han, lai_suat, ngay_gui, ngay_dao_han, trang_thai FROM sotietkiem ORDER BY ngay_gui DESC";
    $stmt = $conn->prepare($selectSql);
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
    $savings = [];
    while ($row = $res->fetch_assoc()) {
        $savings[] = [
            "id" => (int)$row["ma_so"],
            "savingName" => $row["ten_so"],
            "amount" => (int)$row["so_tien"],
            "term" => (int)$row["ky_han"],
            "interestRate" => (float)$row["lai_suat"],
            "startDate" => $row["ngay_gui"],
            "endDate" => $row["ngay_dao_han"],
            "status" => $row["trang_thai"],
            "payoutMethod" => isset($row["phuong_thuc_lai"]) ? $row["phuong_thuc_lai"] : "cuoi_ky",
        ];
    }
    $stmt->close();

    echo json_encode(["success" => true, "savings" => $savings]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    // Thêm sổ tiết kiệm mới - hỗ trợ JSON và x-www-form-urlencoded/multipart
    $data = null;
    $raw = file_get_contents("php://input");
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    // Ưu tiên JSON nếu Content-Type là application/json hoặc body có dấu hiệu JSON
    if (stripos($contentType, 'application/json') !== false || (strlen(trim($raw)) > 0 && (trim($raw)[0] === '{' || trim($raw)[0] === '['))) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // Nếu không phải JSON, thử lấy từ $_POST
    if (!is_array($data) || empty($data)) {
        if (!empty($_POST)) {
            $data = $_POST;
        }
    }

    if (!is_array($data) || empty($data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ. Gửi JSON (application/json) hoặc x-www-form-urlencoded."]);
        $conn->close();
        exit();
    }

    // Nhánh rút tiền một phần từ sổ
    if (isset($data['action']) && $data['action'] === 'withdraw') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $withdrawAmount = isset($data['withdrawAmount']) ? (int)$data['withdrawAmount'] : 0;
        if ($id <= 0 || $withdrawAmount <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Thiếu id hoặc số tiền rút không hợp lệ."]);
            $conn->close();
            exit();
        }

        // Lấy số tiền hiện tại
    $stmt = $conn->prepare("SELECT so_tien, ten_so FROM sotietkiem WHERE ma_so = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
            $conn->close();
            exit();
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Không tìm thấy sổ tiết kiệm."]);
            $conn->close();
            exit();
        }
        $row = $res->fetch_assoc();
        $stmt->close();

        $currentAmount = (int)$row['so_tien'];
        if ($withdrawAmount > $currentAmount) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Số tiền rút vượt quá số tiền trong sổ."]);
            $conn->close();
            exit();
        }

        $remaining = $currentAmount - $withdrawAmount;
        if ($remaining <= 0) {
            // Tất toán
            $upd = $conn->prepare("UPDATE sotietkiem SET so_tien = 0, trang_thai = 'tat_toan', ngay_dao_han = IF(ngay_dao_han IS NULL, CURDATE(), ngay_dao_han) WHERE ma_so = ?");
            if (!$upd) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
                $conn->close();
                exit();
            }
            $upd->bind_param("i", $id);
            if (!$upd->execute()) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Lỗi cập nhật sổ: " . $upd->error]);
                $upd->close();
                $conn->close();
                exit();
            }
            $upd->close();
        } else {
            $upd = $conn->prepare("UPDATE sotietkiem SET so_tien = ? WHERE ma_so = ?");
            if (!$upd) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
                $conn->close();
                exit();
            }
            $upd->bind_param("ii", $remaining, $id);
            if (!$upd->execute()) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Lỗi cập nhật sổ: " . $upd->error]);
                $upd->close();
                $conn->close();
                exit();
            }
            $upd->close();
        }

        echo json_encode(["success" => true, "message" => "Rút tiền thành công.", "remaining" => max(0, $remaining)]);
        $conn->close();
        exit();
    }

    // Nhánh tạo sổ mới
    $savingName = isset($data['savingName']) ? trim($data['savingName']) : '';
    $amount = isset($data['amount']) ? (int)$data['amount'] : 0;
    $term = isset($data['term']) ? (int)$data['term'] : 0;
    $interestRate = isset($data['interestRate']) ? (float)$data['interestRate'] : 0;
    // Không kỳ hạn: không cho 0% (áp dụng mức tối thiểu 0.3%)
    if ($term === 0 && $interestRate <= 0) {
        $interestRate = 0.3;
    }
    $startDate = isset($data['startDate']) && $data['startDate'] !== '' ? trim($data['startDate']) : date('Y-m-d');
    $endDate = $term > 0 ? date('Y-m-d', strtotime("+$term months", strtotime($startDate))) : null;
    $payoutMethod = isset($data['payoutMethod']) && in_array($data['payoutMethod'], ['cuoi_ky','hang_thang','hang_quy']) ? $data['payoutMethod'] : 'cuoi_ky';

    if ($amount <= 0 || $savingName === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Thông tin sổ tiết kiệm không hợp lệ."]);
        $conn->close();
        exit();
    }

    $hasPayout = hasColumn($conn, 'sotietkiem', 'phuong_thuc_lai');
    $sql = $hasPayout
        ? "INSERT INTO sotietkiem (ten_so, so_tien, ky_han, lai_suat, ngay_gui, ngay_dao_han, phuong_thuc_lai) VALUES (?, ?, ?, ?, ?, ?, ?)"
        : "INSERT INTO sotietkiem (ten_so, so_tien, ky_han, lai_suat, ngay_gui, ngay_dao_han) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }
    if ($hasPayout) {
        $stmt->bind_param("siidsss", $savingName, $amount, $term, $interestRate, $startDate, $endDate, $payoutMethod);
    } else {
        $stmt->bind_param("siidss", $savingName, $amount, $term, $interestRate, $startDate, $endDate);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi thêm sổ tiết kiệm: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $insertedId = $stmt->insert_id;
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Sổ tiết kiệm được thêm thành công.", "savingId" => $insertedId]);
    $conn->close();
    exit();
}

if ($method === 'DELETE') {
    // Xóa sổ tiết kiệm - hỗ trợ JSON và x-www-form-urlencoded
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['id'])) {
        // Thử parse body dạng x-www-form-urlencoded
        $tmp = [];
        parse_str($raw, $tmp);
        if (!empty($tmp)) {
            $data = $tmp;
        }
    }

    if (!is_array($data) || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ. Cần trường id."]);
        $conn->close();
        exit();
    }

    $id = (int)$data['id'];
    $stmt = $conn->prepare("DELETE FROM sotietkiem WHERE ma_so = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi xóa sổ tiết kiệm: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->close();
    echo json_encode(["success" => true, "message" => "Sổ tiết kiệm được xóa thành công."]);
    $conn->close();
    exit();
}

// Nếu method khác GET/POST/DELETE
http_response_code(405);
header("Allow: GET, POST, DELETE, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();