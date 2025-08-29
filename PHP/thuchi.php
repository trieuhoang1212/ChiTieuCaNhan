<?php
// thuchi.php - API cho quản lý thu/chi (tương tự style tichluy.php)
// Endpoints:
//   GET  /PHP/thuchi.php?id=123        -> lấy 1 giao dịch (id)
//   GET  /PHP/thuchi.php                -> lấy danh sách giao dịch (mặc định 50 bản ghi mới nhất)
//   POST /PHP/thuchi.php                -> thêm giao dịch (body JSON)

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

// Nếu project đã có các helper (body_json, json_ok, json_err) thì không ghi đè
if (!function_exists('body_json')) {
	function body_json(): array {
		$raw = file_get_contents('php://input');
		if (!$raw) return [];
		$data = json_decode($raw, true);
		return is_array($data) ? $data : [];
	}
}
if (!function_exists('json_ok')) {
	function json_ok($data = null): void {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
	}
}
if (!function_exists('json_err')) {
	function json_err(string $code, string $message, int $http = 400): void {
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($http);
		echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
	}
}

function map_tx_to_client(array $r): array {
	return [
		'id' => (int)$r['id'],
		'loai' => $r['loai'], // 'Thu' | 'Chi'
		'so_tien' => (int)$r['so_tien'],
		'ngay' => $r['ngay'],
		'mo_ta' => $r['mo_ta'],
		'nguoi_giao_dich' => $r['fullname'] ?? null,
		'so_tai_khoan' => $r['account'] ?? null,
		' ngan_hang' => $r['bank'] ?? null,
		'nguoi_giao_dich_id' => isset($r['nguoi_giao_dich_id']) ? (int)$r['nguoi_giao_dich_id'] : null,
		'createdAt' => $r['ngay_tao'] ?? null,
	];
}

try {
	$pdo = db();
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id > 0) {
			$stmt = $pdo->prepare('SELECT g.*, d.ho_ten AS fullname, d.so_tai_khoan AS account, d.ngan_hang AS bank
				FROM GiaoDich g LEFT JOIN DanhBa d ON g.nguoi_giao_dich_id = d.id WHERE g.id = ?');
			$stmt->execute([$id]);
			$row = $stmt->fetch();
			if (!$row) { json_err('NOT_FOUND', 'Không tìm thấy giao dịch', 404); exit; }
			json_ok(map_tx_to_client($row));
			exit;
		}

		// lấy danh sách (mặc định 50 bản ghi)
		$limit = isset($_GET['limit']) ? min(200, (int)$_GET['limit']) : 50;
		$stmt = $pdo->prepare('SELECT g.*, d.ho_ten AS fullname, d.so_tai_khoan AS account, d.ngan_hang AS bank
			FROM GiaoDich g LEFT JOIN DanhBa d ON g.nguoi_giao_dich_id = d.id
			ORDER BY g.ngay DESC, g.id DESC LIMIT ?');
		$stmt->bindValue(1, $limit, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$out = array_map('map_tx_to_client', $rows);
		json_ok(['transactions' => $out]);
		exit;
	}

	if ($method === 'POST') {
		$b = body_json();
		$loai = isset($b['loai']) && $b['loai'] === 'Chi' ? 'Chi' : 'Thu';
		$so_tien = isset($b['so_tien']) ? (int)$b['so_tien'] : 0;
		$ngay = isset($b['ngay']) ? $b['ngay'] : date('Y-m-d H:i:s');
		$mo_ta = isset($b['mo_ta']) ? trim($b['mo_ta']) : null;
		$contact_id = isset($b['contact_id']) ? (int)$b['contact_id'] : 0;
		$fullname = isset($b['fullname']) ? trim($b['fullname']) : '';
		$account = isset($b['account']) ? trim($b['account']) : '';
		$bank = isset($b['bank']) ? trim($b['bank']) : '';
		$saveContact = !empty($b['saveContact']);

		if ($so_tien <= 0) { json_err('INVALID_AMOUNT', 'Số tiền không hợp lệ', 400); exit; }

		// Lấy số dư hiện tại từ bảng naprut (dòng mới nhất)
		$bal = (int)($pdo->query('SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
		if ($loai === 'Chi' && $so_tien > $bal) { json_err('INSUFFICIENT_BALANCE', 'Số dư không đủ', 400); exit; }

		$pdo->beginTransaction();
		try {
			// lưu danh bạ nếu cần và chưa có contact_id
			if ($contact_id <= 0 && $saveContact && $fullname !== '' && $account !== '' && $bank !== '') {
				$ins = $pdo->prepare('INSERT INTO DanhBa (ho_ten, so_tai_khoan, ngan_hang) VALUES (?, ?, ?)');
				$ins->execute([$fullname, $account, $bank]);
				$contact_id = (int)$pdo->lastInsertId();
			}

			// Lưu giao dịch vào GiaoDich
			$insGd = $pdo->prepare('INSERT INTO GiaoDich (loai, so_tien, ngay, mo_ta, nguoi_giao_dich_id) VALUES (?, ?, ?, ?, ?)');
			$insGd->execute([$loai, $so_tien, $ngay, $mo_ta, $contact_id > 0 ? $contact_id : null]);
			$txId = (int)$pdo->lastInsertId();

			// Cập nhật naprut: ghi 1 dòng Nạp/Rút và so_du_sau mới
			if ($loai === 'Thu') {
				$newBal = $bal + $so_tien;
				$loaiText = 'Nạp';
			} else {
				$newBal = $bal - $so_tien;
				$loaiText = 'Rút';
			}
			$ngayNow = date('Y-m-d H:i:s');
			$moTaNaprut = ($mo_ta ? $mo_ta . ' - ' : '') . ($fullname ?: ($contact_id ? 'Người trong danh bạ' : 'Không rõ'));
			$insNap = $pdo->prepare('INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)');
			$insNap->execute([$ngayNow, $loaiText, $moTaNaprut, $so_tien, $newBal]);

			$pdo->commit();

			// Trả về dữ liệu giao dịch mới
			$stmt = $pdo->prepare('SELECT g.*, d.ho_ten AS fullname, d.so_tai_khoan AS account, d.ngan_hang AS bank
				FROM GiaoDich g LEFT JOIN DanhBa d ON g.nguoi_giao_dich_id = d.id WHERE g.id = ?');
			$stmt->execute([$txId]);
			$row = $stmt->fetch();
			json_ok(['transaction' => map_tx_to_client($row), 'currentBalance' => $newBal]);
			exit;
		} catch (Throwable $e) {
			$pdo->rollBack();
			json_err('ADD_FAILED', $e->getMessage(), 500);
		}
	}

	json_err('METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ', 405);
} catch (Throwable $e) {
	json_err('SERVER_ERROR', $e->getMessage(), 500);
}

?>