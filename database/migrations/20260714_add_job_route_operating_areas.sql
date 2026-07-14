-- Use existing Industry Location values as ordered Job Title Operating Areas.
-- Existing individual-industry route rows are retained. For each Job Title and
-- Location, the earliest route row becomes the active Operating Area row so its
-- route position and switching rules remain the defaults for that area.

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

DROP TEMPORARY TABLE IF EXISTS job_route_area_seed;
CREATE TEMPORARY TABLE job_route_area_seed (
    route_stop_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    operating_area VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO job_route_area_seed (route_stop_id, operating_area)
SELECT ranked.route_stop_id, ranked.operating_area
FROM (
    SELECT
        jrs.id AS route_stop_id,
        TRIM(i.location) AS operating_area,
        ROW_NUMBER() OVER (
            PARTITION BY jrs.job_id, jrs.railroad_id, TRIM(i.location)
            ORDER BY jrs.sequence_number, jrs.id
        ) AS area_row_number
    FROM job_route_stops jrs
    JOIN industries i
      ON i.id = jrs.industry_id
     AND i.railroad_id = jrs.railroad_id
     AND i.active = 1
    WHERE NULLIF(TRIM(COALESCE(jrs.operating_area, '')), '') IS NULL
      AND NULLIF(TRIM(COALESCE(i.location, '')), '') IS NOT NULL
      AND NOT EXISTS (
          SELECT 1
          FROM job_route_stops existing_area
          WHERE existing_area.job_id = jrs.job_id
            AND existing_area.railroad_id = jrs.railroad_id
            AND TRIM(existing_area.operating_area) = TRIM(i.location)
      )
) ranked
WHERE ranked.area_row_number = 1;

UPDATE job_route_stops jrs
JOIN job_route_area_seed seed ON seed.route_stop_id = jrs.id
SET jrs.operating_area = seed.operating_area;

DROP TEMPORARY TABLE job_route_area_seed;

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