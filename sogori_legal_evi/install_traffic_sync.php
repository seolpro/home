<?php
declare(strict_types=1);
require_once __DIR__.'/lib/traffic.php';
header('Content-Type: text/html; charset=utf-8');
try{
    traffic_db()->exec("CREATE TABLE IF NOT EXISTS traffic_daily_sync (
      sync_date DATE NOT NULL,
      actual_bytes_at_sync BIGINT NOT NULL DEFAULT 0,
      observed_bytes_at_sync BIGINT NOT NULL DEFAULT 0,
      offset_bytes BIGINT NOT NULL DEFAULT 0,
      synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (sync_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo '<h2>트래픽 동기화 테이블 설치 완료</h2><p><b>traffic_daily_sync</b> 테이블이 준비되었습니다.</p><p>보안을 위해 이 파일은 실행 후 서버에서 삭제하세요.</p>';
}catch(Throwable $e){http_response_code(500);echo '<h2>설치 실패</h2><pre>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</pre>';}
