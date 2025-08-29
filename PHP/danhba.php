<?php
/**
 * danhba.php - API quản lý danh bạ (bảng danhba)
 *
 * GET:
 *   /danhba.php            -> { success, data: { contacts: [...] } }
 *   /danhba.php?id=123     -> { success, data: { id, fullname, ... } }
 * POST (JSON or form):
 *   body: { fullname, account, bank } -> tạo mới (nếu trùng account+bank thì trả về id cũ)
 * PUT (JSON):
 *   body: { id, fullname?, account?, bank? } -> cập nhật
 * DELETE:
 *   /danhba.php?id=123     -> xoá
 */

declare(strict_types=1);

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php';

// JSON helpers (success/data format)
function ok($data = [], int $code = 200): void {
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}
function err(string $error, string $message = '', int $code = 400, array $extra = []): void {
	http_response_code($code);
	header('Content-Type: application/json; charset=utf-8');
	$p = ['success' => false, 'error' => $error];
	if ($message !== '') $p['message'] = $message;
	if ($extra) $p['extra'] = $extra;
	echo json_encode($p, JSON_UNESCAPED_UNICODE);
}
function read_json(): array {
	$raw = file_get_contents('php://input');
	if (!$raw) return [];
	$j = json_decode($raw, true);
	return is_array($j) ? $j : [];
}

function validate_contact_input(array $c): array {
	$fullname = trim((string)($c['fullname'] ?? ''));
	$account = trim((string)($c['account'] ?? ''));
	$bank = trim((string)($c['bank'] ?? ''));

	if ($fullname === '' || $account === '' || $bank === '') {
		throw new InvalidArgumentException('Vui lòng cung cấp fullname, account, bank');
	}
	if (mb_strlen($fullname) > 200) throw new InvalidArgumentException('fullname quá dài');
	if (mb_strlen($account) > 100) throw new InvalidArgumentException('account quá dài');
	if (mb_strlen($bank) > 100) throw new InvalidArgumentException('bank quá dài');

	return [$fullname, $account, $bank];
}

try {
	$pdo = db();
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	if ($method === 'GET') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id > 0) {
			$st = $pdo->prepare('SELECT id, ho_ten AS fullname, so_tai_khoan AS account, ngan_hang AS bank, ngay_tao FROM danhba WHERE id = ?');
			$st->execute([$id]);
			$row = $st->fetch(PDO::FETCH_ASSOC);
			if (!$row) { err('NOT_FOUND', 'Không tìm thấy contact', 404); exit; }
			ok($row); exit;
		}

		$limit = isset($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 200;
		$st = $pdo->prepare('SELECT id, ho_ten AS fullname, so_tai_khoan AS account, ngan_hang AS bank, ngay_tao FROM danhba ORDER BY ho_ten ASC LIMIT ?');
		$st->bindValue(1, $limit, PDO::PARAM_INT);
		$st->execute();
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		ok(['contacts' => $rows]);
		exit;
	}

	if ($method === 'POST') {
		$data = read_json();
		if (!$data && !empty($_POST)) $data = $_POST;
		try {
			list($fullname, $account, $bank) = validate_contact_input($data);
		} catch (InvalidArgumentException $ex) {
			err('INVALID_INPUT', $ex->getMessage(), 400); exit;
		}

		// Nếu đã tồn tại account+bank thì trả về id cũ
		$chk = $pdo->prepare('SELECT id FROM danhba WHERE so_tai_khoan = ? AND ngan_hang = ? LIMIT 1');
		$chk->execute([$account, $bank]);
		$exist = $chk->fetchColumn();
		if ($exist) { ok(['message' => 'Contact đã tồn tại', 'contact_id' => (int)$exist]); exit; }

		$ins = $pdo->prepare('INSERT INTO danhba (ho_ten, so_tai_khoan, ngan_hang) VALUES (?, ?, ?)');
		$ins->execute([$fullname, $account, $bank]);
		$id = (int)$pdo->lastInsertId();
		$st = $pdo->prepare('SELECT id, ho_ten AS fullname, so_tai_khoan AS account, ngan_hang AS bank, ngay_tao FROM danhba WHERE id = ?');
		$st->execute([$id]);
		$row = $st->fetch(PDO::FETCH_ASSOC);
		ok(['contact' => $row]);
		exit;
	}

	if ($method === 'PUT') {
		$data = read_json();
		$id = isset($data['id']) ? (int)$data['id'] : 0;
		if ($id <= 0) { err('INVALID_ID', 'ID không hợp lệ', 400); exit; }

		$st0 = $pdo->prepare('SELECT * FROM danhba WHERE id = ?');
		$st0->execute([$id]);
		$orig = $st0->fetch(PDO::FETCH_ASSOC);
		if (!$orig) { err('NOT_FOUND', 'Không tìm thấy contact', 404); exit; }

		$fullname = trim((string)($data['fullname'] ?? $orig['ho_ten']));
		$account = trim((string)($data['account'] ?? $orig['so_tai_khoan']));
		$bank = trim((string)($data['bank'] ?? $orig['ngan_hang']));
		if ($fullname === '' || $account === '' || $bank === '') { err('INVALID_INPUT', 'fullname/account/bank không được rỗng', 400); exit; }

		$upd = $pdo->prepare('UPDATE danhba SET ho_ten = ?, so_tai_khoan = ?, ngan_hang = ? WHERE id = ?');
		$upd->execute([$fullname, $account, $bank, $id]);
		$st = $pdo->prepare('SELECT id, ho_ten AS fullname, so_tai_khoan AS account, ngan_hang AS bank, ngay_tao FROM danhba WHERE id = ?');
		$st->execute([$id]);
		$row = $st->fetch(PDO::FETCH_ASSOC);
		ok(['contact' => $row]);
		exit;
	}

	if ($method === 'DELETE') {
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($id <= 0) { err('INVALID_ID', 'ID không hợp lệ', 400); exit; }
		$del = $pdo->prepare('DELETE FROM danhba WHERE id = ?');
		$del->execute([$id]);
		ok(['deleted' => $id]);
		exit;
	}

	err('METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ', 405);
} catch (Throwable $e) {
	err('SERVER_ERROR', $e->getMessage(), 500);
}

?>

