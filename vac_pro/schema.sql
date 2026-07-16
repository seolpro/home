CREATE TABLE admins(
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL,
 name VARCHAR(80) NOT NULL, role ENUM('super_admin','hr_admin','department_manager','approver','viewer') NOT NULL DEFAULT 'hr_admin', employee_id INT NULL UNIQUE,
 phone VARCHAR(30), email VARCHAR(120), alimtalk_opt_in TINYINT(1) DEFAULT 1, is_active TINYINT(1) DEFAULT 1,
 last_login DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE departments(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(40) NULL UNIQUE,name VARCHAR(100) NOT NULL,parent_id INT NULL,manager_employee_id INT NULL,sort_order INT DEFAULT 0,is_active TINYINT(1) DEFAULT 1,memo VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE employees(
 id INT AUTO_INCREMENT PRIMARY KEY,employee_no VARCHAR(40),name VARCHAR(80) NOT NULL,position VARCHAR(80),department_id INT NULL,
 phone VARCHAR(30),email VARCHAR(120),hire_date DATE NOT NULL,quit_date DATE NULL,custom_grant_days DECIMAL(6,2) NULL,
 mandatory_rate DECIMAL(5,2) NULL,approval_line_id INT NULL,allowance_enabled TINYINT(1) DEFAULT 1,alimtalk_opt_in TINYINT(1) DEFAULT 1,
 sort_order INT DEFAULT 0,is_active TINYINT(1) DEFAULT 1,memo TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX(department_id),CONSTRAINT fk_emp_dept FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE leave_types(
 id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL,code VARCHAR(40) UNIQUE NOT NULL,color VARCHAR(20) DEFAULT '#2563eb',
 deduct_enabled TINYINT(1) DEFAULT 1,default_days DECIMAL(6,2) DEFAULT 1,allow_custom_days TINYINT(1) DEFAULT 1,
 min_unit DECIMAL(6,2) DEFAULT .25,require_half_option TINYINT(1) DEFAULT 0,allow_date_range TINYINT(1) DEFAULT 1,
 include_weekends TINYINT(1) DEFAULT 0,require_evidence TINYINT(1) DEFAULT 0,require_approval TINYINT(1) DEFAULT 1,
 apply_daily_limit TINYINT(1) DEFAULT 1,annual_limit DECIMAL(6,2) NULL,is_paid TINYINT(1) DEFAULT 1,
 sort_order INT DEFAULT 0,is_active TINYINT(1) DEFAULT 1,description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE admin_department_scopes(id INT AUTO_INCREMENT PRIMARY KEY,admin_id INT NOT NULL,department_id INT NOT NULL,UNIQUE(admin_id,department_id),CONSTRAINT fk_scope_admin FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE CASCADE,CONSTRAINT fk_scope_department FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE approval_lines(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,department_id INT NULL,sort_order INT DEFAULT 0,is_active TINYINT(1) DEFAULT 1,CONSTRAINT fk_line_dept FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE approval_line_steps(id INT AUTO_INCREMENT PRIMARY KEY,line_id INT NOT NULL,step_order INT NOT NULL,approver_employee_id INT NOT NULL,UNIQUE(line_id,step_order),CONSTRAINT fk_step_line FOREIGN KEY(line_id) REFERENCES approval_lines(id) ON DELETE CASCADE,CONSTRAINT fk_step_employee FOREIGN KEY(approver_employee_id) REFERENCES employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE leave_requests(
 id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,leave_type_id INT NOT NULL,approval_line_id INT NULL,start_date DATE NOT NULL,end_date DATE NOT NULL,
 requested_days DECIMAL(6,2) NOT NULL,deduct_days DECIMAL(6,2) NOT NULL,half_option VARCHAR(20) DEFAULT '',memo TEXT,
 evidence_path VARCHAR(255),status ENUM('pending','in_approval','approved','rejected','cancelled') DEFAULT 'pending',
 current_step INT DEFAULT 0,reject_reason VARCHAR(255),created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,approved_at DATETIME NULL,INDEX(employee_id,start_date),
 CONSTRAINT fk_req_emp FOREIGN KEY(employee_id) REFERENCES employees(id),CONSTRAINT fk_req_line FOREIGN KEY(approval_line_id) REFERENCES approval_lines(id) ON DELETE SET NULL,CONSTRAINT fk_req_type FOREIGN KEY(leave_type_id) REFERENCES leave_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE request_approvals(id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NOT NULL,step_order INT NOT NULL,approver_employee_id INT NOT NULL,status ENUM('waiting','pending','approved','rejected','skipped') DEFAULT 'waiting',comment VARCHAR(255),acted_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE(request_id,step_order),CONSTRAINT fk_ra_req FOREIGN KEY(request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,CONSTRAINT fk_ra_employee FOREIGN KEY(approver_employee_id) REFERENCES employees(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE leave_balances(id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,leave_year INT NOT NULL,granted_days DECIMAL(6,2) NOT NULL,carried_days DECIMAL(6,2) DEFAULT 0,adjustment_days DECIMAL(6,2) DEFAULT 0,memo VARCHAR(255),UNIQUE(employee_id,leave_year),CONSTRAINT fk_bal_emp FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE employee_salary_history(
 id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,apply_year INT NOT NULL,monthly_ordinary_wage BIGINT UNSIGNED NOT NULL DEFAULT 0,
 memo VARCHAR(255),created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE(employee_id,apply_year),CONSTRAINT fk_salary_emp FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE leave_allowance_calculations(
 id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,calc_year INT NOT NULL,monthly_ordinary_wage BIGINT UNSIGNED NOT NULL,
 monthly_standard_hours DECIMAL(8,2) NOT NULL DEFAULT 209,daily_standard_hours DECIMAL(8,2) NOT NULL DEFAULT 8,
 granted_days DECIMAL(8,2) NOT NULL DEFAULT 0,used_days DECIMAL(8,2) NOT NULL DEFAULT 0,excluded_days DECIMAL(8,2) NOT NULL DEFAULT 0,
 adjustment_days DECIMAL(8,2) NOT NULL DEFAULT 0,payable_unused_days DECIMAL(8,2) NOT NULL DEFAULT 0,
 hourly_wage DECIMAL(14,2) NOT NULL DEFAULT 0,daily_wage DECIMAL(14,2) NOT NULL DEFAULT 0,allowance_amount BIGINT NOT NULL DEFAULT 0,
 is_confirmed TINYINT(1) DEFAULT 0,confirmed_at DATETIME NULL,confirmed_by INT NULL,memo VARCHAR(255),updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE(employee_id,calc_year),CONSTRAINT fk_allow_emp FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
 CONSTRAINT fk_allow_admin FOREIGN KEY(confirmed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE notification_templates(
 id INT AUTO_INCREMENT PRIMARY KEY,event_code VARCHAR(80) NOT NULL UNIQUE,event_name VARCHAR(100) NOT NULL,
 template_code VARCHAR(100) NOT NULL,message_template TEXT NOT NULL,button_json TEXT NULL,
 variable_help VARCHAR(500) NULL,is_active TINYINT(1) DEFAULT 1,sort_order INT DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE notification_queue(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,event_code VARCHAR(80) NOT NULL,recipient_type VARCHAR(30) NOT NULL,recipient_id INT NULL,
 phone VARCHAR(30) NOT NULL,variables_json MEDIUMTEXT,dedupe_key VARCHAR(64) NOT NULL UNIQUE,
 status ENUM('pending','processing','retry','sent','failed','cancelled') DEFAULT 'pending',attempts INT DEFAULT 0,
 available_at DATETIME DEFAULT CURRENT_TIMESTAMP,locked_at DATETIME NULL,sent_at DATETIME NULL,last_error TEXT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,INDEX(status,available_at),INDEX(recipient_type,recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE notification_logs(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,queue_id BIGINT NULL,event_code VARCHAR(80),recipient_type VARCHAR(30),recipient_id INT NULL,
 phone VARCHAR(30),template_code VARCHAR(100),message_text TEXT,payload MEDIUMTEXT,response MEDIUMTEXT,is_success TINYINT(1) DEFAULT 0,
 message_key VARCHAR(100) NULL,result_code VARCHAR(30) NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX(created_at),INDEX(queue_id),CONSTRAINT fk_nlog_queue FOREIGN KEY(queue_id) REFERENCES notification_queue(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE audit_logs(id BIGINT AUTO_INCREMENT PRIMARY KEY,admin_id INT NULL,action VARCHAR(80),target_type VARCHAR(50),target_id INT NULL,detail TEXT,ip_address VARCHAR(50),created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE departments ADD CONSTRAINT fk_department_parent FOREIGN KEY(parent_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE departments ADD CONSTRAINT fk_department_manager FOREIGN KEY(manager_employee_id) REFERENCES employees(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employee_approval_line FOREIGN KEY(approval_line_id) REFERENCES approval_lines(id) ON DELETE SET NULL;

CREATE TABLE import_batches(
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 import_type ENUM('employees','approved_leave') NOT NULL,
 original_filename VARCHAR(255) NULL,
 total_rows INT NOT NULL DEFAULT 0,
 success_rows INT NOT NULL DEFAULT 0,
 failed_rows INT NOT NULL DEFAULT 0,
 result_message TEXT NULL,
 created_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_import_admin FOREIGN KEY(created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE leave_requests
 ADD COLUMN source_type ENUM('application','import') NOT NULL DEFAULT 'application' AFTER reject_reason,
 ADD COLUMN import_batch_id BIGINT NULL AFTER source_type,
 ADD INDEX idx_leave_import_batch(import_batch_id),
 ADD CONSTRAINT fk_leave_import_batch FOREIGN KEY(import_batch_id) REFERENCES import_batches(id) ON DELETE SET NULL;
