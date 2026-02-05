<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_EMPLOYEES_FETCH = 'employees.fetch';
    private const NS_SELECTORS_EMPLOYEES = 'selectors.employees';
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';
    
    public function __construct(
        AppContainer $app, 
        CacheService $cacheService, 
        CacheVersionService $cacheVersionService
    )
    {
        parent::__construct($app);
        
        $this->cacheService        = $cacheService;
        $this->tag                 = Employee::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }
    
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_employees', false);
        
        $page = (int) $request->integer('page', 1);
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;
        
        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');
        
        // ✅ company filter
        $companyId = $request->input('company_id');
        $companyId = ($companyId === null || $companyId === '') ? null : (int) $companyId;
        
        $sortable = Employee::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? $request->input('field')
            : null;
        
        $direction = strtolower($request->input('order', '')) === 'desc' ? 'desc' : 'asc';
        
        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);
        
        $queryCallback = function () use ($term, $companyId, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Employee::query()
                ->when($companyId, fn ($qq) => $qq->where('company_id', $companyId))
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        // ⚠️ DB-ben nincs "name" mező -> first/last + email
                        $q->whereRaw('LOWER(first_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(last_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(email) like ?', ["%{$term}%"]);
                        // opcionális: phone/position
                        // ->orWhereRaw('LOWER(phone) like ?', ["%{$term}%"])
                        // ->orWhereRaw('LOWER(position) like ?', ["%{$term}%"]);
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };
        
        if($needCache) {
            $paramsForKey = [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $term,          // már lowercased/null
                'field' => $field,          // már whitelistelt/null
                'order' => $direction,      // asc/desc
            ];
            ksort($paramsForKey);

            $namespace = $this->NS_EMPLOYEES_FETCH;
            $version = $this->cacheVersionService->get($namespace);
            
            $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
            $key  = "v{$version}:{$hash}";
            
            /** @var LengthAwarePaginator<int, Employee> $employees */
            $employees = $this->cacheService->remember(
                tag: $this->tag,
                key: $key,
                callback: $queryCallback,
                ttl: config('cache.ttl_fetch', 60)
            );
            
        } else {
            /** @var LengthAwarePaginator<int, Employee> $employees */
            $employees = $queryCallback();
        }
        
        return $employees;
        
    }
    
    public function findOrFailForUpdate(int $id): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()
            ->lockForUpdate()
            ->findOrFail($id);

        return $employee;
    }
    
    /**
     * Summary of getEmployee
     * @param int $id
     * @return Employee
     */
    public function getEmployee(int $id): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::findOrFail($id);
        
        return $employee;
    }
    
    /**
     * Summary of store
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null
     *   hired_at: string
     * } $data
     * @return Employee
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
     * Summary of update
     * @param array{
     *    first_name: string,
     *    last_name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    active: boolean
     * } $data
     * @param int $id
     * @return Employee
     */
    public function update(array $data, $id): Employee
    {
        return DB::transaction(function() use($data, $id) {
            /** @var Employee $employee */
            $employee = Employee::query()->lockForUpdate()->findOrFail($id);
            
            $oldCompany = $employee->company_id;
            
            $employee->fill($data);
            $employee->save();
            $employee->refresh();
            
            $this->updateDefaultSettings($employee);

            $companyChanged = array_key_exists('company_id', $data)
                && (int) $employee->company_id !== $oldCompanyId;
            
            // Cache ürítése
            $this->invalidateAfterEmployeeWrite($companyChanged);
            
            return $employee;
        });
    }
    
    /**
     * @param list<int> $ids
     * @return int
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
     * 
     * @param array $params
     * @return array
     */
    #[Override]
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_employeeToSelect', false);
        
        $queryCallback = function() {
            return Employee::active()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Company $c): array => [
                    'id'   => (int) $c->id, 
                    'name' => (string) $c->name,
                ])
                ->values()
                ->all();
        };
        
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        
        $namespace = $this->NS_SELECTORS_EMPLOYEES;
        $version = $this->cacheVersionService->get($namespace);

        // 👇 verzió a KEY-ben (a CacheService még eléteszi a tag-et)
        $cacheKey = "v{$version}:{$hash}";
        
        return $needCache
            ? $this->cacheService->remember(
                tag: 'employees_select',
                key: $cacheKey,
                callback: $queryCallback,
                ttl: 1800                 // 30 perc, vagy configból
            )
            : $queryCallback();
    }

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
        });
    }
    
    private function createDefaultSettings(Employee $employee): void{}

    private function updateDefaultSettings(Employee $employee): void{}

    private function deleteDefaultSettings(Employee $employee): void{}

    #[Override]
    public function model(): string
    {
        return Employee::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    
}