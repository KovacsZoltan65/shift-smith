# ShiftSmith Tenancy Audit

Date: 2026-03-09

## Summary

- Active tenant resolution is session-driven through `current_tenant_group_id`, `SessionTenantFinder`, and `InitializeTenantGroup`.
- Spatie current-tenant state is present, but enforcement was inconsistent before this change because missing tenant context was not centrally aborted across tenant-required routes.
- Company scoping exists in several repositories, but it is duplicated and not standardized behind one tenancy service.
- Cache namespacing is mostly tenant-aware, but it is inconsistent in shape and some repositories still use company-only namespaces.
- Repository layering is not fully respected: direct Eloquent queries exist in controllers and services.
- Schema support is partial: `companies` has `tenant_group_id`, but core domain tables such as `employees`, `work_schedules`, and `work_shifts` do not carry a direct `tenant_group_id`.

## Tenant Context Resolution

- `TenantGroup::current()` is backed by Spatie and is initialized in [app/Http/Middleware/InitializeTenantGroup.php](/c:/wamp64/www/km/shift-smith/app/Http/Middleware/InitializeTenantGroup.php).
- Resolution source is the session key `current_tenant_group_id` via [app/Tenancy/TenantFinder/SessionTenantFinder.php](/c:/wamp64/www/km/shift-smith/app/Tenancy/TenantFinder/SessionTenantFinder.php).
- Company validation is session-driven in [app/Support/CurrentCompanyContext.php](/c:/wamp64/www/km/shift-smith/app/Support/CurrentCompanyContext.php).
- Before this patch, tenant-required routes relied on `ensure.company` but did not have a dedicated tenant middleware. That allowed inconsistent handling when tenant context drifted or when repositories were called outside request middleware.
- This patch adds [app/Services/Tenant/TenantManager.php](/c:/wamp64/www/km/shift-smith/app/Services/Tenant/TenantManager.php) and [app/Http/Middleware/EnsureTenantContext.php](/c:/wamp64/www/km/shift-smith/app/Http/Middleware/EnsureTenantContext.php), and wires `ensure.tenant` into tenant-required route groups in [routes/web.php](/c:/wamp64/www/km/shift-smith/routes/web.php).

## Company Isolation

- `companies` are tenant-owned through `tenant_group_id`.
- `employees`, `work_schedules`, and `work_shifts` are company-scoped, but not directly tenant-scoped at schema level.
- Several repositories safely validate company ownership against the current tenant before querying company data:
  - [app/Repositories/WorkShiftRepository.php](/c:/wamp64/www/km/shift-smith/app/Repositories/WorkShiftRepository.php)
  - [app/Repositories/WorkScheduleRepository.php](/c:/wamp64/www/km/shift-smith/app/Repositories/WorkScheduleRepository.php)
  - [app/Repositories/Dashboard/DashboardRepository.php](/c:/wamp64/www/km/shift-smith/app/Repositories/Dashboard/DashboardRepository.php)
- Model-level reusable scopes are incomplete. `WorkSchedule` has `scopeForCompany()`, but a consistent `scopeForTenant()` pattern does not exist across the tenant-scoped models.

## Repository Layer Compliance

Violations found:

- Controller query bypass:
  - [app/Http/Controllers/EmployeeWorkPatternController.php](/c:/wamp64/www/km/shift-smith/app/Http/Controllers/EmployeeWorkPatternController.php)
  - [app/Http/Controllers/Admin/UserEmployeeController.php](/c:/wamp64/www/km/shift-smith/app/Http/Controllers/Admin/UserEmployeeController.php)
- Service query bypass:
  - [app/Services/EmployeeWorkPatternService.php](/c:/wamp64/www/km/shift-smith/app/Services/EmployeeWorkPatternService.php)
  - [app/Services/WorkShiftAssignmentService.php](/c:/wamp64/www/km/shift-smith/app/Services/WorkShiftAssignmentService.php)
  - [app/Services/Org/OrgHierarchyGenerator.php](/c:/wamp64/www/km/shift-smith/app/Services/Org/OrgHierarchyGenerator.php)

