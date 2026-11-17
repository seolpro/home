CREATE TABLE IF NOT EXISTS dv_recipients (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(80) NOT NULL,
 recipient_name VARCHAR(80) NOT NULL,
 recipient_phone VARCHAR(20) NOT NULL,
 token CHAR(48) NOT NULL UNIQUE,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dv_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 recipient_id INT UNSIGNED NOT NULL,
 market VARCHAR(80) NOT NULL,
 visit_date DATE NOT NULL,
 vehicle_no VARCHAR(30) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 send_status ENUM('pending','accepted','failed') NOT NULL DEFAULT 'pending',
 send_message VARCHAR(255) DEFAULT NULL,
 send_http SMALLINT UNSIGNED DEFAULT NULL,
 sent_at DATETIME DEFAULT NULL,
 retry_count INT UNSIGNED NOT NULL DEFAULT 0,
 INDEX idx_recipient_created(recipient_id,created_at),
 INDEX idx_visit(visit_date),
 CONSTRAINT fk_dv_recipient FOREIGN KEY (recipient_id) REFERENCES dv_recipients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
