-- Yardmaster V1 planning, session roles, and retained history.
-- Apply after 20260718_add_operations_dispatcher.sql and 20260718_add_operations_module_settings.sql.
-- Safe for existing railroads: the module remains disabled until explicitly enabled.

ALTER TABLE operation_railroad_roles
    MODIFY COLUMN role ENUM('dispatcher','yardmaster') NOT NULL;

CREATE TABLE IF NOT EXISTS operation_session_roles (
    session_id BIGINT UNSIGNED NOT NULL,
    railroad_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('yardmaster') NOT NULL,
    assigned_by_user_id INT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, role),
    KEY idx_operation_session_role_user (user_id, railroad_id, role),
    CONSTRAINT fk_operation_session_role_session FOREIGN KEY (session_id) REFERENCES operating_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_operation_session_role_railroad FOREIGN KEY (railroad_id) REFERENCES railroads(id),
    CONSTRAINT fk_operation_session_role_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operation_yard_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    railroad_id INT NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    equipment_id INT NOT NULL,
    yard_industry_id INT NOT NULL,
    planned_track VARCHAR(120) NULL,
    classification_group VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    created_by_user_id INT NULL,
    updated_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_yard_assignment_session_equipment (session_id, equipment_id),
    KEY idx_yard_assignment_scope (railroad_id, session_id, yard_industry_id),
    CONSTRAINT fk_yard_assignment_session FOREIGN KEY (session_id) REFERENCES operating_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_yard_assignment_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    CONSTRAINT fk_yard_assignment_industry FOREIGN KEY (yard_industry_id) REFERENCES industries(id),
    CONSTRAINT fk_yard_assignment_railroad FOREIGN KEY (railroad_id) REFERENCES railroads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS operation_yard_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    railroad_id INT NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    equipment_id INT NULL,
    event_type ENUM('assigned','moved','cleared','role_assigned','role_cleared') NOT NULL,
    from_yard_industry_id INT NULL,
    to_yard_industry_id INT NULL,
    detail VARCHAR(255) NULL,
    created_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_yard_history_session (railroad_id, session_id, created_at),
    CONSTRAINT fk_yard_history_session FOREIGN KEY (session_id) REFERENCES operating_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_yard_history_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    CONSTRAINT fk_yard_history_from_industry FOREIGN KEY (from_yard_industry_id) REFERENCES industries(id),
    CONSTRAINT fk_yard_history_to_industry FOREIGN KEY (to_yard_industry_id) REFERENCES industries(id),
    CONSTRAINT fk_yard_history_railroad FOREIGN KEY (railroad_id) REFERENCES railroads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
