-- 기존 apple_orders 자료를 유지하면서 신규 컬럼만 추가합니다. 1회만 실행하세요.
ALTER TABLE apple_orders
 ADD COLUMN payment_confirmed TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN payment_confirmed_at DATETIME NULL DEFAULT NULL,
 ADD COLUMN admin_memo VARCHAR(100) NULL DEFAULT NULL;
