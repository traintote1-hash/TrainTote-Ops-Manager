-- Align service plans with public pricing tiers.
ALTER TABLE user_subscriptions
  MODIFY plan_code ENUM('free','plus','pro','business') NOT NULL DEFAULT 'free';

UPDATE user_subscriptions
SET plan_code = 'plus'
WHERE plan_code = 'pro';

UPDATE user_subscriptions
SET plan_code = 'pro'
WHERE plan_code = 'business';

ALTER TABLE user_subscriptions
  MODIFY plan_code ENUM('free','plus','pro') NOT NULL DEFAULT 'free';
