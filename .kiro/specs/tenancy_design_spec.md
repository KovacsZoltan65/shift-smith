# Tenancy Design Spec (TenantGroup-alapú, Single DB now, Multi-DB ready)

This document is the implementation design layer between:

- `.kiro/specs/_tenancy_rules.md` (hard constraints)
  and
- module specs (e.g. `work_shift.md`)

The system runs in **single database** mode currently, but must be designed **multi-database ready**.

---

## 1) Definitions

### Tenant

Tenant = `TenantGroup` (Cégcsoport)

- One TenantGroup can contain multiple Companies
- A User can belong to multiple Companies within the same TenantGroup
- TenantGroup is the future multi-database separation unit
- Company is NOT a tenant

### Scope levels

- Tenant scope: `tenant_group_id`
- Company scope: `company_id`

---

## 2) Data model changes (NOW)

### 2.1 New table: tenant_groups

`tenant_groups`

- id (pk)
- name (string, max 150)
- slug (string, max 150, unique)
- database_name (string, nullable) // future: multi-db mode
- active (bool, default true)
- created_at
- updated_at
- deleted_at (optional, if you want SoftDeletes here)

Indexes:

- unique(slug)
- index(active)

### 2.2 Companies change

`companies`

- add `tenant_group_id` (fk → tenant_groups.id)
- index(tenant_group_id)
- index(tenant_group_id, name) (optional)

Rule:

- Every Company MUST belong to exactly one TenantGroup

### 2.3 Users / memberships

Users remain landlord/global.

Membership stays:
`company_user`

- user_id
- company_id

TenantGroup membership is derived via Company → tenant_group_id.

---

## 3) Runtime context & session state (NOW)

### 3.1 Required session keys

- `current_company_id`
- `current_tenant_group_id`

Rule:

- You must never rely on `current_company_id` without also having `current_tenant_group_id`.

### 3.2 Context resolution flow

1. Login
2. Determine accessible companies for user
3. If 1 company -> auto-select
4. If multiple -> CompanySelector modal
5. On selection:
    - store `current_company_id`
    - resolve `tenant_group_id` from selected company
    - store `current_tenant_group_id`
6. Make tenant current via Spatie multitenancy (TenantGroup)

---

## 4) Spatie Multitenancy setup (Single DB mode) (NOW)

### 4.1 Tenant model

Tenant model: `TenantGroup`

### 4.2 Tenant finder

Tenant must be resolved from session `current_tenant_group_id`.

Finder behavior:

- if missing -> tenant is not initialized (or redirect to company selection)
- if present -> load TenantGroup and make current

### 4.3 Tasks (NOW)

Tasks prepare environment per tenant. Minimal required tasks:

- Cache prefix / tags:
    - Ensure CacheService keys include `tenant:{tenant_group_id}:...`
- Optional tasks if used later:
    - Filesystem tenant path prefix (storage per tenant group)
    - Queue tenant-aware context (jobs should run under the correct tenant)

Important:

- Do NOT switch database connection yet (single DB now)

---

## 5) Query enforcement (NOW)

### 5.1 Mandatory repository scoping

All tenant-scope repositories MUST:

- accept company_id as input OR derive it from current company context
- apply company scope to every query
- never allow unscoped access

### 5.2 Tenant isolation expectation

Even in single DB mode:

- tenant isolation is guaranteed via company_id + tenant_group_id relationship

Rule:

- Any request working with tenant-scope data MUST validate that
  the selected company belongs to the current tenant group.

---

## 6) Cache strategy (NOW)

### 6.1 Key format (mandatory)

All cache keys must follow:

`tenant:{tenant_group_id}:{module}:{key}`

Selector caches:
`tenant:{tenant_group_id}:selector:{entity}:{key}`

### 6.2 Version bump

Mutations must bump cache versions at least on:

- tenant scope (tenant group)
- and/or module scope (tag)

---

## 7) Authorization (NOW)

### 7.1 Policies

All endpoints must use Policies.

### 7.2 FormRequest authorize()

Write operations must authorize via permission strings (Spatie Permission).

---

## 8) Landlord vs Tenant data classification (NOW)

### Landlord (global, always central)

- users
- tenant_groups
- global permission definitions (if you keep them global)
- system/app settings

### Tenant-scope (scoped by company_id, within a tenant group)

- companies
- employees
- schedules / shifts / assignments
- activity logs (tenant-aware)
- settings (company/user level)

Note:
Even if stored in single DB, tenant-scope data MUST behave as if it could be moved.

---

## 9) Future: Single DB → Multi DB migration (LATER)

### 9.1 Migration unit

Migration unit is TenantGroup.

- One tenant group → one database
- Multiple companies live inside the same tenant DB (company_id still required)

### 9.2 Database selection

TenantGroup.database_name will become mandatory for migrated groups.

### 9.3 Command (future)

`php artisan tenancy:migrate-to-multi-db {tenant_group_id}`

Workflow:

1. Lock tenant group
2. Create tenant database
3. Run tenant migrations
4. Chunk-copy tenant-scope tables for that tenant group
5. Verify integrity (counts + sampling)
6. Set database_name
7. Enable SwitchTenantDatabaseTask for that tenant group
8. Unlock

No automatic “merge back” is supported.

---

## 10) Acceptance criteria

- TenantGroup domain is introduced and enforced
- Session state contains both current_company_id and current_tenant_group_id
- Spatie multitenancy makes TenantGroup current (single DB)
- Repositories remain company-scoped and tenant-safe
- Cache keys are tenant-prefixed and version-bumped on mutation
- System is ready for future tenant DB switching by TenantGroup
