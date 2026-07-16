-- Add only the missing lifecycle states while retaining every existing enum value.
ALTER TABLE operation_assignments
    MODIFY status ENUM('draft','ready','waiting','in_progress','needs_review','completed','cancelled','aborted') NOT NULL DEFAULT 'draft';

ALTER TABLE operation_switch_lists
    MODIFY status ENUM('draft','approved','in_progress','completed','cancelled','needs_review','superseded') NOT NULL DEFAULT 'draft';

-- These notes were written by the previous regeneration/edit paths and identify
-- revisions that were invalidated rather than genuinely cancelled.
UPDATE operation_switch_lists
SET status='superseded'
WHERE status='cancelled'
  AND (notes LIKE '%Superseded by Revision %' OR notes LIKE '%Invalidated because the assignment settings were edited.%')
  AND started_at IS NULL
  AND completed_at IS NULL;
