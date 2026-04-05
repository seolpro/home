<?php
// Cafe24 배포용 설정 샘플

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', '교통사고 합의자료 보관함');
define('BASE_URL', ''); // 예: '/traffic_claim_app_final' 또는 ''
define('APP_PASSWORD', 'change_this_password');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB per file

date_default_timezone_set('Asia/Seoul');
