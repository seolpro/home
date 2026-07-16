-- HRM Plus V2: 순차결재 강화 및 일괄업로드 기능 추가
SET @db = DATABASE();

CREATE TABLE IF NOT EXISTS import_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 import_type ENUM('employees','approved_leave') NOT NULL,
 original_filename VARCHAR(255) NULL,
 total_rows INT NOT NULL DEFAULT 0,
 success_rows INT NOT NULL DEFAULT 0,
 failed_rows INT NOT NULL DEFAULT 0,
 result_message TEXT NULL,
 created_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_source = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='leave_requests' AND column_name='source_type');
SET @sql = IF(@has_source=0,"ALTER TABLE leave_requests ADD COLUMN source_type ENUM('application','import') NOT NULL DEFAULT 'application' AFTER reject_reason",'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_batch = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='leave_requests' AND column_name='import_batch_id');
SET @sql = IF(@has_batch=0,"ALTER TABLE leave_requests ADD COLUMN import_batch_id BIGINT NULL AFTER source_type, ADD INDEX idx_leave_import_batch(import_batch_id)",'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 과거 결재데이터의 pending 단계가 여러 개인 경우 가장 앞 단계만 pending으로 정리
UPDATE request_approvals ra
JOIN (
 SELECT request_id,MIN(step_order) min_step
 FROM request_approvals WHERE status='pending' GROUP BY request_id
) x ON x.request_id=ra.request_id
SET ra.status=CASE WHEN ra.step_order=x.min_step THEN 'pending' ELSE 'waiting' END
WHERE ra.status='pending';

UPDATE leave_requests lr
JOIN request_approvals ra ON ra.request_id=lr.id AND ra.status='pending'
SET lr.status='in_approval',lr.current_step=ra.step_order
WHERE lr.status IN ('pending','in_approval');
