CREATE TABLE IF NOT EXISTS operation_switch_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    railroad_id INT NOT NULL,
    user_id INT NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    source_key VARCHAR(64) NOT NULL,
    moved_count INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operation_switch_source (railroad_id, source_type, source_key),
    KEY idx_operation_switch_railroad_completed (railroad_id, completed_at),
    KEY idx_operation_switch_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operation_switch_moves (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    switch_session_id BIGINT UNSIGNED NOT NULL,
    railroad_id INT NOT NULL,
    equipment_id INT NOT NULL,
    move_key VARCHAR(64) NOT NULL,
    outcome ENUM('moved', 'not_moved') NOT NULL,
    reason_code VARCHAR(64) NULL,
    reason_notes VARCHAR(255) NULL,
    old_industry_id INT NULL,
    new_industry_id INT NULL,
    old_track VARCHAR(255) NULL,
    new_track VARCHAR(255) NULL,
    old_load_status VARCHAR(64) NULL,
    new_load_status VARCHAR(64) NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operation_switch_move (switch_session_id, move_key),
    KEY idx_operation_switch_move_railroad (railroad_id),
    KEY idx_operation_switch_move_equipment (equipment_id),
    CONSTRAINT fk_operation_switch_moves_session
        FOREIGN KEY (switch_session_id)
        REFERENCES operation_switch_sessions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
