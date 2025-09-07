<?php
// kehoach.php - API CRUD cho kế hoạch tích luỹ
// Endpoints:
//   GET    /PHP/kehoach.php                     -> danh sách kế hoạch
//   POST   /PHP/kehoach.php                     -> tạo kế hoạch
//   PUT    /PHP/kehoach.php                     -> cập nhật kế hoạch
//   DELETE /PHP/kehoach.php?id=123&refund=1     -> xoá kế hoạch (tuỳ chọn hoàn trả số dư đã tích luỹ)

declare(strict_types=1);

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

function map_row_to_client(array $r): array {
	return [
		'id' => (int)$r['id'],
		'goal' => $r['ten_muc_tieu'],
		'target' => (int)$r['so_tien_muc_tieu'],
		'startDate' => $r['ngay_bat_dau'],
		'endDate' => $r['ngay_ket_thuc'],
		'days' => (int)$r['so_ngay'],
		'daily' => (int)$r['so_tien_trung_binh_ngay'],
		'saved' => (int)$r['so_tien_da_tich_luy'],
		'status' => $r['trang_thai'],
		'createdAt' => $r['ngay_tao'],
		'updatedAt' => $r['ngay_cap_nhat'],
	];
}

try {
	$pdo = db();
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	// Support method override via header or query string for environments that block PUT/DELETE
	$override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ($_GET['_method'] ?? '');
	if (is_string($override) && $override !== '') {
		$method = strtoupper($override);
	}

	if ($method === 'GET') {
		$stmt = $pdo->prepare('SELECT id, ten_muc_tieu, so_tien_muc_tieu, ngay_bat_dau, ngay_ket_thuc, so_ngay, so_tien_trung_binh_ngay, so_tien_da_tich_luy, trang_thai, ngay_tao, ngay_cap_nhat FROM tichluy ORDER BY id DESC');
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$data = array_map('map_row_to_client', $rows);
		json_ok($data);
		exit;
	}

	if ($method === 'POST') {
		$b = body_json();
		$goal = trim((string)($b['goal'] ?? ''));
		$target = (int)($b['target'] ?? 0);
		$startDate = (string)($b['startDate'] ?? ''); // YYYY-MM-DD
		$endDate = (string)($b['endDate'] ?? '');

		if ($goal === '' || $target <= 0 || $startDate === '' || $endDate === '') {
			json_err('INVALID_INPUT', 'Thiếu hoặc sai dữ liệu tạo kế hoạch', 400);
			exit;
		}

		$start = strtotime($startDate);
		$end = strtotime($endDate);
		$diffDays = (int)ceil(($end - $start) / 86400);
		if ($diffDays <= 0) {
			json_err('INVALID_DATE_RANGE', 'Ngày kết thúc phải sau ngày bắt đầu', 400);
			exit;
		}
		$daily = (int)ceil($target / $diffDays);

		$sql = 'INSERT INTO tichluy (ten_muc_tieu, so_tien_muc_tieu, ngay_bat_dau, ngay_ket_thuc, so_ngay, so_tien_trung_binh_ngay, so_tien_da_tich_luy, trang_thai) VALUES (?,?,?,?,?,?,?,?)';
		try {
			$pdo->prepare($sql)->execute([$goal, $target, $startDate, $endDate, $diffDays, $daily, 0, 'dang_tich_luy']);
		} catch (Throwable $ex) {
			// Fallback cho trường hợp cột id không có AUTO_INCREMENT và STRICT mode bật
			// SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default value
			if (strpos($ex->getMessage(), "Field 'id' doesn't have a default value") !== false || strpos($ex->getMessage(), '1364') !== false) {
				$nextId = (int)($pdo->query('SELECT IFNULL(MAX(id), 0) + 1 FROM tichluy')->fetchColumn() ?: 1);
				$sql2 = 'INSERT INTO tichluy (id, ten_muc_tieu, so_tien_muc_tieu, ngay_bat_dau, ngay_ket_thuc, so_ngay, so_tien_trung_binh_ngay, so_tien_da_tich_luy, trang_thai) VALUES (?,?,?,?,?,?,?,?,?)';
				$pdo->prepare($sql2)->execute([$nextId, $goal, $target, $startDate, $endDate, $diffDays, $daily, 0, 'dang_tich_luy']);
			} else { throw $ex; }
		}

		$id = (int)$pdo->lastInsertId();
		// Some MariaDB setups can return 0 if table doesn't have AUTO_INCREMENT correctly set
		if ($id <= 0) {
			$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE ten_muc_tieu = ? AND so_tien_muc_tieu = ? AND ngay_bat_dau = ? AND ngay_ket_thuc = ? ORDER BY id DESC LIMIT 1');
			$stmt->execute([$goal, $target, $startDate, $endDate]);
			$row = $stmt->fetch();
			if ($row) { $id = (int)$row['id']; }
		}
		$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		json_ok(map_row_to_client($row), 201);
		exit;
	}

	if ($method === 'PUT') {
		$b = body_json();
		$id = (int)($b['id'] ?? 0);
		$goal = trim((string)($b['goal'] ?? ''));
		$target = (int)($b['target'] ?? 0);
		$startDate = (string)($b['startDate'] ?? '');
		$endDate = (string)($b['endDate'] ?? '');
		if ($id <= 0 || $goal === '' || $target <= 0 || $startDate === '' || $endDate === '') {
			json_err('INVALID_INPUT', 'Thiếu hoặc sai dữ liệu cập nhật', 400);
			exit;
		}

		$start = strtotime($startDate);
		$end = strtotime($endDate);
		$diffDays = (int)ceil(($end - $start) / 86400);
		if ($diffDays <= 0) {
			json_err('INVALID_DATE_RANGE', 'Ngày kết thúc phải sau ngày bắt đầu', 400);
			exit;
		}
		$daily = (int)ceil($target / $diffDays);

		// Không reset số tiền đã tích luỹ khi sửa kế hoạch.
		$sql = 'UPDATE tichluy SET ten_muc_tieu = ?, so_tien_muc_tieu = ?, ngay_bat_dau = ?, ngay_ket_thuc = ?, so_ngay = ?, so_tien_trung_binh_ngay = ? WHERE id = ?';
		$pdo->prepare($sql)->execute([$goal, $target, $startDate, $endDate, $diffDays, $daily, $id]);

		$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		if (!$row) { json_err('NOT_FOUND', 'Không tìm thấy kế hoạch', 404); exit; }
		json_ok(map_row_to_client($row));
		exit;
	}

	if ($method === 'DELETE') {
		$id = 0; $provided = false;
		if (isset($_GET['id'])) { $id = (int)$_GET['id']; $provided = true; }
		else {
			$b = body_json();
			if (isset($b['id'])) { $id = (int)$b['id']; $provided = true; }
		}
		// If client didn't provide id at all, default to the latest plan
		if (!$provided) {
			$latestId = (int)($pdo->query('SELECT id FROM tichluy ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
			$id = $latestId;
			if ($id <= 0) { json_err('INVALID_ID', 'Thiếu id kế hoạch (không có kế hoạch nào để xoá)', 400); exit; }
		}

		$refund = isset($_GET['refund']) ? (int)$_GET['refund'] : 1; // 1 = hoàn trả số dư đã tích luỹ
		if ($refund !== 0 && $refund !== 1) { $refund = 1; }

		$pdo->beginTransaction();
		try {
			// Lấy kế hoạch
			$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE id = ? FOR UPDATE');
			$stmt->execute([$id]);
			$plan = $stmt->fetch();
			if (!$plan) { throw new Exception('NOT_FOUND'); }

			// Hoàn trả số dư nếu cần (chỉ hoàn khi kế hoạch chưa hoàn thành)
			if ($refund === 1 && (int)$plan['so_tien_da_tich_luy'] > 0 && $plan['trang_thai'] !== 'hoan_thanh') {
				$bal = (int)($pdo->query('SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
				$refundAmount = (int)$plan['so_tien_da_tich_luy'];
				$newBal = $bal + $refundAmount;
				$ngay = date('Y-m-d H:i:s');
				$moTa = 'Hoàn trả tích luỹ khi xoá kế hoạch #' . (int)$plan['id'] . ': ' . (string)$plan['ten_muc_tieu'];
				$ins = $pdo->prepare('INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, "Nạp", ?, ?, ?)');
				$ins->execute([$ngay, $moTa, $refundAmount, $newBal]);
			}

			// Xoá kế hoạch
			$del = $pdo->prepare('DELETE FROM tichluy WHERE id = ?');
			$del->execute([$id]);

			$pdo->commit();
			json_ok(['id' => $id]);
		} catch (Throwable $e) {
			$pdo->rollBack();
			if ($e->getMessage() === 'NOT_FOUND') {
				json_err('NOT_FOUND', 'Không tìm thấy kế hoạch', 404);
			} else {
				json_err('DELETE_FAILED', 'Không xoá được kế hoạch', 500, ['detail' => $e->getMessage()]);
			}
		}
		exit;
	}

	json_err('METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ', 405);
} catch (Throwable $e) {
	json_err('SERVER_ERROR', $e->getMessage(), 500);
}

?>