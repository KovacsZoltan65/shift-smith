<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    public function __construct(AppContainer $app, CacheService $cacheService)
    {
        parent::__construct($app);
        
        $this->cacheService = $cacheService;
        $this->tag          = Company::getTag();
    }
    
    /**
     * 
     * @param Request $request
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_companies', false);
        
        $page = (int) $request->integer('page', 1);
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;
        
        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');
        
        $sortable = Company::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? $request->input('field')
            : null;
        
        $direction = strtolower($request->input('order', '')) === 'desc' ? 'desc' : 'asc';
        
        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);
        
        $queryCallback = function() use($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = Company::query()
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
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
            
            /** @var LengthAwarePaginator<int, Company> $companies */
            $companies = $this->cacheService->remember(
                tag: $this->tag,
                key: $cacheKey,
                callback: $queryCallback
            );
            
        } else {
            /** @var LengthAwarePaginator<int, Company> $companies */
            $companies = $queryCallback();
        }
        
        return $companies;
    }
    
    /**
     * Summary of getCompany
     * @param int $id
     * @return \App\Models\Company
     */
    public function getCompany(int $id): Company
    {
        /** @var Company $company */
        $company = Company::findOrFail($id);
        
        return $company;
    }
    
    public function getCompanyByName(string $name): Company
    {
        /** @var Company $company */
        $company = Company::where('name', '=', $name)->firstOrFail();
        
        return $company;
    }
    
    /**
     * Summary of store
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null
     * } $data
     * @return Company
     */
    public function store(array $data): Company
    {
        return DB::transaction(function() use($data): Company {
            /** @var Company $company */
            $company = Company::query()->create($data);
            
            $this->createDefaultSettings($company);
            
            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);
            
            return $company;
        });
    }
    
    /**
     * Summary of update
     * @param array{
     *    name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    active: boolean
     * } $data
     * @param int $id
     * @return Company
     */
    public function update(array $data, $id): Company
    {
        return DB::transaction(function() use($data, $id) {
            /** @var Company $company */
            $company = Company::query()->lockForUpdate()->findOrFail($id);
            
            $company->fill($data);
            $company->save();
            $company->refresh();
            
            $this->updateDefaultSettings($company);

            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);
            
            return $company;
        });
    }
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            $deleted = Company::query()->whereIn('id', $ids)->delete();
            
            $this->cacheService->forgetAll($this->tag);
            
            return $deleted;
        });
    }
    
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($id) {
            /** @var Company $company */
            $company = Company::query()->lockForUpdate()->findOrFail($id);
            
            $deleted = (bool) $company->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($company);
            
            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);

            return $deleted;
        });
    }
    
    /**
     * Summary of getToSelect
     * @return array<int, array{id: int, name: string}>
     */
    public function getToSelect(array $params): array
    {
        $onlyWithEmployees = $params['only_with_employees'] ?? false;
        
        return Company::active()
            ->when($onlyWithEmployees, fn ($q) => $q->whereHas('employees'))
            ->select(['id', 'name'])
            ->orderBy('name')->get()
            ->map(fn (Company $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])
            ->values()->all();
        /*
        return Company::active()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $c): array => [
                'id'   => (int) $c->id, 
                'name' => (string) $c->name,
            ])
            ->values()
            ->all();
        */
    }
    
    private function createDefaultSettings(Company $company): void{}

    private function updateDefaultSettings(Company $company): void{}

    private function deleteDefaultSettings(Company $company): void{}
    
    public function model(): string
    {
        return Company::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}