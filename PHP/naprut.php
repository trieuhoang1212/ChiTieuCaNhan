<?php
// naprut.php - hỗ trợ GET + POST (JSON hoặc x-www-form-urlencoded)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

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

// Hàm lấy số dư hiện tại (từ giao dịch mới nhất)
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
    // Nếu có query ?balanceOnly=1, chỉ trả về số dư hiện tại
    $balanceOnly = isset($_GET['balanceOnly']) ? (int)$_GET['balanceOnly'] : 0;
    if ($balanceOnly === 1) {
        $bal = getCurrentBalance($conn);
        echo json_encode(["success" => true, "currentBalance" => $bal]);
        $conn->close();
        exit();
    }

    $limit = 50;
    $stmt = $conn->prepare("SELECT id, ngay, loai, mo_ta, so_tien, so_du_sau FROM naprut ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi truy vấn: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }
    $res = $stmt->get_result();
    $transactions = [];
    while ($row = $res->fetch_assoc()) {
        $transactions[] = [
            "id" => (int)$row["id"],
            "ngay" => $row["ngay"],
            "loai" => $row["loai"],
            "mo_ta" => $row["mo_ta"],
            "so_tien" => (int)$row["so_tien"],
            "so_du_sau" => (int)$row["so_du_sau"]
        ];
    }
    $stmt->close();
    echo json_encode(["success" => true, "transactions" => $transactions]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    // --- HỖ TRỢ CẢ JSON VÀ x-www-form-urlencoded ---
    $data = null;
    $raw = file_get_contents("php://input");
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    // Nếu Content-Type là JSON hoặc body bắt đầu bằng { hoặc [ -> thử decode JSON
    if (stripos($contentType, 'application/json') !== false || strlen(trim($raw)) > 0 && (trim($raw)[0] === '{' || trim($raw)[0] === '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

            // Nếu không phải JSON, thử dùng $_POST (x-www-form-urlencoded hoặc form-data)
            if (!is_array($data) || empty($data)) {
                if (!empty($_POST)) {
                    $data = $_POST;
                }
            }

            if (!is_array($data) || empty($data)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Dữ liệu không hợp lệ. Gửi JSON (application/json) hoặc form-data (x-www-form-urlencoded)."
                ]);
                $conn->close();
                exit();
            }    // Lấy giá trị (dùng cả trường từ JSON hoặc form)
    $loai = isset($data['loai']) ? trim($data['loai']) : '';
    $so_tien = isset($data['so_tien']) ? (int)$data['so_tien'] : 0;
    $mo_ta = isset($data['mo_ta']) ? trim($data['mo_ta']) : '';

    // Validate
    if ($loai !== 'Nạp' && $loai !== 'Rút') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Trường 'loai' phải là 'Nạp' hoặc 'Rút'."]);
        $conn->close();
        exit();
    }
    if ($so_tien <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Trường 'so_tien' phải lớn hơn 0."]);
        $conn->close();
        exit();
    }

    // Lấy số dư hiện tại và kiểm tra khi rút
    $currentBalance = getCurrentBalance($conn);
    if ($loai === 'Rút' && $so_tien > $currentBalance) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Số dư không đủ để rút."]);
        $conn->close();
        exit();
    }

    // Tính số dư mới
    $newBalance = ($loai === 'Nạp') ? ($currentBalance + $so_tien) : ($currentBalance - $so_tien);
    $ngay = date('Y-m-d H:i:s');

    $insertSql = "INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("sssii", $ngay, $loai, $mo_ta, $so_tien, $newBalance);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi lưu giao dịch: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $insertedId = $stmt->insert_id;
    $stmt->close();

    // Lấy lại bản ghi vừa insert
    $stmt2 = $conn->prepare("SELECT id, ngay, loai, mo_ta, so_tien, so_du_sau FROM naprut WHERE id = ?");
    $stmt2->bind_param("i", $insertedId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $row = $res2->fetch_assoc();
    $stmt2->close();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Giao dịch được lưu thành công.",
        "transaction" => [
            "id" => (int)$row["id"],
            "ngay" => $row["ngay"],
            "loai" => $row["loai"],
            "mo_ta" => $row["mo_ta"],
            "so_tien" => (int)$row["so_tien"],
            "so_du_sau" => (int)$row["so_du_sau"]
        ],
        "currentBalance" => $newBalance
    ]);

    $conn->close();
    exit();
}

// Nếu method khác GET/POST
http_response_code(405);
header("Allow: GET, POST, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();