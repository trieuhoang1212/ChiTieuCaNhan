<?php
// tichluy.php - API cho đóng góp tích luỹ và xem 1 kế hoạch
// Endpoints:
//   GET  /PHP/tichluy.php?id=123                   -> lấy chi tiết 1 kế hoạch
//   POST /PHP/tichluy.php (id, amount)             -> đóng góp tiền vào kế hoạch, trừ số dư sodu

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

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

	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id <= 0) {
			$latestId = (int)($pdo->query('SELECT id FROM tichluy ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
			$id = $latestId; // chấp nhận id=0 nếu tồn tại
		}
		$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE id = ?');
		$stmt->execute([$id]);
		$row = $stmt->fetch();
		if (!$row) { json_err('NOT_FOUND', 'Không tìm thấy kế hoạch', 404); exit; }
		json_ok(map_row_to_client($row));
		exit;
	}

	if ($method === 'POST') {
		$b = body_json();
		$id = (int)($b['id'] ?? 0);
		if ($id <= 0) {
			$latestId = (int)($pdo->query('SELECT id FROM tichluy ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
			$id = $latestId;
		}
	$amount = (int)($b['amount'] ?? 0);
	if ($amount <= 0) { json_err('INVALID_AMOUNT', 'Số tiền không hợp lệ', 400); exit; }
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT * FROM tichluy WHERE id = ? FOR UPDATE');
			$stmt->execute([$id]);
			$plan = $stmt->fetch();
			if (!$plan) { throw new Exception('NOT_FOUND'); }

			$saved = (int)$plan['so_tien_da_tich_luy'];
			$target = (int)$plan['so_tien_muc_tieu'];
			$remain = $target - $saved;
			if ($remain <= 0) { throw new Exception('ALREADY_DONE'); }
			if ($amount > $remain) { throw new Exception('AMOUNT_EXCEED'); }

			// Lấy số dư hiện tại từ bảng naprut (dòng mới nhất)
			$bal = (int)($pdo->query('SELECT so_du_sau FROM naprut ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
			if ($amount > $bal) { throw new Exception('INSUFFICIENT_BALANCE'); }

			$newBal = $bal - $amount;
			// Ghi nhận giao dịch rút vào bảng naprut
			$ngay = date('Y-m-d H:i:s');
			$moTa = 'Đóng góp tích luỹ: ' . (string)$plan['ten_muc_tieu'];
			$insNaprut = $pdo->prepare('INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, "Rút", ?, ?, ?)');
			$insNaprut->execute([$ngay, $moTa, $amount, $newBal]);

			$newSaved = $saved + $amount;
			$status = $newSaved >= $target ? 'hoan_thanh' : 'dang_tich_luy';
			$upd = $pdo->prepare('UPDATE tichluy SET so_tien_da_tich_luy = ?, trang_thai = ? WHERE id = ?');
			$upd->execute([$newSaved, $status, $id]);

			$stmt2 = $pdo->prepare('SELECT * FROM tichluy WHERE id = ?');
			$stmt2->execute([$id]);
			$row = $stmt2->fetch();

			$pdo->commit();
			json_ok(['plan' => map_row_to_client($row), 'currentBalance' => $newBal]);
		} catch (Throwable $e) {
			$pdo->rollBack();
			$code = 400; $msg = $e->getMessage(); $err = 'CONTRIBUTE_FAILED';
			if ($msg === 'NOT_FOUND') { $err = 'NOT_FOUND'; $msg = 'Không tìm thấy kế hoạch'; $code = 404; }
			if ($msg === 'ALREADY_DONE') { $err = 'ALREADY_DONE'; $msg = 'Kế hoạch đã hoàn thành'; }
			if ($msg === 'AMOUNT_EXCEED') { $err = 'AMOUNT_EXCEED'; $msg = 'Số tiền vượt quá số còn thiếu'; }
			if ($msg === 'INSUFFICIENT_BALANCE') { $err = 'INSUFFICIENT_BALANCE'; $msg = 'Số dư không đủ'; }
			json_err($err, $msg, $code);
		}
		exit;
	}

	json_err('METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ', 405);
} catch (Throwable $e) {
	json_err('SERVER_ERROR', $e->getMessage(), 500);
}

?>