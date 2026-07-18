-- Repair Queue V1. Apply after 20260715_expand_operations_lifecycle_statuses.sql.

CREATE TABLE IF NOT EXISTS operation_repairs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    railroad_id INT NOT NULL,
    equipment_id INT NOT NULL,
    source_move_id BIGINT UNSIGNED NULL,
    status ENUM('awaiting_repair','in_repair','ready_for_service','closed') NOT NULL DEFAULT 'awaiting_repair',
    reason_code VARCHAR(64) NOT NULL DEFAULT 'bad_order',
    original_notes VARCHAR(255) NULL,
    reported_at DATETIME NOT NULL,
    created_by_user_id INT NULL,
    equipment_active_before TINYINT(1) NOT NULL DEFAULT 1,
    service_state_applied TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    open_equipment_id INT GENERATED ALWAYS AS (
        CASE WHEN status IN ('awaiting_repair','in_repair','ready_for_service') THEN equipment_id ELSE NULL END
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operation_repair_source_move (source_move_id),
    UNIQUE KEY uq_operation_repair_open_equipment (railroad_id, open_equipment_id),
    KEY idx_operation_repairs_status (railroad_id, status, reported_at),
    KEY idx_operation_repairs_equipment (railroad_id, equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operation_repair_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repair_id BIGINT UNSIGNED NOT NULL,
    railroad_id INT NOT NULL,
    event_type ENUM('reported','incident','status_change','note') NOT NULL,
    previous_status VARCHAR(32) NULL,
    new_status VARCHAR(32) NOT NULL,
    note TEXT NULL,
    source_move_id BIGINT UNSIGNED NULL,
    created_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operation_repair_history_incident (repair_id, event_type, source_move_id),
    KEY idx_operation_repair_history_repair (repair_id, created_at),
    KEY idx_operation_repair_history_scope (railroad_id, repair_id),
    CONSTRAINT fk_operation_repair_history_repair
        FOREIGN KEY (repair_id) REFERENCES operation_repairs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safely surface only the most recent persisted historical Bad Order per equipment.
-- These imports do not alter equipment.active because the current service state is unknowable.
INSERT IGNORE INTO operation_repairs (
    railroad_id, equipment_id, source_move_id, status, reason_code, original_notes,
    reported_at, equipment_active_before, service_state_applied
)
SELECT
    m.railroad_id, m.equipment_id, m.id, 'awaiting_repair', 'bad_order', m.exception_notes,
    COALESCE(m.completed_at, sl.completed_at, sl.updated_at), COALESCE(e.active, 1), 0
FROM operation_switch_list_moves m
JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
JOIN equipment e ON e.id=m.equipment_id AND e.railroad_id=m.railroad_id
LEFT JOIN operation_switch_list_moves newer
    ON newer.railroad_id=m.railroad_id
    AND newer.equipment_id=m.equipment_id
    AND newer.exception_reason_code='bad_order'
    AND newer.id>m.id
WHERE m.exception_reason_code='bad_order'
  AND m.equipment_id IS NOT NULL
  AND newer.id IS NULL;

INSERT IGNORE INTO operation_repair_history (
    repair_id, railroad_id, event_type, new_status, note, source_move_id, created_at
)
SELECT
    r.id, r.railroad_id, 'reported', r.status,
    'Imported from historical Operations Bad Order; equipment service state was not changed.',
    r.source_move_id, r.reported_at
FROM operation_repairs r
WHERE r.source_move_id IS NOT NULL;
