<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = db();

    // stock_sms_logs가 없으면 정상 구조로 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_sms_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(30) NOT NULL,
            message TEXT NOT NULL,
            result_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_created_at (created_at),
            KEY idx_recipient (recipient)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 혹시 잘못 생성된 stock_sms_logs에 contract_id가 존재하고 NOT NULL이면 NULL 허용
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'stock_sms_logs'
          AND COLUMN_NAME = 'contract_id'
        LIMIT 1
    ");

    $col = $stmt->fetch();

    if ($col && ($col['IS_NULLABLE'] ?? '') === 'NO') {
        $pdo->exec("
            ALTER TABLE stock_sms_logs
            MODIFY contract_id INT NULL
        ");
    }

    echo '<!doctype html><html lang="ko"><meta charset="utf-8">';
    echo '<body style="font-family:sans-serif;padding:30px">';
    echo '<h2>✅ 주식 브리핑 문자로그 패치 완료</h2>';
    echo '<p><b>stock_sms_logs</b> 테이블을 정상 확인했습니다.</p>';
    echo '<p>기존 임대차용 <b>sms_logs</b> 테이블은 수정하지 않았습니다.</p>';
    echo '<p style="color:#b42318"><b>이 파일은 이제 서버에서 삭제하세요.</b></p>';
    echo '</body></html>';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>❌ 패치 오류</h2>';
    echo '<pre>' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</pre>';
}
