# Persistent Operations migration

Back up the database, then apply these files in order with the same MySQL account used by TrainTote:

1. `database/migrations/20260712_add_switch_completion_history.sql`
2. `database/migrations/20260712_add_persistent_operations.sql`
3. `database/migrations/20260712_add_job_routes_and_exchange_rules.sql`

All migrations are idempotent. The persistent Operations migration does not drop or rewrite the legacy `jobs`, `job_locomotives`, `job_industries`, `job_cars`, `operation_switch_sessions`, or `operation_switch_moves` tables. Existing completed switch history therefore remains available for rollback and audit.

The route/exchange migration is also idempotent. Existing Job Titles require no data migration: the absence of a profile row means **Entire Railroad**, preserving current behavior. Ordered route stops are stored separately and do not replace legacy `job_industries` associations.

The web application never creates permanent tables during a normal request. Deployments must apply the migration before using the new Operations pages.
