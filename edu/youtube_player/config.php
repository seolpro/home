<?php
// ================================
// YouTube 교육영상 재생관 설정파일
// ================================

// 1) DB 접속정보를 Cafe24 환경에 맞게 수정하세요.
define('DB_HOST', 'localhost');
define('DB_NAME', 'seolhopro');
define('DB_USER', 'seolhopro');
define('DB_PASS', 'ajou2130--');
define('DB_CHARSET', 'utf8mb4');

// 2) 관리자 비밀번호를 반드시 변경하세요.
define('ADMIN_PASSWORD', '2130');

// 3) 설치 폴더가 도메인 루트가 아닌 경우 자동으로 계산됩니다.
// 예: https://도메인.com/youtube_player/index.php

session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_admin(): void {
    if (empty($_SESSION['yt_admin'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    $posted = (string)($_POST['csrf'] ?? '');
    $saved = (string)($_SESSION['csrf'] ?? '');
    if (!$posted || !$saved || !hash_equals($saved, $posted)) {
        http_response_code(400);
        exit('잘못된 요청입니다. CSRF 오류');
    }
}

function get_setting(string $key, string $default = ''): string {
    try {
        $stmt = db()->prepare('SELECT setting_value FROM yt_settings WHERE setting_key=? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_value'] : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function set_setting(string $key, string $value): void {
    $stmt = db()->prepare(
        'INSERT INTO yt_settings (setting_key, setting_value) VALUES (?, ?)\n'
        .'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // admin 폴더에서 호출되면 상위 폴더로 보정
    $dir = preg_replace('~/admin$~', '', $dir);
    return $scheme.'://'.$host.($dir ? $dir : '');
}

function extract_youtube_id(string $url): ?string {
    $url = trim($url);
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) return $url;
    if (preg_match('~youtu\.be/([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    if (preg_match('~[?&]v=([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    if (preg_match('~/embed/([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    if (preg_match('~/shorts/([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return null;
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('~[^a-z0-9_-]+~', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'video-'.date('YmdHis');
}
