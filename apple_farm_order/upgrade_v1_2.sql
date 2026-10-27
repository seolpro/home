-- 기존 apple_orders 테이블에 추천인 컬럼을 추가합니다. 최초 1회만 실행하세요.
ALTER TABLE apple_orders ADD COLUMN referrer VARCHAR(80) NULL DEFAULT NULL AFTER delivery_request;
