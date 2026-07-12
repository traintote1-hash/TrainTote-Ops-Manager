-- Reusable Job Title work scope, ordered route stops, and industry exchange rules.
-- Existing Job Titles default to Entire Railroad when no profile row exists.

CREATE TABLE IF NOT EXISTS job_operation_profiles (
    job_id INT NOT NULL,
    railroad_id INT NOT NULL,
    work_scope ENUM('entire_railroad','selected_route') NOT NULL DEFAULT 'entire_railroad',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_job_operation_profile_railroad (railroad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_route_stops (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    railroad_id INT NOT NULL,
    job_id INT NOT NULL,
    industry_id INT NOT NULL,
    sequence_number INT UNSIGNED NOT NULL,
    outbound_load_status ENUM('Any','Loaded','Empty') NOT NULL DEFAULT 'Any',
    inbound_load_status ENUM('Any','Loaded','Empty') NOT NULL DEFAULT 'Any',
    pull_destination_mode ENUM('operating_base','yard','staging_interchange','selected_location','next_compatible') NOT NULL DEFAULT 'yard',
    pull_destination_industry_id INT NULL,
    replacement_source_mode ENUM('operating_base','starting_cars','prepared_cut','staged_group','selected_location') NOT NULL DEFAULT 'starting_cars',
    replacement_source_industry_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_route_stop_industry (job_id, industry_id),
    UNIQUE KEY uq_job_route_stop_sequence (job_id, sequence_number),
    KEY idx_job_route_stop_railroad (railroad_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
