<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function post(string $k, $d='') { return $_POST[$k] ?? $d; }
function getv(string $k, $d='') { return $_GET[$k] ?? $d; }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function redirect(string $url): void {
    $base = defined('BASE_URL') && BASE_URL !== '' ? rtrim(BASE_URL, '/') . '/' : '';
    if (preg_match('~^https?://~i', $url) || str_starts_with($url, '/')) {
        header('Location: ' . $url);
    } else {
        header('Location: ' . $base . $url);
    }
    exit;
}
function set_flash(string $type, string $message): void { $_SESSION['flash'] = compact('type','message'); }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $t = post('csrf_token');
    if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(400); exit('잘못된 요청입니다.');
    }
}
function is_logged_in(): bool { return !empty($_SESSION['traffic_claim_logged_in']); }
function require_login(): void { if (!is_logged_in()) redirect('login.php'); }
function app_url(string $path=''): string {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}
function format_money($v): string { return number_format((float)$v) . '원'; }
function allowed_extensions(): array {
    return ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','hwp','hwpx','txt','zip'];
}
function image_extensions(): array { return ['jpg','jpeg','png','gif','webp']; }
function document_type_options(): array {
    return [
        'medical_receipt' => '진료비 영수증',
        'diagnosis' => '진단서/소견서',
        'pharmacy' => '약제비 증빙',
        'transport' => '교통비 증빙',
        'repair' => '수리비/견적서',
        'loss_income' => '휴업손해 자료',
        'insurance' => '보험사 문서',
        'agreement' => '합의 관련 문서',
        'photo' => '사고/치료 사진',
        'etc' => '기타',
    ];
}
function expense_type_options(): array {
    return [
        'medical' => '병원비', 'pharmacy' => '약제비', 'transport' => '교통비',
        'repair' => '수리비', 'care' => '간병비', 'misc' => '기타'
    ];
}
function case_status_options(): array {
    return [
        'collecting' => '자료 수집중',
        'negotiating' => '보험사 협의중',
        'reviewing' => '합의 검토중',
        'settled' => '합의 완료',
        'closed' => '종결',
    ];
}
function offer_stage_options(): array {
    return [
        'insurer_offer' => '보험사 제안',
        'counter_offer' => '내 제시금/반제안',
        'phone_call' => '통화/협의 내용',
        'agreement_done' => '합의 확정',
        'etc' => '기타',
    ];
}
function page_header(string $title): void {
    $flash = get_flash();
    require __DIR__ . '/partials_header.php';
}
function page_footer(): void { require __DIR__ . '/partials_footer.php'; }
function ensure_upload_dirs(): void {
    $dirs = [UPLOAD_DIR, UPLOAD_DIR . '/documents', UPLOAD_DIR . '/photos'];
    foreach ($dirs as $dir) if (!is_dir($dir)) @mkdir($dir, 0777, true);
}
function upload_single_file(array $file, string $subdir='documents'): array {
    ensure_upload_dirs();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('파일 업로드 오류');
    if (($file['size'] ?? 0) <= 0) throw new RuntimeException('빈 파일 업로드 불가');
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) throw new RuntimeException('파일 용량 초과');
    $original = $file['name'] ?? 'file';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, allowed_extensions(), true)) throw new RuntimeException('허용되지 않는 파일 형식');
    $folder = in_array($ext, image_extensions(), true) ? 'photos' : $subdir;
    $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = rtrim(UPLOAD_DIR, '/') . '/' . $folder . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('파일 저장 실패');
    return [
        'original_name' => $original,
        'stored_name' => $stored,
        'mime_type' => $file['type'] ?? 'application/octet-stream',
        'file_ext' => $ext,
        'file_size' => (int)($file['size'] ?? 0),
        'file_path' => $target,
        'storage_group' => $folder,
        'is_image' => in_array($ext, image_extensions(), true) ? 1 : 0,
    ];
}
function normalize_files_array(array $files): array {
    $normalized = [];
    if (!isset($files['name']) || !is_array($files['name'])) return $normalized;
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $normalized;
}
function fetch_case(int $id): ?array {
    $st = get_pdo()->prepare('SELECT * FROM accident_cases WHERE id=?'); $st->execute([$id]); return $st->fetch() ?: null;
}
function ensure_case_exists(int $id): array {
    $case = fetch_case($id); if (!$case) { http_response_code(404); exit('사건을 찾을 수 없습니다.'); } return $case;
}
function expense_total_for_case(int $caseId): float {
    $st = get_pdo()->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE case_id=?'); $st->execute([$caseId]); return (float)$st->fetchColumn();
}
function document_total_for_case(int $caseId): int {
    $st = get_pdo()->prepare('SELECT COUNT(*) FROM case_documents WHERE case_id=?'); $st->execute([$caseId]); return (int)$st->fetchColumn();
}
function latest_offer_amount(int $caseId): ?int {
    $st = get_pdo()->prepare("SELECT amount FROM negotiations WHERE case_id=? AND amount IS NOT NULL ORDER BY event_date DESC, id DESC LIMIT 1");
    $st->execute([$caseId]); $v = $st->fetchColumn(); return $v === false ? null : (int)$v;
}
function case_summary_counts(int $caseId): array {
    $pdo = get_pdo();
    $counts = [];
    foreach ([
        'expenses' => 'SELECT COUNT(*) FROM expenses WHERE case_id=?',
        'documents' => 'SELECT COUNT(*) FROM case_documents WHERE case_id=?',
        'images' => 'SELECT COUNT(*) FROM case_documents WHERE case_id=? AND is_image=1',
        'negotiations' => 'SELECT COUNT(*) FROM negotiations WHERE case_id=?',
    ] as $k => $sql) { $st = $pdo->prepare($sql); $st->execute([$caseId]); $counts[$k] = (int)$st->fetchColumn(); }
    return $counts;
}
function delete_physical_file(?string $path): void {
    if ($path && is_file($path)) @unlink($path);
}
function db_has_column(string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $st = get_pdo()->query('SHOW COLUMNS FROM `' . str_replace('`','',$table) . '`');
    $cols = array_column($st->fetchAll(), 'Field');
    return $cache[$key] = in_array($column, $cols, true);
}
