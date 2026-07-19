-- Session-entered crew assignments. Names are descriptive and are not TrainTote accounts.
-- Apply after 20260718_add_operations_yardmaster.sql.

ALTER TABLE operating_sessions
    ADD COLUMN IF NOT EXISTS yardmaster_name VARCHAR(120) NULL AFTER notes;

ALTER TABLE operation_assignments
    ADD COLUMN IF NOT EXISTS unit_identifier VARCHAR(24) NULL AFTER assignment_number,
    ADD COLUMN IF NOT EXISTS engineer_name VARCHAR(120) NULL AFTER crew_name,
    ADD COLUMN IF NOT EXISTS conductor_name VARCHAR(120) NULL AFTER engineer_name,
    ADD COLUMN IF NOT EXISTS brakeman_names VARCHAR(255) NULL AFTER conductor_name;

-- Preserve a usable identity for assignments created before this migration.
UPDATE operation_assignments
   SET unit_identifier = assignment_number
 WHERE unit_identifier IS NULL OR unit_identifier = '';
