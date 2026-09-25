# Demo site update

`demo.traintote.com` uses `enter_demo.php` to create a temporary visitor account, clone rows from the seed railroad, log the visitor in, and send them to the dashboard.

## Update path

1. Back up the demo files and demo database.
2. Upload the current application files to the demo site.
3. Keep the demo site's `config/database.php`, `config/tt_auth_secret.php`, `config/openai.php`, and `uploads/` content.
4. Apply the database migrations below to the demo database in order.
5. Confirm the seed railroad still has `seed_data = 1`, and its seed industries, equipment, jobs, waybills, and prepared cuts are marked as seed rows where those tables support `seed_data`.
6. Open `https://demo.traintote.com/enter_demo.php` and confirm it creates a fresh temporary demo railroad.

## Migration order

Apply these after the original demo schema and seed data:

1. `database/migrations/20260711_add_industries_active.sql`
2. `database/migrations/20260712_add_switch_completion_history.sql`
3. `database/migrations/20260712_add_persistent_operations.sql`
4. `database/migrations/20260712_add_job_routes_and_exchange_rules.sql`
5. `database/migrations/20260713_add_prepared_cut_car_count.sql`
6. `database/migrations/20260714_add_job_route_operating_areas.sql`
7. `database/migrations/20260715_expand_operations_lifecycle_statuses.sql`
8. `database/migrations/20260718_add_auth_remember_tokens.sql`
9. `database/migrations/20260718_add_operations_repair_queue.sql`
10. `database/migrations/20260718_add_operations_fast_clock.sql`
11. `database/migrations/20260718_add_operations_dispatcher.sql`
12. `database/migrations/20260718_add_operations_module_settings.sql`
13. `database/migrations/20260718_add_operations_yardmaster.sql`
14. `database/migrations/20260719_add_session_crew_assignments.sql`
15. `database/migrations/20260923_add_session_invitations.sql`
16. `database/migrations/20260924_add_equipment_dcc_fields.sql`

## Demo-specific schema

The original demo database has demo-only fields that are not part of the normal production migrations:

- `users.demo_user`
- `users.expires_at`
- `railroads.seed_data`
- seed flags on the demo source tables, such as `industries.seed_data`, `equipment.seed_data`, `waybills.seed_data`, and `waybill_cycles.seed_data`

Do not remove those fields when updating the demo database. `enter_demo.php` still uses them to find and clone the seed layout.
