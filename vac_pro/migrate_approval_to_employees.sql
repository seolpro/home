-- 기존 V2 설치본을 직원기반 결재선으로 변경하는 SQL
-- 실행 전 DB 백업을 권장합니다.
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE admins ADD COLUMN employee_id INT NULL AFTER role;
ALTER TABLE admins ADD UNIQUE KEY uq_admin_employee (employee_id);

ALTER TABLE approval_line_steps ADD COLUMN approver_employee_id INT NULL AFTER step_order;
ALTER TABLE request_approvals ADD COLUMN approver_employee_id INT NULL AFTER step_order;

-- 관리자와 직원의 이름이 동일한 경우 기존 결재선을 자동 연결합니다.
UPDATE approval_line_steps als
JOIN admins a ON a.id=als.approver_admin_id
JOIN employees e ON e.name=a.name AND e.is_active=1
SET als.approver_employee_id=e.id
WHERE als.approver_employee_id IS NULL;

UPDATE request_approvals ra
JOIN admins a ON a.id=ra.approver_admin_id
JOIN employees e ON e.name=a.name AND e.is_active=1
SET ra.approver_employee_id=e.id
WHERE ra.approver_employee_id IS NULL;

UPDATE admins a
JOIN employees e ON e.name=a.name AND e.is_active=1
SET a.employee_id=e.id
WHERE a.employee_id IS NULL;

-- 자동 연결되지 않은 기존 결재선은 삭제 후 화면에서 다시 등록해야 합니다.
DELETE FROM approval_line_steps WHERE approver_employee_id IS NULL;
DELETE FROM request_approvals WHERE approver_employee_id IS NULL;

ALTER TABLE approval_line_steps DROP FOREIGN KEY fk_step_admin;
ALTER TABLE request_approvals DROP FOREIGN KEY fk_ra_admin;
ALTER TABLE approval_line_steps DROP COLUMN approver_admin_id;
ALTER TABLE request_approvals DROP COLUMN approver_admin_id;
ALTER TABLE approval_line_steps MODIFY approver_employee_id INT NOT NULL;
ALTER TABLE request_approvals MODIFY approver_employee_id INT NOT NULL;
ALTER TABLE approval_line_steps ADD CONSTRAINT fk_step_employee FOREIGN KEY(approver_employee_id) REFERENCES employees(id) ON DELETE CASCADE;
ALTER TABLE request_approvals ADD CONSTRAINT fk_ra_employee FOREIGN KEY(approver_employee_id) REFERENCES employees(id);

SET FOREIGN_KEY_CHECKS=1;
