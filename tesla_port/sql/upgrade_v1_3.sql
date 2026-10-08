-- Tesla Port Monitor v1.2 -> v1.3 (1회 실행)
ALTER TABLE tp_vessels
 ADD COLUMN origin_port_code VARCHAR(10) NULL AFTER origin_port,
 ADD COLUMN is_vehicle_carrier TINYINT(1) NOT NULL DEFAULT 0 AFTER report_company,
 ADD COLUMN china_origin_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER is_vehicle_carrier,
 ADD INDEX idx_vehicle_candidate(is_vehicle_carrier, china_origin_flag, eta);
