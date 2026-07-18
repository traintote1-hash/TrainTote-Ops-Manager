-- Optional Operations Dispatcher V1. Apply after the persistent Operations migrations.

ALTER TABLE railroads
    ADD COLUMN IF NOT EXISTS operations_dispatcher_enabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE operating_sessions
    ADD COLUMN IF NOT EXISTS dispatcher_enabled TINYINT(1) NULL DEFAULT NULL;

ALTER TABLE operation_assignments
    ADD COLUMN IF NOT EXISTS dispatcher_status ENUM('not_started','working','delayed') NOT NULL DEFAULT 'not_started',
    ADD COLUMN IF NOT EXISTS dispatcher_note TEXT NULL,
    ADD COLUMN IF NOT EXISTS dispatcher_crew_message VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS dispatcher_updated_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS dispatcher_updated_by_user_id INT NULL;

CREATE TABLE IF NOT EXISTS operation_railroad_roles (
    railroad_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('dispatcher') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (railroad_id, user_id, role),
    KEY idx_operation_role_user (user_id, railroad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
