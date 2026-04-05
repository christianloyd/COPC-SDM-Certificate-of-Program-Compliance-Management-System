CREATE DATABASE IF NOT EXISTS copcsdm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE copcsdm;

CREATE TABLE IF NOT EXISTS copc_documents (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_name    VARCHAR(255)    NOT NULL,
  program        VARCHAR(255)    NOT NULL,
  category       ENUM('COPC', 'COPC Exemption', 'HEI List', 'GR') NOT NULL,
  date_approved  DATE            NOT NULL,
  file_path      VARCHAR(500)    NULL,        -- NULL for manual-entry records
  file_type      VARCHAR(10)     NULL,        -- 'pdf', 'jpg', 'png', 'xlsx' or NULL
  file_name      VARCHAR(255)    NULL,
  file_size_kb   INT UNSIGNED    NULL,
  extracted_text LONGTEXT        NULL,        -- NULL if no file or extraction failed
  notes          TEXT            NULL,        -- Optional admin notes
  entry_type     ENUM('upload', 'manual') NOT NULL DEFAULT 'upload',
  uploaded_by    VARCHAR(100)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_school_name  (school_name),
  INDEX idx_program      (program),
  INDEX idx_category     (category),
  INDEX idx_date_approved(date_approved),
  INDEX idx_entry_type   (entry_type),
  FULLTEXT INDEX ft_search (school_name, program, extracted_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(100)    NOT NULL UNIQUE,
  password_hash  VARCHAR(255)    NOT NULL,
  full_name      VARCHAR(200)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at  DATETIME        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password is 'admin123' (bcrypt hash)
INSERT INTO admin_users (username, password_hash, full_name) 
VALUES ('admin', '$2a$12$R9h/cIPzTdR.YnJB0R.9aOuM1K5lJwGOKz04.dOUMrA2a/vT0r5i.', 'System Administrator')
ON DUPLICATE KEY UPDATE id=id;
