-- Use existing Industry Location values as ordered Job Title Operating Areas.
-- Existing individual-industry route rows are mapped automatically where possible.

ALTER TABLE job_route_stops MODIFY industry_id INT NULL;

SET @operating_area_missing := (
    SELECT COUNT(*) = 0 FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'job_route_stops' AND column_name = 'operating_area'
);
SET @operating_area_sql := IF(@operating_area_missing,
    'ALTER TABLE job_route_stops ADD COLUMN operating_area VARCHAR(255) NULL AFTER industry_id',
    'SELECT 1');
PREPARE add_operating_area FROM @operating_area_sql;
EXECUTE add_operating_area;
DEALLOCATE PREPARE add_operating_area;

UPDATE job_route_stops jrs
JOIN industries i ON i.id = jrs.industry_id AND i.railroad_id = jrs.railroad_id
SET jrs.operating_area = TRIM(i.location)
WHERE NULLIF(TRIM(COALESCE(jrs.operating_area,'')),'') IS NULL
  AND NULLIF(TRIM(COALESCE(i.location,'')),'') IS NOT NULL;

-- Several legacy industry stops may map to one shared Location. Keep the first
-- configured stop so each Operating Area appears once without manual re-entry.
DELETE duplicate_stop
FROM job_route_stops duplicate_stop
JOIN job_route_stops first_stop
  ON first_stop.job_id = duplicate_stop.job_id
 AND first_stop.railroad_id = duplicate_stop.railroad_id
 AND first_stop.operating_area = duplicate_stop.operating_area
 AND first_stop.id < duplicate_stop.id
WHERE NULLIF(TRIM(duplicate_stop.operating_area),'') IS NOT NULL;

SET @area_index_missing := (
    SELECT COUNT(*) = 0 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'job_route_stops'
      AND index_name = 'uq_job_route_stop_area'
);
SET @area_index_sql := IF(@area_index_missing,
    'ALTER TABLE job_route_stops ADD UNIQUE KEY uq_job_route_stop_area (job_id, operating_area)',
    'SELECT 1');
PREPARE add_route_area_index FROM @area_index_sql;
EXECUTE add_route_area_index;
DEALLOCATE PREPARE add_route_area_index;