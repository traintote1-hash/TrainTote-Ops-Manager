-- Fast Clock V1. Apply after 20260718_add_operations_repair_queue.sql.
-- Session rows retain their configured clock values as immutable history after start.

ALTER TABLE operating_sessions
    ADD COLUMN IF NOT EXISTS fast_clock_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN IF NOT EXISTS fast_clock_running TINYINT(1) NOT NULL DEFAULT 0 AFTER fast_clock_enabled,
    ADD COLUMN IF NOT EXISTS fast_clock_start_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 480 AFTER fast_clock_running,
    ADD COLUMN IF NOT EXISTS fast_clock_ratio TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER fast_clock_start_minutes,
    ADD COLUMN IF NOT EXISTS fast_clock_base_model_seconds INT UNSIGNED NOT NULL DEFAULT 28800 AFTER fast_clock_ratio,
    ADD COLUMN IF NOT EXISTS fast_clock_base_real_at DATETIME NULL AFTER fast_clock_base_model_seconds,
    ADD COLUMN IF NOT EXISTS fast_clock_last_sync_at DATETIME NULL AFTER fast_clock_base_real_at,
    ADD COLUMN IF NOT EXISTS fast_clock_started_at DATETIME NULL AFTER fast_clock_last_sync_at;
