<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "quanlychitieu";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Kết nối DB thất bại"]);
    exit();
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$month = $_GET['thang_nam'] ?? $data['thang_nam'] ?? date('Y-m');

// Hàm cập nhật số dư (ghi vào naprut)
function updateBalance($conn, $amount, $action = 'subtract') {
    $res = $conn->query("SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $current = $row ? (int)$row['so_du_sau'] : 0;
    $new = ($action === 'subtract') ? $current - $amount : $current + $amount;
    $ngay = date('Y-m-d H:i:s');
    $loai = ($action === 'subtract') ? 'Giới hạn' : 'Hoàn giới hạn';
    $mo_ta = ($action === 'subtract') ? 'Đặt giới hạn tháng' : 'Bỏ giới hạn tháng';
    $stmt = $conn->prepare("INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $ngay, $loai, $mo_ta, $amount, $new);
    $stmt->execute();
    $stmt->close();
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
    updateBalance($conn, $so_tien, 'subtract');
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