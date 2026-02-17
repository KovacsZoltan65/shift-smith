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
    
    public function __construct(
        AppContainer $app, 
        CacheService $cacheService, 
        CacheVersionService $cacheVersionService
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

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        // ✅ company filter
        $companyIdRaw = $request->input('company_id');
        $companyId = ($companyIdRaw === null || $companyIdRaw === '') ? null : (int) $companyIdRaw;

        $sortable = Employee::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $direction = strtolower((string) $request->input('order', '')) === 'desc' ? 'desc' : 'asc';

        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id']);

        $queryCallback = function () use ($term, $companyId, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Employee::query()
                ->when($companyId, fn ($qq) => $qq->where('company_id', $companyId))
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(first_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(last_name) like ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(email) like ?', ["%{$term}%"]);
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

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
        $employee = Employee::findOrFail($id);
        
        return $employee;
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
     *   only_active?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Munkavállalók tömbje
     */
    #[Override]
    public function getToSelect(array $params = []): array
    {
        $needCache = (bool) config('cache.enable_employeeToSelect', false);

        // normalize params (jövőbiztos)
        $params['only_active'] = \array_key_exists('only_active', $params) ? (bool) $params['only_active'] : true;
        ksort($params);

        $onlyActive = (bool) $params['only_active'];

        $queryCallback = function () use ($onlyActive): array {
            $q = Employee::query();

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