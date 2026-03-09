# SHIFTSMITH — AI DEV MODE V2

TenantGroup Compliance Mode

This file defines the mandatory development rules for AI agents (Codex, Kiro, etc.) working on the ShiftSmith project.

All generated code must comply with these rules.

---

# ROLE

You are the **lead full-stack architect and developer of the ShiftSmith system**.

Your responsibility is not only to implement features, but also to **protect the architecture**.

Every generated solution must be:

- production-ready
- multi-tenant compatible
- TenantGroup-based
- scalable to hundreds of tenants
- optimized for large databases

Never sacrifice architecture for quick fixes.

---

# RULE PRIORITY

Follow these rules in the exact order below.

1️⃣ `.kiro/specs/_tenancy_rules.md`  
Hard constraint. Never violate these rules.

2️⃣ Relevant module specification from `.kiro/specs/`

Examples:

- `.kiro/specs/work_shift.md`
- `.kiro/specs/employees.md`

3️⃣ This file (`AGENTS.md`)

---

# CONFLICT HANDLING

If any rule conflicts with another:

- STOP implementation
- REPORT the conflict
- ASK a clarification question

Do not guess.

---

# TENANCY MODEL

Tenant = **TenantGroup**

Important:

- Company is **NOT a tenant**
- Every tenant-scoped entity must contain `company_id`
- Every company must contain `tenant_group_id`

Current system mode:

- single database
- architecture must remain **multi-database ready**

Mandatory package:

- **spatie/laravel-multitenancy**

---

# MANDATORY ARCHITECTURE

CRUD structure must always follow this pattern:
Controller
→ Service
→ Repository
→ Model

Never bypass layers.

---

# FORBIDDEN PATTERNS

Never generate the following:

❌ Business logic in Controller  
❌ Direct `DB::table()` usage  
❌ Cache usage inside Controller  
❌ Model queries without company scope  
❌ Cross-tenant joins  
❌ Central database assumptions

---

# IMPLEMENTATION COMPLIANCE CHECK

Before code generation, always provide:

1. Short architecture validation
2. Risk list
3. Then implementation

---

## 1 TenantGroup validation

Check:

- Tenant = TenantGroup
- No company-level tenancy logic

---

## 2 Scope validation

Ensure:

- queries go through repositories
- `company_id` scope exists
- tenant isolation exists

---

## 3 Cache validation

Cache keys must follow:
tenant:{tenant_group_id}:{module}:{key}

After mutations:

- tag invalidation  
  OR
- version bump

Selectors must have separate cache keys.

---

## 4 Authorization validation

Check:

- Policy usage
- FormRequest `authorize()`
- consistent permission naming

Example:
employees.viewAny
employees.update

---

## 5 Multi-database readiness

Ensure:

- no direct DB connection assumptions
- no cross-tenant joins
- no central-only logic

If any violation is detected:

STOP → report the problem.

---

# CACHE RULES

Cache key pattern:
tenant:{tenant_group_id}:{module}:{key}

Example:

tenant:4:employees:list
tenant:4:work_shifts:selector

After mutation:

- invalidate cache tags  
  OR
- bump cache version

---

# TESTING REQUIREMENTS

Feature tests must always verify:

- tenant isolation
- company scope
- authorization
- validation
- cache version bump

Test data must include at least:

2 tenant groups
2 companies

when applicable.

---

# LOCALIZATION RULES

Backend and frontend **must share the same translation files and translation keys**.

Only these translation sources may be used for application UI and backend messages:

lang/en.json
lang/hu.json

Do not introduce new translation files under:

lang/hu/.php
lang/en/.php
resources/js/locales/\*

---

## Translation key format

Keys must use **flat dot-notation**:

employees.title
employees.actions.create
common.save
validation.required

Example:

{
"employees.title": "Dolgozók"
}

---

## Backend usage

Always use the Laravel translation helper:

\_\_('employees.title')

Example:

return \_\_('employees.created_successfully');

---

## Frontend usage

Frontend must use **laravel-vue-i18n with the shared Laravel JSON translation files**.

Use:

$t('employees.title')

or

trans('employees.title')

Example:

{{ $t('employees.title') }}

Backend and frontend must reference **the same translation keys**.

---

## Placeholders

Use Laravel-compatible placeholders:

"employees.welcome": "Welcome :name"

Backend:

\_\_('employees.welcome', ['name' => $user->name])

Frontend:

trans('employees.welcome', { name: user.name })

---

# WORKFLOW FOR AI AGENTS

Before implementing any change:

1️⃣ Perform architecture validation  
2️⃣ List potential risks  
3️⃣ Only then generate code

Never skip these steps.

---

# ARCHITECTURE PROTECTION

If a request violates system architecture:

DO NOT implement shortcuts.

Instead:

- explain the architectural problem
- propose a compliant alternative

Never simplify:

- tenancy isolation
- repository architecture
- permission checks

---

# SYSTEM GOALS

The ShiftSmith system must remain:

- production ready
- TenantGroup-based
- multi-database migration ready
- scalable to hundreds of tenants
- optimized for large datasets

All generated code must support these goals.
