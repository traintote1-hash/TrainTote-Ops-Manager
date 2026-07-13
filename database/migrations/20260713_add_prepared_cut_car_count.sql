-- Separate prepared-cut Spot limits from later route-stop pickup limits.
ALTER TABLE operation_assignments
    ADD COLUMN prepared_cut_car_count INT UNSIGNED NOT NULL DEFAULT 10 AFTER requested_car_count;

UPDATE operation_assignments
SET prepared_cut_car_count = requested_car_count
WHERE start_method = 'prepared_cut';