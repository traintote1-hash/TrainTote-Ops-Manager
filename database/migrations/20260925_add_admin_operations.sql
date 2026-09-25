ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS user_subscriptions (
 user_id INT NOT NULL PRIMARY KEY,
 plan_code ENUM('free','pro','business') NOT NULL DEFAULT 'free',
 status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
 updated_by INT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS admin_audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT NULL, target_user_id INT NULL,
 event_type VARCHAR(80) NOT NULL, details JSON NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_admin_audit_created (created_at)
);
-- Set your administrator after migration:
-- UPDATE users SET is_admin=1 WHERE email='your-email@example.com';
