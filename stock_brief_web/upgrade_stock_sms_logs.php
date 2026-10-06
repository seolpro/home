<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=utf-8');

try {
    db()->exec("
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

    echo '<h2>✅ stock_sms_logs 테이블 생성 완료</h2>';
    echo '<p>기존 임대차 시스템의 sms_logs 테이블은 수정하지 않았습니다.</p>';
    echo '<p><b>이 파일은 이제 서버에서 삭제하세요.</b></p>';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>❌ 생성 오류</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
