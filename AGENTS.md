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

All user-visible UI text and backend response messages must come from these shared JSON files.

Do not hardcode visible labels, button text, dialog text, toast text, menu text, page titles, table headers, placeholders, status labels, or backend `message` strings if they are intended to appear in the UI.

Internal-only fallback strings that are not expected to appear in normal UI flow may remain untranslated, but any fallback that can realistically surface to the user must be translated.

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

Prefer page- or domain-based keys over ad hoc UI-only namespaces.

Use the same key for the same visible concept across backend, page title, and menu whenever possible.

Examples:

- `companies.title`
- `users.title`
- `work_shifts.title`

Avoid duplicated parallel keys like:

- `menu.items.companies`
- `companies.page_title`
- `companies.header`

when they represent the same visible label.

If a shared generic key already exists for the same visible label, reuse it instead of creating a domain-specific duplicate.

Examples:

- use `delete`, not `companies.actions.delete_one`
- use `columns.name`, not `companies.form.name`
- use `columns.email`, not `companies.form.email`
- use `columns.phone`, not `companies.form.phone`
- use `columns.active`, not `companies.form.active`

Create a new domain key only when the text is semantically different or needs domain-specific wording.

---

## Backend usage

Always use the Laravel translation helper:

\_\_('employees.title')

Example:

return \_\_('employees.created_successfully');

If a backend response `message` is shown in the UI, it must also use shared translation keys.

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

Important:

- In Vue templates, use `$t(...)`
- In `<script setup>`, use `trans(...)`
- Do not use `$t(...)` directly inside `<script setup>` logic

For static definitions loaded at module import time, do not translate eagerly during file load if the value must react to locale changes.

Instead:

- store translation keys in the definition
- resolve them at runtime in a computed/composable/component layer

This rule is especially important for:

- menu definitions
- static config arrays
- shared action definitions
- table column metadata

---

## Placeholders

Use Laravel-compatible placeholders:

"employees.welcome": "Welcome :name"

Backend:

\_\_('employees.welcome', ['name' => $user->name])

Frontend:

trans('employees.welcome', { name: user.name })

---

## UI Localization Scope

These must be localized if visible to the user:

- page titles
- menu group labels and menu item labels
- button labels
- dialog headers
- dialog body text
- confirm accept/reject labels
- toast summary/detail text
- empty/loading states
- table headers
- form labels
- placeholders
- status labels
- tooltip text
- backend API `message` values shown by the frontend

These usually do not need localization unless they surface in the UI:

- developer comments
- logs
- internal variable names
- purely defensive fallback strings that never reach the user in normal flow

When in doubt, decide based on whether the text can appear on screen for the end user.

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
