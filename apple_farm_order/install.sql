CREATE TABLE IF NOT EXISTS apple_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_no VARCHAR(30) NOT NULL UNIQUE,
 recipient_name VARCHAR(80) NOT NULL,
 phone VARCHAR(20) NOT NULL,
 postcode VARCHAR(10) NOT NULL,
 road_address VARCHAR(255) NOT NULL,
 detail_address VARCHAR(255) NOT NULL,
 product_code VARCHAR(80) NOT NULL,
 product_name VARCHAR(120) NOT NULL,
 unit_price INT UNSIGNED NOT NULL DEFAULT 0,
 quantity INT UNSIGNED NOT NULL,
 delivery_request TEXT NULL,
 status ENUM('received','shipped') NOT NULL DEFAULT 'received',
 shipped_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_created(created_at),INDEX idx_status(status),INDEX idx_phone(phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
