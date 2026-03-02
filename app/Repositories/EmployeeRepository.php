<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Models\EmployeeProfile;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Services\Access\CompanyAccessService;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use App\Data\Employee\EmployeeData;
use App\Data\Employee\EmployeeIndexData;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Munkavállaló repository osztály
 * 
 * Adatbázis műveletek kezelése munkavállalókhoz.
 * Cache támogatással, verziókezeléssel és lapozással.
 * Cég szűrés támogatással és cross-cache invalidálással.
 */
class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a munkavállalók listázásához */
    private const NS_EMPLOYEES_FETCH = 'employees.fetch';
    /** Cache namespace a munkavállaló selector listához */
    private const NS_SELECTORS_EMPLOYEES = 'selectors.employees';
    /** Cache namespace a cég selector listához (cross-invalidálás) */
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';
    /** Cache namespace a dashboard KPI-khoz */
    private const NS_DASHBOARD_STATS = 'dashboard.stats';
    
    public function __construct(
        AppContainer $app, 
        CacheService $cacheService, 
        CacheVersionService $cacheVersionService,
        private readonly CompanyAccessService $companyAccessService,
    )
    {
        parent::__construct($app);
        
        $this->cacheService = $cacheService;
        $this->tag = Employee::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }
    
    /**
     * Munkavállalók listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Támogatja a keresést (név, email), cég szűrést, rendezést és lapozást.
     * 
     * @param Request $request HTTP kérés (search, company_id, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, Employee> Lapozott munkavállaló lista
     */
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_employees', false);
        $currentTenantId = TenantGroup::current()?->id;

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        // ✅ company filter
        $companyIdRaw = $request->input('company_id');
        $companyId = ($companyIdRaw === null || $companyIdRaw === '') ? null : (int) $companyIdRaw;
        if ($companyId !== null) {
            $companyTenantId = Company::query()
                ->whereKey($companyId)
                ->value('tenant_group_id');

            if (! is_numeric($companyTenantId)) {
                $companyId = null;
            } else {
                $companyTenantId = (int) $companyTenantId;
                if (is_numeric($currentTenantId) && (int) $currentTenantId !== $companyTenantId) {
                    $companyId = null;
                }

                if (! is_numeric($currentTenantId)) {
                    $currentTenantId = $companyTenantId;
                }
            }
        }

        $sortable = Employee::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id']);

        $queryCallback = function () use ($term, $companyId, $field, $direction, $perPage, $page, $appendQuery, $currentTenantId): LengthAwarePaginator {
            $q = Employee::query()
                ->with('position:id,name')
                ->when(
                    is_numeric($currentTenantId),
                    fn ($qq) => $qq->whereHas('companies', fn ($cq) => $cq->where('tenant_group_id', (int) $currentTenantId)->where('company_employee.active', true))
                );

            if ($companyId !== null) {
                $q->whereHas('companies', function ($companyQuery) use ($companyId, $currentTenantId): void {
                    $companyQuery
                        ->whereKey($companyId)
                        ->where('companies.active', true)
                        ->where('company_employee.active', true)
                        ->when(
                            is_numeric($currentTenantId),
                            fn ($tenantScoped) => $tenantScoped->where('companies.tenant_group_id', (int) $currentTenantId)
                        );
                });
            }

            $q
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(first_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(last_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(email) like ?', ["%{$term}%"])
                            ->orWhereHas('position', fn ($pos) => $pos->whereRaw('LOWER(name) like ?', ["%{$term}%"]));
                    });
                });

            if ($field === 'name') {
                $q->orderBy('last_name', $direction)->orderBy('first_name', $direction);
            } elseif ($field) {
                $q->orderBy($field, $direction);
            } else {
                $q->orderByDesc('id');
            }

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache) {
            /** @var LengthAwarePaginator<int, Employee> $employees */
            $employees = $queryCallback();
            return $employees;
        }

        // ⚠️ fontos: a company_id is része a cache key-nek!
        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'company_id' => $companyId,
            'tenant_id' => $currentTenantId,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_EMPLOYEES_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, Employee> $employees */
        $employees = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $employees;
    }
    
    /**
     * Munkavállaló lekérése pesszimista zárolással
     * 
     * Frissítési műveletekhez használatos, hogy elkerülje a race condition-öket.
     * 
     * @param int $id Munkavállaló azonosító
     * @return Employee Munkavállaló model (zárolva)
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function findOrFailForUpdate(int $id): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()
            ->lockForUpdate()
            ->findOrFail($id);

        return $employee;
    }
    
    /**
     * Munkavállaló lekérése azonosító alapján
     * 
     * @param int $id Munkavállaló azonosító
     * @return Employee Munkavállaló model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getEmployee(int $id): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()->with('position:id,name')->findOrFail($id);
        
        return $employee;
    }

    public function findByIdInCompany(int $employeeId, int $companyId): ?Employee
    {
        $currentTenantId = TenantGroup::current()?->id;

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->with('position:id,name')
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->whereHas('company', function ($query) use ($companyId, $currentTenantId): void {
                $query->whereKey($companyId)->where('active', true);

                if (is_numeric($currentTenantId)) {
                    $query->where('tenant_group_id', (int) $currentTenantId);
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->first();

        return $employee;
    }

    public function findForLeaveEntitlement(int $employeeId): EmployeeLeaveProfileDTO
    {
        $currentTenantId = \App\Models\TenantGroup::current()?->id;

        /** @var Employee|object $employee */
        $employee = Employee::query()
            ->select([
                'employees.id',
                'employees.company_id',
                'profiles.birth_date',
                'profiles.children_count',
                'profiles.disabled_children_count',
                'profiles.is_disabled',
            ])
            ->leftJoin('employee_profiles as profiles', function ($join): void {
                $join->on('profiles.employee_id', '=', 'employees.id')
                    ->on('profiles.company_id', '=', 'employees.company_id');
            })
            ->whereKey($employeeId)
            ->whereHas('company', function ($query) use ($currentTenantId): void {
                $query->where('companies.active', true);

                if (is_numeric($currentTenantId)) {
                    $query->where('companies.tenant_group_id', (int) $currentTenantId);
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->firstOrFail();

        return new EmployeeLeaveProfileDTO(
            employee_id: (int) $employee->id,
            company_id: (int) $employee->company_id,
            birth_date: is_string($employee->birth_date) ? $employee->birth_date : null,
            children_count: max(0, (int) $employee->children_count),
            disabled_children_count: max(0, (int) $employee->disabled_children_count),
            is_disabled: (bool) $employee->is_disabled,
        );
    }

    /**
     * Munkavállaló lekérése keresztnév alapján
     * 
     * @param string $name Munkavállaló keresztneve
     * @return Employee Munkavállaló model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getEmployeeByName(string $name): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()->where('first_name', $name)->firstOrFail();
        
        return $employee;
    }
    
    /**
     * Új munkavállaló létrehozása
     * 
     * Tranzakcióban futtatva, alapértelmezett beállításokkal.
     * Létrehozás után cache invalidálás (beleértve a cég selector-t is).
     * 
     * @param array{
     *   first_name: string,
     *   last_name: string,
      *   address?: string|null,
      *   phone?: string|null,
      *   email?: string|null,
     *   position_id?: int|null,
     *   hired_at: string|null
     * } $data Munkavállaló adatok
     * @return Employee Létrehozott munkavállaló
     */
    public function store(array $data): Employee
    {
        return DB::transaction(function() use($data): Employee {
            /** @var Employee $employee */
            $employee = Employee::query()->create($data);
            
            $this->createDefaultSettings($employee);
            
            // Cache ürítése
            $this->invalidateAfterEmployeeWrite(true);
            
            return $employee;
        });
    }
    
    /**
     * Munkavállaló adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Ha a cég megváltozik, a cég selector cache is invalidálódik.
     * 
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   email?: string|null,
     *   address?: string|null,
     *   phone?: string|null,
     *   position_id?: int|null,
     *   hired_at?: string|null,
     *   active?: bool,
     *   company_id?: int|null
     * } $data Frissítendő adatok
     * @param int $id Munkavállaló azonosító
     * @return Employee Frissített munkavállaló
     */
    public function update(array $data, $id): Employee
    {
        return DB::transaction(function () use ($data, $id): Employee {
            /** @var Employee $employee */
            $employee = Employee::query()->lockForUpdate()->findOrFail($id);

            $oldCompanyId = (int) $employee->company_id;

            $employee->fill($data);
            $employee->save();
            $employee->refresh();

            $this->updateDefaultSettings($employee);

            $companyChanged = array_key_exists('company_id', $data)
                && (int) $employee->company_id !== $oldCompanyId;

            $this->invalidateAfterEmployeeWrite($companyChanged);

            return $employee;
        });
    }
    
    /**
     * Több munkavállaló törlése egyszerre
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * A cég selector cache is invalidálódik.
     * 
     * @param list<int> $ids Munkavállaló azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    #[Override]
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            $deleted = Employee::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterEmployeeWrite(true);
            
            return $deleted;
        });
    }

    /**
     * Egy munkavállaló törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a kapcsolódó beállításokat és invalidálja a cache-eket.
     * 
     * @param int $id Munkavállaló azonosító
     * @return bool Sikeres törlés esetén true
     */
    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($id) {
            /** @var Employee $employee */
            $employee = Employee::query()->lockForUpdate()->findOrFail($id);
            
            $deleted = (bool) $employee->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($employee);

            $this->invalidateAfterEmployeeWrite(true);

            return $deleted;
        });
    }

    /**
     * Munkavállalók lekérése select listához
     * 
     * Egyszerűsített munkavállaló lista (id, name) dropdown/select mezőkhöz.
     * Cache-elhető, opcionálisan csak aktív munkavállalókat ad vissza.
     * A név formátuma: "Vezetéknév Keresztnév".
     * 
     * @param array{
     *   company_id?: int|null,
     *   only_active?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Munkavállalók tömbje
     */
    #[Override]
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_employeeToSelect', false);

        // normalize params (jövőbiztos)
        $companyId = null;
        if (\array_key_exists('company_id', $params) && $params['company_id'] !== null && $params['company_id'] !== '') {
            $companyId = (int) $params['company_id'];
        }
        $params['company_id'] = $companyId;
        $params['only_active'] = \array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        ksort($params);

        $onlyActive = (bool) $params['only_active'];
        $companyId = $params['company_id'];

        $queryCallback = function () use ($onlyActive, $companyId): array {
            $q = Employee::query();
            if ($companyId !== null) {
                $this->companyAccessService->scopeEmployeesToCompany($q, $companyId);
            }

            if ($onlyActive) {
                $q->active(); // scopeActive
            }

            /** @var array<int, array{id:int, name:string}> $out */
            $out = $q->select(['id', 'first_name', 'last_name'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e): array => [
                    'id' => (int) $e->id,
                    'name' => trim((string) $e->last_name . ' ' . (string) $e->first_name),
                ])
                ->values()
                ->all();

            return $out;
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_EMPLOYEES);
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: self::NS_SELECTORS_EMPLOYEES,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    /**
     * @param array{
     *   required_daily_minutes?: int|null,
     *   month?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   search?: string|null,
     *   shift_ids?: list<int>,
     *   eligible_for_autoplan?: bool
     * } $params
     * @return array{
     *   data: array<int, array{id:int, full_name:string, name:string, work_pattern_summary:string}>,
     *   meta: array{
     *     total_employees:int,
     *     eligible_count:int,
     *     excluded_count:int,
     *     excluded_reasons: array{missing_pattern:int, not_matching_minutes:int, inactive:int},
     *     required_daily_minutes:int,
     *     month:string|null,
     *     date_from:string,
     *     date_to:string
     *   }
     * }
     */
    #[Override]
    public function getEligibleForAutoPlan(int $companyId, array $params): array
    {
        $needCache = (bool) config('cache.enable_employeeToSelect', false);

        $requiredDailyMinutes = (int) ($params['required_daily_minutes'] ?? 480);
        $month = \is_string($params['month'] ?? null) ? (string) $params['month'] : null;
        $dateFrom = \is_string($params['date_from'] ?? null) ? (string) $params['date_from'] : null;
        $dateTo = \is_string($params['date_to'] ?? null) ? (string) $params['date_to'] : null;
        $search = \is_string($params['search'] ?? null) ? trim((string) $params['search']) : null;
        $shiftIds = array_values(array_map('intval', (array) ($params['shift_ids'] ?? [])));
        $eligibleForAutoplan = (bool) ($params['eligible_for_autoplan'] ?? true);
        sort($shiftIds);

        if ($dateFrom !== null && $dateTo !== null) {
            $rangeStart = CarbonImmutable::parse($dateFrom)->toDateString();
            $rangeEnd = CarbonImmutable::parse($dateTo)->toDateString();
        } elseif ($month !== null && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month) === 1) {
            // Ha nincs explicit range, a hónap teljes intervallumával dolgozunk.
            $monthStart = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
            $rangeStart = $monthStart->toDateString();
            $rangeEnd = $monthStart->endOfMonth()->toDateString();
        } else {
            // Végső fallback: mai nap.
            $today = CarbonImmutable::today()->toDateString();
            $rangeStart = $today;
            $rangeEnd = $today;
        }

        $keyParams = [
            'company_id' => $companyId,
            'required_daily_minutes' => $requiredDailyMinutes,
            'month' => $month,
            'date_from' => $rangeStart,
            'date_to' => $rangeEnd,
            'search' => $search,
            'shift_ids' => $shiftIds,
            'eligible_for_autoplan' => $eligibleForAutoplan,
        ];
        ksort($keyParams);

        $queryCallback = function () use ($companyId, $requiredDailyMinutes, $month, $rangeStart, $rangeEnd, $search): array {
            $applySearch = static function ($query) use ($search): void {
                if ($search === null || $search === '') {
                    return;
                }

                $term = mb_strtolower($search, 'UTF-8');
                $query->where(function ($q) use ($term): void {
                    $q->whereRaw('LOWER(first_name) like ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) like ?', ["%{$term}%"])
                        ->orWhereRaw("LOWER(CONCAT(last_name, ' ', first_name)) like ?", ["%{$term}%"]);
                });
            };

            // Intervallum-átfedés: [date_from, date_to] metszi a kiválasztott range-et.
            $overlap = static function ($query) use ($companyId, $rangeStart, $rangeEnd): void {
                $query->where('company_id', $companyId)
                    ->whereDate('date_from', '<=', $rangeEnd)
                    ->where(function ($qq) use ($rangeStart): void {
                        $qq->whereNull('date_to')->orWhereDate('date_to', '>=', $rangeStart);
                    });
            };

            $eligibleQuery = Employee::query()
                ->where('active', true)
                ->whereHas('workPatterns', function ($q) use ($overlap, $companyId, $requiredDailyMinutes): void {
                    $overlap($q);
                    // Az átfedő hozzárendeléshez tartozó munkarend napi perce egyezzen.
                    $q
                        ->whereHas('workPattern', function ($wq) use ($companyId, $requiredDailyMinutes): void {
                            $wq->where('company_id', $companyId)
                                ->where('active', true)
                                ->where('daily_work_minutes', $requiredDailyMinutes);
                        });
                });
            $this->companyAccessService->scopeEmployeesToCompany($eligibleQuery, $companyId);
            $applySearch($eligibleQuery);

            /** @var array<int, array{id:int, full_name:string, name:string, work_pattern_summary:string}> $eligible */
            $eligible = (clone $eligibleQuery)
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e): array => [
                    'id' => (int) $e->id,
                    'full_name' => trim((string) $e->last_name . ' ' . (string) $e->first_name),
                    'name' => trim((string) $e->last_name . ' ' . (string) $e->first_name),
                    'work_pattern_summary' => '8h/day',
                ])
                ->values()
                ->all();

            $baseQuery = Employee::query();
            $this->companyAccessService->scopeEmployeesToCompany($baseQuery, $companyId);
            $applySearch($baseQuery);
            $totalEmployees = (int) (clone $baseQuery)->count();
            $inactiveCount = (int) (clone $baseQuery)->where('active', false)->count();
            $activeWithAnyOverlap = (int) (clone $baseQuery)
                ->where('active', true)
                ->whereHas('workPatterns', fn ($q) => $overlap($q))
                ->count();
            $eligibleCount = count($eligible);
            $excludedCount = max(0, $totalEmployees - $eligibleCount);
            $activeCount = max(0, $totalEmployees - $inactiveCount);
            $missingPatternCount = max(0, $activeCount - $activeWithAnyOverlap);
            $notMatchingMinutesCount = max(0, $activeWithAnyOverlap - $eligibleCount);

            return [
                'data' => $eligible,
                'meta' => [
                    'total_employees' => $totalEmployees,
                    'eligible_count' => $eligibleCount,
                    'excluded_count' => $excludedCount,
                    'excluded_reasons' => [
                        'missing_pattern' => $missingPatternCount,
                        'not_matching_minutes' => $notMatchingMinutesCount,
                        'inactive' => $inactiveCount,
                    ],
                    'required_daily_minutes' => $requiredDailyMinutes,
                    'month' => $month,
                    'date_from' => $rangeStart,
                    'date_to' => $rangeEnd,
                    // backward compatibility
                    'total_count' => $totalEmployees,
                    'breakdown' => [
                        'inactive' => $inactiveCount,
                        'not_target_daily_minutes' => $notMatchingMinutesCount,
                    ],
                    'target_daily_minutes' => $requiredDailyMinutes,
                ],
            ];
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_EMPLOYEES);
        $hash = hash('sha256', json_encode($keyParams, JSON_THROW_ON_ERROR));
        $key = "v{$version}:eligible_autoplan:{$hash}";

        /** @var array{
         *   data: array<int, array{id:int, full_name:string, name:string, work_pattern_summary:string}>,
         *   meta: array{
         *     total_employees:int,
         *     eligible_count:int,
         *     excluded_count:int,
         *     excluded_reasons: array{missing_pattern:int, not_matching_minutes:int, inactive:int},
         *     required_daily_minutes:int,
         *     month:string|null,
         *     date_from:string,
         *     date_to:string
         *   }
         * } $out
         */
        $out = $this->cacheService->remember(
            tag: self::NS_SELECTORS_EMPLOYEES,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );

        return $out;
    }

    /**
     * Cache invalidálás munkavállaló írási műveletek után
     * 
     * Növeli a verzió számokat a munkavállaló cache-ekhez.
     * Opcionálisan invalidálja a cég selector cache-t is (ha a cég munkavállalói változtak).
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @param bool $affectsCompanySelector Ha true, a cég selector cache is invalidálódik
     * @return void
     */
    private function invalidateAfterEmployeeWrite(bool $affectsCompanySelector = true): void
    {
        DB::afterCommit(function () use ($affectsCompanySelector): void {
            // Employees listázás (Index) cache
            $this->cacheVersionService->bump(self::NS_EMPLOYEES_FETCH);

            // EmployeeSelector cache (ha van)
            $this->cacheVersionService->bump(self::NS_SELECTORS_EMPLOYEES);

            // CompanySelector cache – only_with_employees miatt
            if ($affectsCompanySelector) {
                $this->cacheVersionService->bump(self::NS_SELECTORS_COMPANIES);
            }

            $this->cacheVersionService->bump(self::NS_DASHBOARD_STATS);
        });
    }
    
    /**
     * Alapértelmezett beállítások létrehozása új munkavállalóhoz
     * 
     * @param Employee $employee Munkavállaló model
     * @return void
     */
    private function createDefaultSettings(Employee $employee): void{}

    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param Employee $employee Munkavállaló model
     * @return void
     */
    private function updateDefaultSettings(Employee $employee): void{}

    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param Employee $employee Munkavállaló model
     * @return void
     */
    private function deleteDefaultSettings(Employee $employee): void{}

    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    #[Override]
    public function model(): string
    {
        return Employee::class;
    }

    /**
     * Repository inicializálás
     * 
     * Criteria-k regisztrálása (pl. query string alapú szűrés).
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    
}
