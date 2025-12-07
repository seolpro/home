<?php
// /www/coupon_reminder/db.php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST .
           ';port=' . DB_PORT .
           ';dbname=' . DB_NAME .
           ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // 500 에러 원인 확인용 (테스트 끝나면 주석 처리해도 됨)
        echo 'DB 연결 오류: ' . $e->getMessage();
        exit;
    }

    return $pdo;
}
