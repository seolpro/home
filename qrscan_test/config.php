<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Seoul');

/*
 * =========================================================
 * QR 참석체크 테스트 - 공통 설정
 * =========================================================
 *
 * 서버 구조
 *
 * /www/
 * ├─ private_config/
 * │  └─ db_common.php
 * │
 * └─ qrscan_test/
 *    ├─ config.php
 *    ├─ install.php
 *    └─ ...
 *
 * db_common.php 반환 형식:
 * [
 *   'host'    => '...',
 *   'name'    => '...',
 *   'user'    => '...',
 *   'pass'    => '...',
 *   'charset' => 'utf8mb4'
 * ]
 */


/* =========================================================
 * 1. 공통 DB 설정 불러오기
 * ========================================================= */

$dbConfigFile = dirname(__DIR__) . '/private_config/db_common.php';

if (!is_file($dbConfigFile)) {
    http_response_code(500);
    exit(
        'DB 설정 파일을 찾을 수 없습니다.<br>' .
        '확인 경로: ' .
        htmlspecialchars($dbConfigFile, ENT_QUOTES, 'UTF-8')
    );
}

$db = require $dbConfigFile;

if (!is_array($db)) {
    http_response_code(500);
    exit('db_common.php 설정값이 올바른 배열 형식이 아닙니다.');
}


/* =========================================================
 * 2. 필수 DB 설정 확인
 * ========================================================= */

$requiredDbKeys = ['host', 'name', 'user', 'pass'];

foreach ($requiredDbKeys as $key) {
    if (!array_key_exists($key, $db)) {
        http_response_code(500);
        exit(
            'DB 설정값이 누락되었습니다: ' .
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
        );
    }
}


/* =========================================================
 * 3. PDO DB 연결
 * ========================================================= */

function db(): PDO
{
    static $pdo = null;

    global $db;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn =
        'mysql:host=' . $db['host'] .
        ';dbname=' . $db['name'] .
        ';charset=' . $charset;

    try {

        $pdo = new PDO(
            $dsn,
            $db['user'],
            $db['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

    } catch (PDOException $e) {

        http_response_code(500);

        exit(
            'DB 연결에 실패했습니다.<br><br>' .
            '오류: ' .
            htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        );
    }

    return $pdo;
}


/* =========================================================
 * 4. HTML 출력용
 * ========================================================= */

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
 * 5. 현재 웹앱 기본 URL
 * ========================================================= */

function base_url(): string
{
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $scheme = $https ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';

    $dir = str_replace(
        '\\',
        '/',
        dirname($scriptName)
    );

    $dir = rtrim($dir, '/');

    return $scheme . '://' . $host . $dir;
}