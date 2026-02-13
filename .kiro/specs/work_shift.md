# Module Spec Prompt — WorkShifts (Műszakok) — Companies baseline alapján

## Goal
Implement a full CRUD module named **WorkShifts** (HU: "Műszakok") using the existing **Companies** module as the canonical baseline.
The module must follow the same architecture and conventions:
- Controller → Service → Repository
- FormRequest validation for each write operation
- Policy authorization on all endpoints
- Consistent JSON responses for API routes
- Inertia + Vue 3 + PrimeVue UI with DataTable + modal CRUD
- Cache key/tag + cache version bumping the same way Companies does
- Pest Feature tests mirroring Companies tests
- No duplication: if a file already exists, UPDATE it; do not create a parallel duplicate.
- Multi-tenant scoped by `company_id` (like Companies ecosystem in this project)

## Constraints / Project Rules
- Laravel 12, MySQL
- Vue 3 + Inertia + Vite + Tailwind + PrimeVue
- Match existing patterns and naming conventions in the repo
- Authorization:
  - Use policy methods and `$this->authorize(...)` or `authorizeResource` like Companies
  - FormRequests `authorize()` must use permissions (e.g. `work_shifts.create`, `work_shifts.update`, etc.)
- Caching:
  - Use the same CacheService / cache tag strategy as Companies
  - Ensure cache versions are bumped on create/update/delete/bulkDelete
- Responses:
  - API endpoints return JSON with `{ message, data }` (mirror Companies)
  - Inertia pages return props similarly to Companies/Index page

## Entity Definition: WorkShift
Table: `work_shifts`
Columns:
- id (pk)
- company_id (fk companies.id, cascade on delete)
- name (string, max 100)
- name_lc (generated stored column: LOWER(name)) [for search/index like Companies]
- start_time (time)
- end_time (time)
- active (boolean, default true)
- timestamps
- softDeletes

Indexes:
- index(company_id, name_lc)

Validation rules:
- name: required|string|max:100
- start_time: required|date_format:H:i
- end_time: required|date_format:H:i|after:start_time
- active: boolean

IMPORTANT: Decide/assume for v1: end_time must be after start_time (no overnight shifts for now).
(If overnight shifts needed later, we’ll add a flag.)

## Permissions (Spatie)
Create permissions (or ensure they exist if already seeded):
- work_shifts.view
- work_shifts.create
- work_shifts.update
- work_shifts.delete

(If there is a central PermissionSeeder or RolePermission mapping, update it to include these.)

## Backend Tasks

### 1) Migration
Create migration for `work_shifts` with stored generated `name_lc`.
Ensure foreign key to companies.
Add soft deletes.
Add index on (company_id, name_lc).

### 2) Model
Create `App\Models\WorkShift`:
- fillable: company_id, name, start_time, end_time, active
- casts: active boolean (time cast consistent with project conventions)
- add SoftDeletes
- if project uses activitylog like Companies: integrate similarly (optional but prefer consistency)

### 3) Policy
Create `App\Policies\WorkShiftPolicy` mirroring Companies policy structure.
Methods:
- viewAny, view, create, update, delete, bulkDelete (if your baseline has bulk delete policy)
Use permissions: `work_shifts.view`, etc.

Register policy in AuthServiceProvider (if project uses explicit mapping) or match existing registration mechanism.

### 4) Repository Layer
Create:
- `app/Interfaces/WorkShiftRepositoryInterface.php`
- `app/Repositories/WorkShiftRepository.php`

Mirror Companies repository patterns:
- query builder with company scope
- search by `name_lc` using lowercase comparisons
- pagination with `per_page`
- filtering by `active` if requested
- fetch endpoint for selectors: small payload id+name with optional search

Repository methods (suggested):
- paginate(array $filters): LengthAwarePaginator
- findOrFailScoped(int $id, int $companyId): WorkShift
- create(array $data): WorkShift
- update(WorkShift $shift, array $data): WorkShift
- delete(WorkShift $shift): void
- bulkDelete(array $ids, int $companyId): int
- fetchForSelector(array $filters): Collection|array

### 5) Service Layer
Create `app/Services/WorkShiftService.php`:
- index($filters)
- fetch($filters)
- store($dto/array)
- update($id, $data)
- delete($id)
- bulkDelete($ids)

