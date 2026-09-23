-- Run after the existing Operations migrations. No changes to owner accounts.
CREATE TABLE IF NOT EXISTS operation_session_invites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    invite_type ENUM('email','qr') NOT NULL,
    email VARCHAR(255) NULL,
    assignment_id BIGINT UNSIGNED NULL,
    created_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    claimed_at DATETIME NULL,
    UNIQUE KEY uq_session_invite_token (token_hash),
    KEY idx_session_invite_created (session_id, created_at),
    CONSTRAINT fk_session_invite_session FOREIGN KEY (session_id) REFERENCES operating_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operation_session_participants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    invite_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    access_hash CHAR(64) NOT NULL,
    status ENUM('pending','approved','revoked') NOT NULL DEFAULT 'pending',
    assignment_id BIGINT UNSIGNED NULL,
    joined_at DATETIME NOT NULL,
    UNIQUE KEY uq_session_participant_access (access_hash),
    KEY idx_session_participant_invite (invite_id),
    CONSTRAINT fk_session_participant_invite FOREIGN KEY (invite_id) REFERENCES operation_session_invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
