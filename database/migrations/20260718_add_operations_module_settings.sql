-- Unified Operations module settings. Apply after 20260718_add_operations_dispatcher.sql.

CREATE TABLE IF NOT EXISTS operation_module_settings (
    railroad_id INT NOT NULL,
    module_key VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by_user_id INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (railroad_id, module_key),
    KEY idx_operation_module_enabled (railroad_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve features that an existing railroad had explicitly configured or used.
INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT DISTINCT railroad_id, 'fast_clock', 1
FROM operating_sessions
WHERE fast_clock_enabled = 1 OR fast_clock_started_at IS NOT NULL;

INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT id, 'dispatcher', 1
FROM railroads
WHERE operations_dispatcher_enabled = 1;

INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT DISTINCT railroad_id, 'dispatcher', 1
FROM operating_sessions
WHERE dispatcher_enabled = 1;

INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT DISTINCT railroad_id, 'repair_queue', 1
FROM operation_repairs;

INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT DISTINCT railroad_id, 'crew_messaging', 1
FROM operation_assignments
WHERE dispatcher_crew_message IS NOT NULL AND TRIM(dispatcher_crew_message) <> '';

INSERT IGNORE INTO operation_module_settings (railroad_id, module_key, enabled)
SELECT DISTINCT railroad_id, 'advanced_roles', 1
FROM operation_railroad_roles;
