<?php
/**
 * thuchi.php - API quản lý thu/chi (bảng thuchi)
 *
 * GET:
 *   /thuchi.php              -> { success, data: { transactions: [...], currentBalance? } }
 *   /thuchi.php?id=123       -> { success, data: { id, ... } }
 * POST (JSON or form):
 *   body: { loai:'Thu'|'Chi' | type:'income'|'expense', so_tien|amount, ngay|date, mo_ta|note,
 *           fullname, account, bank, saveContact }
 *   -> tạo giao dịch; nếu có bảng naprut sẽ ghi sổ để đồng bộ số dư
 * DELETE:
 *   /thuchi.php?id=123       -> xoá giao dịch (và hoàn trả sổ naprut nếu có)
 * POST?action=deleteTransaction (JSON {id}) -> xoá giao dịch
 */

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

// ----- Helpers (trả JSON theo format success/data) -----
function respond_ok($data = [], int $code = 200): void {
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}
function respond_err(string $error, string $message = '', int $code = 400, array $extra = []): void {
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	$p = ['success' => false, 'error' => $error];
	if ($message !== '') $p['message'] = $message;
	if ($extra) $p['extra'] = $extra;
	echo json_encode($p, JSON_UNESCAPED_UNICODE);
}
function body_json_assoc(): array {
	$raw = file_get_contents('php://input');
	if (!$raw) return [];
	$j = json_decode($raw, true);
	return is_array($j) ? $j : [];
}

// ----- Balance helpers using bảng `naprut` -----
function naprut_exists(PDO $pdo): bool {
	try {
		$pdo->query('SELECT 1 FROM `naprut` LIMIT 1');
		return true;
	} catch (Throwable $e) { return false; }
}
function get_current_balance(PDO $pdo): int {
	try {
		$stmt = $pdo->query('SELECT so_du_sau FROM `naprut` ORDER BY id DESC LIMIT 1');
		$v = $stmt->fetchColumn();
		return $v !== false ? (int)$v : 0;
	} catch (Throwable $e) { return 0; }
}

function map_tx(array $r): array {
	return [
		'id' => (int)$r['id'],
		'loai' => $r['loai'],
		'type' => ($r['loai'] === 'Thu') ? 'income' : 'expense',
		'so_tien' => (int)$r['so_tien'],
		'amount' => (int)$r['so_tien'],
		'ngay' => $r['ngay'],
		'date' => $r['ngay'],
		'mo_ta' => $r['mo_ta'] ?? null,
		'note' => $r['mo_ta'] ?? null,
	];
}

