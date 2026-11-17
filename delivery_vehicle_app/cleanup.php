<?php
// CLI cron only: php /path/to/cleanup.php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Forbidden');}
require __DIR__.'/common.php';
$days=max(1,(int)($cfg['retention_days']??90));$s=db()->prepare('DELETE FROM dv_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');$s->execute([$days]);echo 'Deleted: '.$s->rowCount().PHP_EOL;
