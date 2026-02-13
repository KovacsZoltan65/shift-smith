# Prompt — WorkShifts permissions + seed + role mapping (Spatie)

## Goal
Add/ensure Spatie permissions for the new WorkShifts module and wire them into seeders and role-permission mapping, matching existing project conventions (same approach as Companies).

## Tasks
1) Identify the project's current permission seeding mechanism:
   - Look for seeders such as:
     - Database\Seeders\PermissionSeeder
     - Database\Seeders\RolePermissionSeeder
     - Database\Seeders\UserRoleSeeder
     - or a central seeder orchestrator
   - Do NOT create a parallel new system. Update existing seeders.

2) Ensure the following permissions exist (guard: web):
   - work_shifts.view
   - work_shifts.create
   - work_shifts.update
   - work_shifts.delete

3) Add them to default roles following the Companies baseline:
   - superadmin: all permissions
   - company_admin: view/create/update/delete (if that’s how Companies does it)
   - user/basic role: typically view only (match Companies pattern)
   If there is a "role matrix" in seeders, integrate WorkShifts into it.

4) Ensure idempotency:
   - Use firstOrCreate/updateOrCreate patterns
   - Do not hardcode IDs
   - Do not break existing environments

5) Add tests (optional but preferred):
   - Verify permissions exist after seed
   - Verify a role gets assigned these permissions (if your suite covers seed behavior)

## Deliverables
- List all modified seeder files and show diffs
- Show the final permission list insertion logic
- Show how role mapping was updated
- Ensure running `php artisan db:seed` produces the permissions and mappings without duplicates
