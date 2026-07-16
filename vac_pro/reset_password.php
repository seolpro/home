<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/lib/db.php';

try {
    $pdo = db();

    // 실제 초기화할 관리자 아이디
    $username = 'admin';

    // 임시 새 비밀번호
    $newPassword = '12345678';

    /*
     * admins 테이블에서 실제 비밀번호 컬럼을 자동 탐색
     */
    $columns = $pdo->query("SHOW COLUMNS FROM admins")
                   ->fetchAll(PDO::FETCH_COLUMN);

    $passwordColumn = null;

    foreach ([
        'password_hash',
        'passwd_hash',
        'password',
        'passwd'
    ] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $passwordColumn = $candidate;
            break;
        }
    }

    if ($passwordColumn === null) {
        throw new RuntimeException(
            'admins 테이블에서 비밀번호 저장 컬럼을 찾을 수 없습니다. 현재 컬럼: '
            . implode(', ', $columns)
        );
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    /*
     * 컬럼명은 DB에서 확인한 허용 목록 중 하나만 사용하므로 안전함
     */
    $sql = "
        UPDATE admins
        SET `{$passwordColumn}` = ?
        WHERE username = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash, $username]);

    if ($stmt->rowCount() < 1) {
        $check = $pdo->prepare("
            SELECT id, username, name
            FROM admins
            WHERE username = ?
            LIMIT 1
        ");
        $check->execute([$username]);

        if (!$check->fetch()) {
            throw new RuntimeException(
                '관리자 아이디를 찾을 수 없습니다: ' . $username
            );
        }
    }

    echo '<h2>관리자 비밀번호 초기화 완료</h2>';
    echo '<p>사용된 비밀번호 컬럼: '
        . htmlspecialchars($passwordColumn, ENT_QUOTES, 'UTF-8')
        . '</p>';
    echo '<p>아이디: '
        . htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
        . '</p>';
    echo '<p>임시 비밀번호: '
        . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8')
        . '</p>';
    echo '<p style="color:red;font-weight:bold;">'
        . '로그인 확인 후 reset_password.php 파일을 반드시 삭제하세요.'
        . '</p>';

} catch (Throwable $e) {
    http_response_code(500);

    echo '<pre style="white-space:pre-wrap;">';
    echo '오류: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "\n파일: " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
    echo "\n라인: " . (int)$e->getLine();
    echo '</pre>';
}