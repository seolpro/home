CREATE TABLE `accident_cases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `accident_date` DATE NOT NULL,
  `accident_place` VARCHAR(255) DEFAULT NULL,
  `opponent_name` VARCHAR(255) DEFAULT NULL,
  `opponent_insurer` VARCHAR(255) DEFAULT NULL,
  `claim_handler` VARCHAR(255) DEFAULT NULL,
  `handler_contact` VARCHAR(100) DEFAULT NULL,
  `claim_no` VARCHAR(120) DEFAULT NULL,
  `vehicle_no` VARCHAR(120) DEFAULT NULL,
  `hospital_name` VARCHAR(255) DEFAULT NULL,
  `injury_summary` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'collecting',
  `expected_settlement` BIGINT DEFAULT NULL,
  `notes` TEXT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_accident_date` (`accident_date`),
  KEY `idx_status` (`status`),
  KEY `idx_claim_no` (`claim_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` INT UNSIGNED NOT NULL,
  `expense_date` DATE NOT NULL,
  `expense_type` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` INT NOT NULL DEFAULT 0,
  `memo` TEXT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_case_date` (`case_id`, `expense_date`),
  CONSTRAINT `fk_expenses_case` FOREIGN KEY (`case_id`) REFERENCES `accident_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` INT UNSIGNED NOT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `memo` TEXT,
  `document_date` DATE DEFAULT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(150) DEFAULT NULL,
  `file_ext` VARCHAR(20) DEFAULT NULL,
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `file_path` VARCHAR(255) NOT NULL,
  `storage_group` VARCHAR(30) NOT NULL DEFAULT 'documents',
  `is_image` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_doc_case_date` (`case_id`, `document_date`),
  KEY `idx_doc_type` (`document_type`),
  CONSTRAINT `fk_documents_case` FOREIGN KEY (`case_id`) REFERENCES `accident_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `negotiations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` INT UNSIGNED NOT NULL,
  `event_date` DATE NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `amount` BIGINT DEFAULT NULL,
  `channel` VARCHAR(100) DEFAULT NULL,
  `counterparty` VARCHAR(255) DEFAULT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `details` TEXT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_neg_case_date` (`case_id`, `event_date`),
  KEY `idx_neg_stage` (`stage`),
  CONSTRAINT `fk_negotiations_case` FOREIGN KEY (`case_id`) REFERENCES `accident_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
