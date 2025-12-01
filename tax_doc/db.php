<?php
// db.php (UTF-8, BOM 없음)

// 🔧 Cafe24 DB 정보에 맞게 수정하세요
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'seolhopro');       // 실제 DB명으로 수정
define('DB_USER', 'seolhopro');       // DB 계정
define('DB_PASS', 'ajou2130--');       // DB 비밀번호

define('TIMEZONE', 'Asia/Seoul');
date_default_timezone_set(TIMEZONE);

// 📂 업로드 폴더 (상대경로)
define('BASE_UPLOAD_DIR', __DIR__ . '/uploads');
define('TAX_UPLOAD_DIR',  BASE_UPLOAD_DIR . '/tax');
define('CARD_UPLOAD_DIR', BASE_UPLOAD_DIR . '/card');

// 🔌 PDO 연결 함수
function getDB() {
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

// 📁 업로드 폴더 자동 생성
function ensureUploadDirs() {
    $dirs = [BASE_UPLOAD_DIR, TAX_UPLOAD_DIR, CARD_UPLOAD_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// 🔐 파일명 안전하게 생성
function makeSafeFilename(string $originalName): string {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $ext = $ext ? ('.' . strtolower($ext)) : '';
    return date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $ext;
}

// ✅ 허용할 MIME 타입 (이미지 + PDF)
function isAllowedMime(string $mime): bool {
    $allowed = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf'
    ];
    return in_array($mime, $allowed, true);
}
