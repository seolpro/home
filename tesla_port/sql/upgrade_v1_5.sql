-- Tesla Port Monitor v1.5
-- v1.4 설치 DB에서 1회 실행
ALTER TABLE tp_vessels
  ADD COLUMN customs_mbl_no VARCHAR(20) NULL AFTER customs_cargo_no,
  ADD COLUMN customs_hbl_no VARCHAR(20) NULL AFTER customs_mbl_no,
  ADD COLUMN customs_entry_date VARCHAR(8) NULL AFTER customs_hbl_no;

CREATE TABLE IF NOT EXISTS tp_customs_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  vessel_id BIGINT UNSIGNED NOT NULL,
  cargo_no VARCHAR(19) NOT NULL,
  mbl_no VARCHAR(20) NULL,
  hbl_no VARCHAR(20) NULL,
  entry_date VARCHAR(8) NULL,
  ship_name VARCHAR(100) NULL,
  discharge_port VARCHAR(100) NULL,
  shipping_company VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_vessel_cargo(vessel_id,cargo_no),
  KEY idx_cargo_no(cargo_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
