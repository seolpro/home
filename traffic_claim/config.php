<?php
// Cafe24 배포용 설정 샘플

define('DB_HOST', 'localhost');
define('DB_NAME', 'seolhopro');
define('DB_USER', 'seolhopro');
define('DB_PASS', 'ajou2130--');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', '교통사고 합의자료 보관함');
define('BASE_URL', '/traffic_claim'); // 예: '/traffic_claim_app_final' 또는 ''
define('APP_PASSWORD', '0911');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB per file

date_default_timezone_set('Asia/Seoul');
