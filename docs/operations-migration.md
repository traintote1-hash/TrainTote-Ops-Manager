# Persistent Operations migration

Back up the database, then apply these files in order with the same MySQL account used by TrainTote:

1. `database/migrations/20260712_add_switch_completion_history.sql`
2. `database/migrations/20260712_add_persistent_operations.sql`
3. `database/migrations/20260712_add_job_routes_and_exchange_rules.sql`
4. `database/migrations/20260713_add_prepared_cut_car_count.sql`
5. `database/migrations/20260714_add_job_route_operating_areas.sql`
6. `database/migrations/20260715_expand_operations_lifecycle_statuses.sql`
7. `database/migrations/20260718_add_operations_repair_queue.sql`
8. `database/migrations/20260718_add_operations_fast_clock.sql`

All migrations are idempotent. The persistent Operations migration does not drop or rewrite the legacy `jobs`, `job_locomotives`, `job_industries`, `job_cars`, `operation_switch_sessions`, or `operation_switch_moves` tables. Existing completed switch history therefore remains available for rollback and audit.

The route/exchange migration is also idempotent. Existing Job Titles require no data migration: the absence of a profile row means **Entire Railroad**, preserving current behavior. Ordered route stops are stored separately and do not replace legacy `job_industries` associations.

The web application never creates permanent tables during a normal request. Deployments must apply the migration before using the new Operations pages.

The Repair Queue migration adds repair and dated repair-history tables. It imports the most recent historical Bad Order for each affected equipment item as an open record, without changing `equipment.active`, because historical serviceability cannot be inferred safely. New Bad Order completions use the existing `equipment.active` eligibility field to remove equipment from Operations; closing the repair restores the pre-repair value and never changes industry or track.

The Fast Clock migration adds optional clock state directly to each operating session. Disabled sessions make no clock requests. Configured start time and ratio become read-only once the clock first starts, so completed Session History retains the values used during the event.
