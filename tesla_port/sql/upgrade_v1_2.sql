-- v1/v1.1 설치 DB에서 v1.2로 1회 업그레이드
ALTER TABLE tp_vessels
 ADD COLUMN call_sign VARCHAR(30) NULL AFTER voyage_no,
 ADD COLUMN vessel_kind VARCHAR(80) NULL AFTER call_sign,
 ADD COLUMN port_code VARCHAR(10) NULL AFTER eta,
 ADD COLUMN first_origin_port VARCHAR(120) NULL AFTER origin_port,
 ADD COLUMN next_port VARCHAR(120) NULL AFTER first_origin_port,
 ADD COLUMN manifest_no VARCHAR(40) NULL AFTER next_port,
 ADD COLUMN berth_name VARCHAR(120) NULL AFTER manifest_no,
 ADD COLUMN cargo_ton DECIMAL(14,2) NULL AFTER berth_name,
 ADD COLUMN landing_ton DECIMAL(14,2) NULL AFTER cargo_ton,
 ADD COLUMN report_company VARCHAR(150) NULL AFTER landing_ton,
 ADD INDEX idx_manifest_no(manifest_no),
 ADD INDEX idx_call_sign(call_sign);
