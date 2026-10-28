CREATE TABLE IF NOT EXISTS apple_as_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(40) NOT NULL,
 detail TEXT NOT NULL,
 status ENUM('received','processing','resolved') NOT NULL DEFAULT 'received',
 admin_memo TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 INDEX idx_as_order (order_id), INDEX idx_as_status (status), INDEX idx_as_created (created_at),
 CONSTRAINT fk_apple_as_order FOREIGN KEY (order_id) REFERENCES apple_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
