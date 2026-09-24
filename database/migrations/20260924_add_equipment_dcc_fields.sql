-- Optional DCC metadata applies only to locomotive records at the application layer.
ALTER TABLE equipment
    ADD COLUMN dcc_address VARCHAR(10) NULL AFTER current_track,
    ADD COLUMN dcc_decoder VARCHAR(100) NULL AFTER dcc_address;
