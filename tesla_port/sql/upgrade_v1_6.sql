-- Tesla Port Monitor v1.6 / API024 입출항보고내역 교차검증
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER china_origin_flag;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_submission_no VARCHAR(30) NULL AFTER customs_iopr_verified;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_arrival_at DATETIME NULL AFTER customs_iopr_submission_no;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_departure_port VARCHAR(200) NULL AFTER customs_iopr_arrival_at;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_customs_name VARCHAR(100) NULL AFTER customs_iopr_departure_port;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_berth_name VARCHAR(300) NULL AFTER customs_iopr_customs_name;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_last_checked_at DATETIME NULL AFTER customs_iopr_berth_name;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_error VARCHAR(1000) NULL AFTER customs_iopr_last_checked_at;
ALTER TABLE tp_vessels ADD COLUMN customs_iopr_raw_xml LONGTEXT NULL AFTER customs_iopr_error;
CREATE INDEX idx_tp_vessels_iopr_checked ON tp_vessels(customs_iopr_last_checked_at);
