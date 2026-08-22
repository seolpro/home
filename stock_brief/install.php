<?php
require_once __DIR__.'/lib.php';
try{
db()->exec("CREATE TABLE IF NOT EXISTS portfolio (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,market ENUM('KR_KOSPI','KR_KOSDAQ','US') NOT NULL DEFAULT 'KR_KOSPI',name VARCHAR(100) NOT NULL,symbol VARCHAR(30) NOT NULL,quantity DECIMAL(18,6) NOT NULL DEFAULT 0,avg_price DECIMAL(18,4) NOT NULL DEFAULT 0,news_keyword VARCHAR(150) NOT NULL DEFAULT '',is_active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_market_symbol(market,symbol)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
db()->exec("CREATE TABLE IF NOT EXISTS stock_sms_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recipient VARCHAR(30) NOT NULL,message TEXT NOT NULL,result_json LONGTEXT NULL,created_at DATETIME NOT NULL,KEY idx_created_at(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo '<h2>설치 완료</h2><p>install.php는 삭제하세요.</p>';}catch(Throwable $e){http_response_code(500);echo '<pre>'.e($e->getMessage()).'</pre>';}
