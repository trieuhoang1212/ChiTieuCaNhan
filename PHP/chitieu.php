<?php
// filepath: c:\xampp\htdocs\ChiTieuCaNhan\PHP\chitieu.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

// Kết nối cơ sở dữ liệu
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quanlychitieu";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Kết nối DB thất bại: " . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8");

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
    $so_tien = $data['so_tien'] ?? 0;
    $ngay = date('Y-m-d H:i:s');

    if (empty($danh_muc) || $so_tien <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
        $conn->close();
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO chitieu (ngay, danh_muc, so_tien) VALUES (?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi SQL: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("ssi", $ngay, $danh_muc, $so_tien);
    $stmt->execute();
    echo json_encode(["success" => true, "message" => "Thêm khoản chi thành công."]);
    $stmt->close();
    $conn->close();
    exit();
}


// Phương thức không được hỗ trợ
http_response_code(405);
header("Allow: GET, POST, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();