try {
	$pdo = db();
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id > 0) {
			$st = $pdo->prepare('SELECT t.*, d.ho_ten, d.so_tai_khoan, d.ngan_hang
								 FROM thuchi t
								 LEFT JOIN danhba d ON t.nguoi_giao_dich_id = d.id
								 WHERE t.id = ?');
			$st->execute([$id]);
			$row = $st->fetch(PDO::FETCH_ASSOC);
			if (!$row) { respond_err('NOT_FOUND', 'Không tìm thấy giao dịch', 404); exit; }
			respond_ok(map_tx($row));
			exit;
		}

		$limit = isset($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 200;
		$st = $pdo->prepare('SELECT t.*, d.ho_ten, d.so_tai_khoan, d.ngan_hang
							  FROM thuchi t
							  LEFT JOIN danhba d ON t.nguoi_giao_dich_id = d.id
							  ORDER BY t.ngay DESC, t.id DESC
							  LIMIT ?');
		$st->bindValue(1, $limit, PDO::PARAM_INT);
		$st->execute();
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$txs = array_map('map_tx', $rows);
		$currentBalance = naprut_exists($pdo) ? get_current_balance($pdo) : null;
		respond_ok(['transactions' => $txs, 'currentBalance' => $currentBalance]);
		exit;
	}

	if ($method === 'POST' && !(isset($_GET['action']) && $_GET['action'] === 'deleteTransaction')) {
		// Add transaction
		$data = body_json_assoc();
		if (empty($data) && !empty($_POST)) $data = $_POST;

		$loai = $data['loai'] ?? null;
		if (!$loai && isset($data['type'])) {
			$loai = ($data['type'] === 'expense') ? 'Chi' : 'Thu';
		}
		$loai = ($loai === 'Chi') ? 'Chi' : 'Thu';

		$so_tien = isset($data['so_tien']) ? (int)$data['so_tien'] : 0;
		if ($so_tien <= 0 && isset($data['amount'])) $so_tien = (int)$data['amount'];
		if ($so_tien <= 0) { respond_err('INVALID_AMOUNT', 'Số tiền không hợp lệ', 400); exit; }

		$ngay = $data['ngay'] ?? ($data['date'] ?? '');
		// Chuẩn hoá về YYYY-MM-DD cho cột DATE
		if ($ngay === '' || strlen($ngay) < 10) $ngay = date('Y-m-d');
		else $ngay = substr($ngay, 0, 10);

		$mo_ta = trim((string)($data['mo_ta'] ?? ($data['note'] ?? '')));
		$fullname = trim((string)($data['fullname'] ?? ''));
		$account = trim((string)($data['account'] ?? ''));
		$bank = trim((string)($data['bank'] ?? ''));
		$saveContact = !empty($data['saveContact']);

		$pdo->beginTransaction();
		try {
			$contact_id = 0;
			if ($saveContact && $fullname !== '' && $account !== '' && $bank !== '') {
				// Kiểm tra tồn tại
				$chk = $pdo->prepare('SELECT id FROM danhba WHERE so_tai_khoan = ? AND ngan_hang = ? LIMIT 1');
				$chk->execute([$account, $bank]);
				$ex = $chk->fetchColumn();
				if ($ex) $contact_id = (int)$ex; else {
					$insC = $pdo->prepare('INSERT INTO danhba (ho_ten, so_tai_khoan, ngan_hang) VALUES (?, ?, ?)');
					$insC->execute([$fullname, $account, $bank]);
					$contact_id = (int)$pdo->lastInsertId();
				}
			}

			// Nếu có bảng naprut thì kiểm tra số dư khi chi
			if (naprut_exists($pdo) && $loai === 'Chi') {
				$bal = get_current_balance($pdo);
				if ($so_tien > $bal) { throw new RuntimeException('INSUFFICIENT_BALANCE'); }
			}

			// Thêm giao dịch
			$ins = $pdo->prepare('INSERT INTO thuchi (loai, so_tien, ngay, mo_ta, nguoi_giao_dich_id) VALUES (?, ?, ?, ?, ?)');
			$ins->execute([$loai, $so_tien, $ngay, $mo_ta !== '' ? $mo_ta : null, $contact_id > 0 ? $contact_id : null]);
			$txId = (int)$pdo->lastInsertId();

			// Ghi sổ naprut (nếu có) để cập nhật số dư
			$newBal = null;
			if (naprut_exists($pdo)) {
				$bal = get_current_balance($pdo);
				$newBal = ($loai === 'Thu') ? ($bal + $so_tien) : ($bal - $so_tien);
				$loaiN = ($loai === 'Thu') ? 'Nạp' : 'Rút';
				$desc = ($mo_ta !== '' ? ($mo_ta . ' - ') : '') . ($fullname !== '' ? $fullname : '');
				$now = date('Y-m-d H:i:s');
				$insN = $pdo->prepare('INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)');
				$insN->execute([$now, $loaiN, $desc, $so_tien, $newBal]);
			}

			$pdo->commit();

			// Trả lại giao dịch vừa thêm (có join danh bạ)
			$st = $pdo->prepare('SELECT t.*, d.ho_ten, d.so_tai_khoan, d.ngan_hang
								  FROM thuchi t LEFT JOIN danhba d ON t.nguoi_giao_dich_id = d.id WHERE t.id = ?');
			$st->execute([$txId]);
			$row = $st->fetch(PDO::FETCH_ASSOC);
			respond_ok(['transaction' => map_tx($row), 'currentBalance' => $newBal]);
			exit;
		} catch (Throwable $e) {
			$pdo->rollBack();
			if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
				respond_err('INSUFFICIENT_BALANCE', 'Số dư không đủ', 400);
			} else {
				respond_err('ADD_FAILED', $e->getMessage(), 500);
			}
			exit;
		}
	}

	if ($method === 'DELETE' || ($method === 'POST' && (isset($_GET['action']) && $_GET['action'] === 'deleteTransaction'))) {
		// Xoá giao dịch
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id <= 0) {
			$b = body_json_assoc();
			if (!empty($b['id'])) $id = (int)$b['id'];
		}
		if ($id <= 0) { respond_err('INVALID_ID', 'ID không hợp lệ', 400); exit; }

		$pdo->beginTransaction();
		try {
			$st = $pdo->prepare('SELECT * FROM thuchi WHERE id = ? FOR UPDATE');
			$st->execute([$id]);
			$tx = $st->fetch(PDO::FETCH_ASSOC);
			if (!$tx) { throw new RuntimeException('NOT_FOUND'); }

			$newBal = null;
			if (naprut_exists($pdo)) {
				$bal = get_current_balance($pdo);
				$amount = (int)$tx['so_tien'];
				// Hoàn trả: nếu xoá Thu -> Rút; nếu xoá Chi -> Nạp
				$loaiN = ($tx['loai'] === 'Thu') ? 'Rút' : 'Nạp';
				$newBal = ($loaiN === 'Nạp') ? ($bal + $amount) : ($bal - $amount);
				$now = date('Y-m-d H:i:s');
				$desc = 'Hoàn trả do xoá giao dịch #' . $id . ($tx['mo_ta'] ? (': ' . $tx['mo_ta']) : '');
				$insN = $pdo->prepare('INSERT INTO naprut (ngay, loai, mo_ta, so_tien, so_du_sau) VALUES (?, ?, ?, ?, ?)');
				$insN->execute([$now, $loaiN, $desc, $amount, $newBal]);
			}

			$del = $pdo->prepare('DELETE FROM thuchi WHERE id = ?');
			$del->execute([$id]);

			$pdo->commit();
			respond_ok(['deleted' => $id, 'currentBalance' => $newBal]);
			exit;
		} catch (Throwable $e) {
			$pdo->rollBack();
			if ($e->getMessage() === 'NOT_FOUND') respond_err('NOT_FOUND', 'Không tìm thấy giao dịch', 404);
			else respond_err('DELETE_FAILED', $e->getMessage(), 500);
			exit;
		}
	}

	respond_err('METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ', 405);
} catch (Throwable $e) {
	respond_err('SERVER_ERROR', $e->getMessage(), 500);
}

?>