These should move query construction into repositories.

## Cache Isolation

- Cache infrastructure is mostly tenant-aware through:
  - [app/Services/CacheService.php](/c:/wamp64/www/km/shift-smith/app/Services/CacheService.php)
  - [app/Services/Cache/CacheVersionService.php](/c:/wamp64/www/km/shift-smith/app/Services/Cache/CacheVersionService.php)
- Strengths:
  - automatic `tenant:{tenant_group_id}:...` qualification exists
  - version bumping after mutations exists in many repositories
  - selector and dashboard caches already include tenant-aware namespaces in several places
- Gaps:
  - some repositories still use company-only namespaces such as `company:{company_id}:...`
  - cache tag naming is not standardized on `tenant:{tenant_group_id}:{module}:{key}`
  - invalidation strategy is mixed between versioning and tag flushing

## Cross-Tenant Safety

Direct query review found tenant-risk areas:

- `CompanyRepository::getCompany()` and `getCompanyByName()` are not tenant-scoped.
- `EmployeeRepository::getEmployee()`, `getEmployeeByName()`, `findOrFailForUpdate()`, `store()`, `update()`, `destroy()`, and related raw `findOrFail()` paths do not consistently gate on current tenant before resolving the target record.
- Controller/service direct `findOrFail()` calls bypass repository safety entirely in the files listed above.

These are not all exploitable from routes today because some flows pass scoped `company_id`, but they are architecture violations and future regression points.

## Database Schema Validation

### `companies`

- has `tenant_group_id`
- has index on `tenant_group_id`
- FK to `tenant_groups` exists via migration hardening

### `users`

- does not have `company_id`
- does not have `tenant_group_id`
- current access is mediated through pivots, not direct tenant columns

### `employees`

- has `company_id`
- `company_id` is constrained to `companies.id`
- no direct `tenant_group_id`
- no explicit standalone `company_id` index in the base migration beyond the FK-backed index

### `work_schedules`

- has `company_id`
- indexed by `company_id`
- no direct `tenant_group_id`

### `work_shifts`

- has `company_id`
- indexed by `company_id`
- no direct `tenant_group_id`

## Implemented Changes

- Added central tenant service:
  - [app/Services/Tenant/TenantManager.php](/c:/wamp64/www/km/shift-smith/app/Services/Tenant/TenantManager.php)
- Added tenant enforcement middleware:
  - [app/Http/Middleware/EnsureTenantContext.php](/c:/wamp64/www/km/shift-smith/app/Http/Middleware/EnsureTenantContext.php)
- Added reusable repository trait:
  - [app/Repositories/Concerns/TenantScopedRepository.php](/c:/wamp64/www/km/shift-smith/app/Repositories/Concerns/TenantScopedRepository.php)
- Added global helper:
  - [app/Support/helpers.php](/c:/wamp64/www/km/shift-smith/app/Support/helpers.php)
- Wired `ensure.tenant` into tenant-required route groups in [routes/web.php](/c:/wamp64/www/km/shift-smith/routes/web.php).
- Aligned `TenantContext` and `CurrentCompanyContext` with the central manager.

## Refactor Suggestions

1. Move all controller and service Eloquent queries into repositories.
2. Standardize every tenant-scoped repository on the new trait and `TenantManager`.
3. Add direct `tenant_group_id` to tenant-owned domain tables if multi-database readiness and auditing matter more than strict normalization.
4. Introduce model scopes such as `scopeForTenant()` and `scopeForTenantCompany()` on core domain models.
5. Normalize cache namespaces so every tenant cache resolves to `tenant:{tenant_group_id}:{module}:{key}` before hashing.
6. Add architecture tests that forbid `::query()`, `::all()`, and `DB::table()` usage in controllers and non-repository services.
