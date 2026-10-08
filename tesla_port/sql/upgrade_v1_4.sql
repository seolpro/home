-- Tesla Port Monitor v1.3 -> v1.4 (1회 실행)
ALTER TABLE tp_vessels
 ADD COLUMN customs_cargo_no VARCHAR(30) NULL AFTER manifest_no,
 ADD COLUMN customs_product_name VARCHAR(300) NULL AFTER customs_cargo_no,
 ADD COLUMN customs_progress_status VARCHAR(300) NULL AFTER customs_product_name,
 ADD COLUMN customs_total_weight DECIMAL(18,3) NULL AFTER customs_progress_status,
 ADD COLUMN customs_weight_unit VARCHAR(10) NULL AFTER customs_total_weight,
 ADD COLUMN customs_package_count INT NULL AFTER customs_weight_unit,
 ADD COLUMN customs_last_checked_at DATETIME NULL AFTER customs_package_count,
 ADD COLUMN customs_error VARCHAR(1000) NULL AFTER customs_last_checked_at,
 ADD COLUMN customs_raw_xml LONGTEXT NULL AFTER customs_error,
 ADD INDEX idx_customs_cargo_no(customs_cargo_no),
 ADD INDEX idx_customs_checked(customs_last_checked_at);
