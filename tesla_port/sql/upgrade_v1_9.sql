-- Tesla Port Monitor v1.9
-- 1회 실행: MRN -> MSN 후보 자동스캔 결과/상태 저장
CREATE TABLE IF NOT EXISTS tp_manifest_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vessel_id BIGINT UNSIGNED NOT NULL,
  mrn VARCHAR(11) NOT NULL,
  msn CHAR(4) NOT NULL,
  cargo_no VARCHAR(19) NOT NULL,
  mbl_no VARCHAR(30) NULL,
  hbl_no VARCHAR(30) NULL,
  product_name VARCHAR(300) NULL,
  package_count INT NULL,
  package_unit VARCHAR(20) NULL,
  total_weight DECIMAL(18,3) NULL,
  weight_unit VARCHAR(20) NULL,
  loading_port VARCHAR(150) NULL,
  progress_status VARCHAR(300) NULL,
  tesla_flag TINYINT(1) NOT NULL DEFAULT 0,
  raw_xml LONGTEXT NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_manifest_cargo(cargo_no),
  KEY idx_manifest_vessel(vessel_id),
  KEY idx_manifest_mrn(mrn),
  KEY idx_manifest_tesla(tesla_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tp_vessels
  ADD COLUMN manifest_scan_last_msn INT NULL AFTER customs_iopr_raw_xml,
  ADD COLUMN manifest_scan_hits INT NOT NULL DEFAULT 0 AFTER manifest_scan_last_msn,
  ADD COLUMN manifest_scan_complete TINYINT(1) NOT NULL DEFAULT 0 AFTER manifest_scan_hits,
  ADD COLUMN manifest_scan_last_checked_at DATETIME NULL AFTER manifest_scan_complete,
  ADD COLUMN manifest_scan_error VARCHAR(1000) NULL AFTER manifest_scan_last_checked_at;

CREATE INDEX idx_tp_manifest_scan ON tp_vessels(manifest_scan_complete,manifest_scan_last_checked_at);
