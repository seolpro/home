CREATE TABLE IF NOT EXISTS tp_vessels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source_uid CHAR(64) NOT NULL UNIQUE,
 vessel_name VARCHAR(150) NOT NULL, voyage_no VARCHAR(80) NULL, call_sign VARCHAR(30) NULL, vessel_kind VARCHAR(80) NULL,
 eta DATETIME NULL, port_code VARCHAR(10) NULL, port_name VARCHAR(80) NOT NULL DEFAULT '미확인', origin_port VARCHAR(120) NULL,
 first_origin_port VARCHAR(120) NULL, next_port VARCHAR(120) NULL, manifest_no VARCHAR(40) NULL, customs_cargo_no VARCHAR(30) NULL, customs_product_name VARCHAR(300) NULL, customs_progress_status VARCHAR(300) NULL, customs_total_weight DECIMAL(18,3) NULL, customs_weight_unit VARCHAR(10) NULL, customs_package_count INT NULL, customs_last_checked_at DATETIME NULL, customs_error VARCHAR(1000) NULL, customs_raw_xml LONGTEXT NULL, berth_name VARCHAR(120) NULL,
 cargo_ton DECIMAL(14,2) NULL, landing_ton DECIMAL(14,2) NULL, report_company VARCHAR(150) NULL,
 tesla_flag TINYINT(1) NOT NULL DEFAULT 0, vehicle_count INT NULL, total_weight_kg DECIMAL(14,2) NULL, model_guess VARCHAR(120) NULL,
 cargo_status VARCHAR(100) NULL, source_type VARCHAR(30) NOT NULL DEFAULT 'MOF', raw_json LONGTEXT NULL,
 first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 notified_at DATETIME NULL, INDEX(eta), INDEX(tesla_flag,notified_at), INDEX(manifest_no), INDEX(call_sign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tp_subscribers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, phone VARCHAR(30) NOT NULL UNIQUE, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tp_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, vessel_id BIGINT UNSIGNED NOT NULL, subscriber_id INT UNSIGNED NOT NULL, channel VARCHAR(10) NOT NULL DEFAULT 'ALT', success TINYINT(1) NOT NULL DEFAULT 0, result_json LONGTEXT NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_notice(vessel_id,subscriber_id,channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
