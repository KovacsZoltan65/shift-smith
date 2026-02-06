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
use App\Services\Cache\CacheVersionService;

class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_COMPANIES_FETCH = 'companies.fetch';
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';

    public function __construct(
        AppContainer $app, 
        CacheService $cacheService, 
        CacheVersionService $cacheVersionService
    )
    {
        parent::__construct($app);
        
        $this->cacheService = $cacheService;
        $this->tag = Company::getTag();
        $this->cacheVersionService = $cacheVersionService;
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
        
        //$direction = strtolower($request->input('order', '')) === 'desc' ? 'desc' : 'asc';
        
        $orderRaw = (string) $request->input('order', 'desc');
        $direction = strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';

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
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));
            
            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);
            
            return $paginator;
        };
        
        if(!$needCache) {
            /** @var LengthAwarePaginator<int, Company> $companies */
            $companies = $queryCallback();
            
            return $companies;
        }
        
        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,      // lowercased/null
            'field' => $field,      // whitelistelt/null
            'order' => $direction,  // asc/desc
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_COMPANIES_FETCH);
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, Company> $companies */
        $companies = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

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
    
    /**
     * Summary of getCompanyByName
     * @param string $name
     * @return \App\Models\Company
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompanyByName(string $name): Company
    {
        // Get the company by its name
        /** @var Company $company */
        $company = Company::where('name', '=', $name)->firstOrFail();

        return $company;
    }
    
    /**
     * @param array{
     *   only_with_employees?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        $needCache = (bool) config('cache.enable_companyToSelect', false);

        // normalize
        $params['only_with_employees'] = !empty($params['only_with_employees']);
        ksort($params);

        $onlyWithEmployees = (bool) $params['only_with_employees'];

        $queryCallback = function () use ($onlyWithEmployees): array {
            /** @var array<int, array{id: int, name: string}> $out */
            $out = Company::active()
                ->when($onlyWithEmployees, fn ($q) => $q->whereHas('employees'))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Company $c): array => ['id' => (int) $c->id, 'name' => (string) $c->name])
                ->values()
                ->all();

            return $out;
        };

        if (!$needCache) {
            return $queryCallback();
        }
        
        $version = $this->cacheVersionService->get(self::NS_SELECTORS_COMPANIES);
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: 'companies_select',
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_companyToSelect', 1800)
        );
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
            $this->invalidateAfterCompanyWrite();
            
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
            $this->invalidateAfterCompanyWrite();
            
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
            
            $this->invalidateAfterCompanyWrite();
            
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
            $this->invalidateAfterCompanyWrite();

            return $deleted;
        });
    }
    
//    private function invalidateCompaniesSelectorCache(): void
//    {
//        // selector cache: v{version}:{hash(params)}
//        $namespace = 'selectors.companies';
//        $this->cacheVersionService->bump($namespace);
//
//        // opcionális: régi bejegyzések takarítása is
//        $this->cacheService->forgetAll('companies_select');
//    }
    
    private function invalidateAfterCompanyWrite(): void
    {
        DB::afterCommit(function():void {
            // Companies lista oldal cache
            $this->cacheVersionService->bump(self::NS_COMPANIES_FETCH);

            // CompanySelector cache (mert a selector aktív cégeket listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_COMPANIES);
        });
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