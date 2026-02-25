# Global Tenancy Rules (Mandatory)

⚠️ These rules are mandatory for ALL implementations and ALL module specs.

The system is currently single-database, but EVERYTHING must be implemented multi-database ready.

---

## 1. Domain principle

Tenant = TenantGroup (Cégcsoport)

- One TenantGroup can contain multiple Companies
- One User can belong to multiple Companies within the same TenantGroup
- TenantGroup is the future multi-database separation unit
- Company is NOT a tenant

---

## 2. Data model rules

All tenant-scope entities MUST have:

- `company_id` (required)
- Company MUST have `tenant_group_id` (required)

Forbidden:

- Treating Company as tenant
- Tenancy logic based only on session `company_id` without `tenant_group_id`

---

## 3. Query rules

Forbidden:

- Naked `DB::table()` usage
- Unscoped `Model::query()` that can leak cross-company / cross-tenant data
- Cross-tenant JOINs

Mandatory:

- Controller → Service → Repository
- Tenant context must be applied
- Company scope enforcement is required everywhere

---

## 4. Cache rules

All cache keys MUST be tenant-aware:

`tenant:{tenant_group_id}:...`

Selector caches MUST use separate namespace.

All mutations MUST bump tenant-level cache versions.

---

## 5. Spatie Multitenancy

Spatie multitenancy is installed and MUST be used.

Current mode:
Single database tenancy.

Current tenant = TenantGroup.

Forbidden:

- Custom “mini tenancy” solutions
- Omitting TenantGroup from session/state

---

## 6. Multi-database readiness requirement

Code MUST NOT:

- Assume all data is in a single database
- Directly depend on a central connection for tenant-scope entities
- Assume globally unique IDs across tenants

---

## 7. Forbidden patterns (must fail review)

- Any repository method that can query without company scope
- Any service method that caches without tenant prefix
- Any endpoint that authorizes without policy / permission checks
- Any “quick fix” query in controller using DB facade

---

## 8. PR checklist (mandatory)

Before considering a feature done:

- [ ] Tenant = TenantGroup is respected everywhere
- [ ] All tenant-scope tables are scoped by company_id
- [ ] All queries go through repository and are scoped
- [ ] Cache keys are tenant-aware and versions bump on mutations
- [ ] Policies + FormRequest authorize() are in place
- [ ] Tests cover tenant isolation (at least 2 companies, 2 tenant groups when relevant)
