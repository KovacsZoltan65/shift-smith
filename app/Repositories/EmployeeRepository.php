<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    protected CacheService $cacheService;
    protected string $tag;
    
    public function __construct(AppContainer $app, CacheService $cacheService)
    {
        parent::__construct($app);
        
        $this->cacheService = $cacheService;
        $this->tag          = Employee::getTag();
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
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };
        
        if($needCache) {
            try {
                $json = json_encode($request->all(), JSON_THROW_ON_ERROR);
            } catch(JsonException) {
                $json = md5(serialize($request->all()));
            }
            
            $cacheKey = $this->generateCacheKey($this->tag, $json);
            
            /** @var LengthAwarePaginator<int, Employee> $employees */
            $employees = $this->cacheService->remember(
                tag: $this->tag,
                key: $cacheKey,
                callback: $queryCallback
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
     * @return \App\Models\Employee
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
            $this->cacheService->forgetAll($this->tag);
            
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
            
            $employee->fill($data);
            $employee->save();
            $employee->refresh();
            
            $this->updateDefaultSettings($employee);

            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);
            
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
            
            $this->cacheService->forgetAll($this->tag);
            
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
            
            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);

            return $deleted;
        });
    }

    #[Override]
    public function getToSelect(): array
    {
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