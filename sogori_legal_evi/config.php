<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');

const APP_TITLE = '소고리 350·334번지 경계·유수 현장증거 정리';
// 반드시 변경하세요. 가능하면 영문/숫자/특수문자를 섞은 긴 비밀번호를 사용하세요.
const ADMIN_PASSWORD = '2130';
const MAX_UPLOAD_MB = 20;
const DATA_FILE = __DIR__ . '/data/evidence.json';
const UPLOAD_DIR = __DIR__ . '/uploads';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ensure_dirs(): void {
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    if (!is_dir(dirname(DATA_FILE))) @mkdir(dirname(DATA_FILE), 0755, true);
    if (!file_exists(DATA_FILE)) file_put_contents(DATA_FILE, json_encode(['items'=>[]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
}
function load_data(): array {
    ensure_dirs();
    $raw = @file_get_contents(DATA_FILE);
    $d = $raw ? json_decode($raw, true) : null;
    return is_array($d) ? $d : ['items'=>[]];
}
function save_data(array $d): void {
    ensure_dirs();
    $tmp = DATA_FILE.'.tmp';
    file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, DATA_FILE);
}
function csrf(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) { http_response_code(400); exit('잘못된 요청입니다.'); }
}
function admin_required(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['legal_admin'])) { header('Location: login.php'); exit; }
}
