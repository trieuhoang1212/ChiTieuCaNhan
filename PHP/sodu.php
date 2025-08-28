<?php
// sodu.php - API quản lý số dư (GET list, POST thêm)
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
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Trả về tất cả bản ghi (giới hạn 100) và currentBalance (bản ghi mới nhất)
    $limit = 100;
    $stmt = $conn->prepare("SELECT id, so_tien, ngay FROM sodu ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi truy vấn: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }
    $res = $stmt->get_result();
    $balances = [];
    while ($row = $res->fetch_assoc()) {
        $balances[] = [
            "id" => (int)$row["id"],
            "so_tien" => (int)$row["so_tien"],
            "ngay" => $row["ngay"]
        ];
    }
    $stmt->close();

    // currentBalance
    $currentBalance = 0;
    if (!empty($balances)) {
        $currentBalance = $balances[0]["so_tien"];
    }

    echo json_encode(["success" => true, "currentBalance" => $currentBalance, "balances" => $balances]);
    $conn->close();
    exit();
}

if ($method === 'POST') {
    // Hỗ trợ JSON và x-www-form-urlencoded
    $data = null;
    $raw = file_get_contents("php://input");
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (stripos($contentType, 'application/json') !== false || (strlen(trim($raw)) > 0 && (trim($raw)[0] === '{' || trim($raw)[0] === '['))) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $data = $decoded;
    }

    if ((!is_array($data) || empty($data)) && !empty($_POST)) {
        $data = $_POST;
    }

    if (!is_array($data) || !isset($data['so_tien'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Vui lòng gửi trường 'so_tien' (JSON hoặc x-www-form-urlencoded)."]);
        $conn->close();
        exit();
    }

    $so_tien = (int)$data['so_tien'];

    // Insert
    $stmt = $conn->prepare("INSERT INTO sodu (so_tien) VALUES (?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị truy vấn: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("i", $so_tien);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi khi lưu số dư: " . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $insertedId = $stmt->insert_id;
    $stmt->close();

    // Lấy lại bản ghi vừa insert để trả về
    $stmt2 = $conn->prepare("SELECT id, so_tien, ngay FROM sodu WHERE id = ?");
    $stmt2->bind_param("i", $insertedId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $row = $res2->fetch_assoc();
    $stmt2->close();

    http_response_code(201);
    echo json_encode(["success" => true, "message" => "Số dư được lưu.", "balance" => ["id" => (int)$row['id'], "so_tien" => (int)$row['so_tien'], "ngay" => $row['ngay']]]);
    $conn->close();
    exit();
}

// Nếu phương thức khác
http_response_code(405);
header("Allow: GET, POST, OPTIONS");
echo json_encode(["success" => false, "message" => "Phương thức không được hỗ trợ."]);
$conn->close();
exit();
?>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const currentBalanceElement = document.getElementById("currentBalance");
    const balanceHistoryElement = document.getElementById("balanceHistory");

    // Hàm lấy số dư từ API
    async function fetchBalance() {
      try {
        const response = await fetch("PHP/sodu.php");
        const data = await response.json();

        if (data.success) {
          // Hiển thị số dư hiện tại
          currentBalanceElement.textContent = data.currentBalance.toLocaleString() + " VNĐ";

          // Hiển thị lịch sử số dư
          balanceHistoryElement.innerHTML = "";
          data.balances.forEach((balance) => {
            const row = document.createElement("tr");
            row.innerHTML = `
              <td>${balance.id}</td>
              <td>${balance.so_tien.toLocaleString()} VNĐ</td>
              <td>${balance.ngay}</td>
            `;
            balanceHistoryElement.appendChild(row);
          });
        } else {
          alert("Không thể tải dữ liệu số dư.");
        }
      } catch (error) {
        console.error("Lỗi khi gọi API:", error);
        alert("Đã xảy ra lỗi khi tải số dư.");
      }
    }

    // Gọi API khi tải trang
    fetchBalance();
  });
</script>