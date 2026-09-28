<?php
declare(strict_types=1);
require __DIR__.'/config.php';
require __DIR__.'/lib/traffic.php';
header('Content-Type: text/html; charset=utf-8');
$ok=false;$msg='';
try{
 $pdo=traffic_db();
 $sqls=[
"CREATE TABLE IF NOT EXISTS traffic_visits (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45) NOT NULL, session_key VARCHAR(64) DEFAULT NULL, page_url VARCHAR(500) DEFAULT NULL, referer VARCHAR(1000) DEFAULT NULL, user_agent VARCHAR(1000) DEFAULT NULL, device_type VARCHAR(30) DEFAULT NULL, browser VARCHAR(50) DEFAULT NULL, os VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id), KEY idx_visited_at(visited_at), KEY idx_ip(ip_address), KEY idx_session(session_key), KEY idx_page(page_url(191))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS traffic_resource_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45) NOT NULL, session_key VARCHAR(64) DEFAULT NULL, transferred_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, resource_json MEDIUMTEXT DEFAULT NULL, PRIMARY KEY(id), KEY idx_created_at(created_at), KEY idx_ip(ip_address), KEY idx_session(session_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS traffic_alert_logs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, alert_date DATE NOT NULL, alert_level TINYINT UNSIGNED NOT NULL, used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, limit_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, usage_percent DECIMAL(7,2) NOT NULL DEFAULT 0, recipient VARCHAR(30) DEFAULT NULL, send_success TINYINT(1) NOT NULL DEFAULT 0, result_json MEDIUMTEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_date_level(alert_date,alert_level), KEY idx_success(send_success)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
 ];
 foreach($sqls as$sql)$pdo->exec($sql);
 $ok=true;$msg='traffic_visits, traffic_resource_events, traffic_alert_logs 테이블 생성이 완료되었습니다.';
}catch(Throwable $e){$msg='설치 실패: '.$e->getMessage();}
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>트래픽 DB 설치</title><style>body{font-family:system-ui;background:#f5f7fa;padding:30px}.box{max-width:760px;margin:auto;background:#fff;border:1px solid #ddd;border-radius:16px;padding:24px}.ok{color:#087443}.err{color:#b42318}code{background:#f2f4f7;padding:2px 5px;border-radius:4px}</style><div class="box"><h1>트래픽 DB 설치</h1><p class="<?=$ok?'ok':'err'?>"><b><?=htmlspecialchars($msg,ENT_QUOTES,'UTF-8')?></b></p><?php if($ok):?><p>이제 <code>install_traffic.php</code>는 서버에서 삭제하거나 파일명을 변경해 주세요.</p><p><a href="admin/traffic.php">방문·트래픽 통계 열기</a></p><?php else:?><p><code>config.php</code>의 TRAFFIC_DB_* 값을 확인한 후 다시 실행하세요.</p><?php endif?></div></html>