Apply caching exactly like Companies:
- list and fetch responses should be cacheable (tagged)
- on mutations bump cache versions / invalidate tags (same strategy as Companies)
- Use same cache key generator patterns

### 6) Controller
Create `app/Http/Controllers/WorkShiftController.php`
Mirror Companies controller:
- Inertia page `index()` returns `Pages/WorkShifts/Index`
- API endpoints:
  - `fetch()` for selector options
  - store/update/destroy (can be API or standard Inertia form posts, but keep same style as Companies)
- Use FormRequests
- Use policy authorize checks

### 7) FormRequests
Create folder: `app/Http/Requests/WorkShift/`
- IndexRequest (filters)
- FetchRequest (selector filters)
- StoreRequest
- UpdateRequest
- DeleteRequest
- BulkDeleteRequest

Authorization:
- StoreRequest -> `work_shifts.create`
- UpdateRequest -> `work_shifts.update`
- DeleteRequest -> `work_shifts.delete`
- Index/Fetch -> `work_shifts.view`

### 8) Routes
Add routes consistent with Companies:
- Inertia:
  - GET `/work-shifts` -> WorkShiftController@index
- API:
  - GET `/api/work-shifts/fetch` -> WorkShiftController@fetch
  - POST `/api/work-shifts` -> store
  - PUT `/api/work-shifts/{id}` -> update
  - DELETE `/api/work-shifts/{id}` -> destroy
  - POST `/api/work-shifts/bulk-delete` -> bulkDelete

Ensure route names match project conventions (`work_-_shifts.index`, `api.work-shifts.fetch`, etc.)
If Companies uses `Route::prefix('api')...`, follow that.

## Frontend Tasks (Inertia + Vue + PrimeVue)

Create folder: `resources/js/Pages/WorkShifts/`

### Index.vue
Mirror Companies/Index.vue layout and UX:
- DataTable listing shifts:
  - columns: name, start_time, end_time, active
- Filters:
  - search (InputText)
  - active (Select or Toggle)
  - per_page
  - (optional) CompanySelector if superadmin can switch companies (match your existing selector pattern)
- Actions:
  - Create (opens CreateModal)
  - Edit row action (opens EditModal)
  - Delete row action (ConfirmDialog + API call)
  - Bulk delete (multi select + confirm)

Use Toast messages consistent with Companies.

### CreateModal.vue / EditModal.vue
- fields: name, start_time, end_time, active
- validation error handling same as Companies modals
- submit via service layer (frontend services pattern) matching Companies

### Frontend Service (if you use it)
Create `resources/js/services/WorkShiftService.js` mirroring CompanyService:
- fetchList(params)
- fetchSelector(params)
- create(payload)
- update(id, payload)
- delete(id)
- bulkDelete(ids)

Must use your existing HttpClient wrapper and error normalization strategy.

## Tests (Pest Feature)
Create folder `tests/Feature/WorkShifts/`
Mirror Companies tests structure and assertions.
Required tests:
1) Index
- denies access without permission
- returns paginated results scoped to company
2) Store
- denies without permission
- validates required fields
- creates and bumps cache versions
3) Update
- denies without permission
- validates fields
- updates and bumps cache versions
4) Delete
- denies without permission
- soft deletes and bumps cache versions
5) BulkDelete
- denies without permission
- deletes multiple scoped ids and bumps cache versions
6) Fetch (selector)
- denies without view permission
- returns minimal payload, supports search

Tests must:
- use factories
- create 2 companies and ensure company scoping works
- verify cache version bump mechanism the same way Companies tests do

## Deliverables
Output the changes as a patch-style plan:
- List created/updated files with paths
- For each file: explain what was added/changed
- Provide full code for new files
- Provide diff snippets for modified files (routes, seeders, AuthServiceProvider, etc.)
- Ensure no duplicate pattern violations

## Acceptance Criteria
- All new endpoints follow Companies patterns
- All feature tests pass
- UI loads WorkShifts index and supports CRUD with modals
- Caching behaves like Companies (versions bump on mutations)
- Multi-tenant scoping enforced everywhere (repo/service/controller/tests)

Start implementing now.
