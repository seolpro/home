<?php
require __DIR__.'/config.php';
$sql = <<<SQL
CREATE TABLE IF NOT EXISTS qr_test_members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_no VARCHAR(8) NOT NULL UNIQUE,
 qr_token CHAR(64) DEFAULT NULL UNIQUE,
 token_created_at DATETIME DEFAULT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qr_test_attendance (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_no VARCHAR(8) NOT NULL,
 scanned_code VARCHAR(255) NOT NULL,
 checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_member_no (member_no),
 KEY idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
db()->exec($sql);
echo '<meta charset="utf-8"><h2>설치 완료</h2><p><a href="index.php">테스트 시작</a></p>';
