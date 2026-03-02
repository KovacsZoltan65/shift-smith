<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use App\Models\TenantGroup;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use App\Services\Cache\CacheVersionService;

/**
 * Adatbázis műveletek kezelése cégekhez.
 */
class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a cégek listázásához */
    private const NS_COMPANIES_FETCH = 'companies.fetch';
    /** Cache namespace HQ/landlord cég listához */
    private const NS_HQ_COMPANIES_FETCH = 'hq.companies.fetch';
    /** Cache namespace a cég selector listához */
    private const NS_SELECTORS_COMPANIES = 'selectors.companies';
    /** Cache namespace a dashboard KPI-khoz */
    private const NS_DASHBOARD_STATS = 'dashboard.stats';

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
     * Cégek listázása lapozással, szűréssel és rendezéssel.
     *
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_companies', false);
        $currentTenantId = TenantGroup::current()?->id;
        $user = $request->user();
        $isSuperadmin = $user !== null && method_exists($user, 'hasRole') && $user->hasRole('superadmin');
        
        $page = (int) $request->integer('page', 1);
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;
        
        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');
        
        $sortable = Company::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? $request->input('field')
            : null;
        
        $orderRaw = (string) $request->input('order', 'desc');
        $direction = strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';

        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);
        
        $queryCallback = function() use($term, $field, $direction, $perPage, $page, $appendQuery, $currentTenantId, $isSuperadmin): LengthAwarePaginator {
            $q = Company::query()
                ->when(
                    $currentTenantId !== null,
                    fn ($qq) => $qq->where('tenant_group_id', $currentTenantId)
                )
                // Landlord módban csak superadmin láthat globális listát.
                ->when(
                    $currentTenantId === null && !$isSuperadmin,
                    fn ($qq) => $qq->whereRaw('1 = 0')
                )
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
            'tenant_id' => $currentTenantId,
            'landlord_scope' => $currentTenantId === null
                ? ($isSuperadmin ? 'global_superadmin' : 'restricted_non_superadmin')
                : 'tenant_scoped',
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
     * HQ cégek globális listázása tenant scope nélkül.
     *
     * @return LengthAwarePaginator<int, Company>
     */
    public function fetchHq(Request $request): LengthAwarePaginator
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

        $orderRaw = (string) $request->input('order', 'desc');
        $direction = strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);

        $queryCallback = function () use ($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
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

        if (!$needCache) {
            /** @var LengthAwarePaginator<int, Company> $companies */
            $companies = $queryCallback();

            return $companies;
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
            'scope' => 'hq_global_landlord',
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_HQ_COMPANIES_FETCH);
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
     * Cég lekérése azonosító alapján.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompany(int $id): Company
    {
        /** @var Company $company */
        $company = Company::findOrFail($id);
        
        return $company;
    }
    
    /**
     * Cég lekérése név alapján.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompanyByName(string $name): Company
    {
        /** @var Company $company */
        $company = Company::where('name', '=', $name)->firstOrFail();

        return $company;
    }
    
    /**
     * Cégek lekérése select listához.
     *
     * @param array{only_with_employees?: bool} $params
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        $needCache = (bool) config('cache.enable_companyToSelect', false);
        $currentTenantId = TenantGroup::current()?->id;

        // normalize
        $params['only_with_employees'] = !empty($params['only_with_employees']);
        ksort($params);

        $onlyWithEmployees = (bool) $params['only_with_employees'];

        $queryCallback = function () use ($onlyWithEmployees, $currentTenantId): array {
            /** @var array<int, array{id: int, name: string}> $out */
            $out = Company::active()
                ->when(
                    $currentTenantId !== null,
                    fn ($q) => $q->where('tenant_group_id', $currentTenantId)
                )
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
            tag: self::NS_SELECTORS_COMPANIES,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    public function companyBelongsToActiveTenantGroup(int $companyId, int $tenantGroupId): bool
    {
        return Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->where('tenant_group_id', $tenantGroupId)
            ->exists();
    }

    /**
     * Új cég létrehozása.
     *
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   active?: bool
     * } $data
     */
    public function store(array $data): Company
    {
        return DB::transaction(function() use($data): Company {
            $tenantGroup = TenantGroup::query()->create([
                'name' => (string) $data['name'],
                'slug' => $this->makeUniqueTenantGroupSlug((string) $data['name']),
                'active' => true,
            ]);

            $data['tenant_group_id'] = $tenantGroup->id;

            /** @var Company $company */
            $company = Company::query()->create($data);
            
            $this->createDefaultSettings($company);
            
            // Cache ürítése
            $this->invalidateAfterCompanyWrite();
            
            return $company;
        });
    }

    private function makeUniqueTenantGroupSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'company';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (TenantGroup::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Cég adatainak frissítése.
     *
     * @param array{
     *    name: string,
     *    email?: string|null,
     *    address?: string|null,
     *    phone?: string|null,
     *    active?: bool
     * } $data
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
     * Több cég törlése egyszerre.
     *
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            $deleted = Company::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterCompanyWrite();
            
            return $deleted;
        });
    }
    
    /**
     * Egy cég törlése.
     */
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
    
    /**
     * Cache invalidálás cég írási műveletek után.
     */
    private function invalidateAfterCompanyWrite(): void
    {
        DB::afterCommit(function():void {
            // Companies lista oldal cache
            $this->cacheVersionService->bump(self::NS_COMPANIES_FETCH);
            $this->cacheVersionService->bump(self::NS_HQ_COMPANIES_FETCH);

            // CompanySelector cache (mert a selector aktív cégeket listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_COMPANIES);
            $this->cacheVersionService->bump(self::NS_DASHBOARD_STATS);
        });
    }
    
    /**
     * Alapértelmezett beállítások létrehozása új céghez.
     */
    private function createDefaultSettings(Company $company): void{}

    /**
     * Alapértelmezett beállítások frissítése.
     */
    private function updateDefaultSettings(Company $company): void{}

    /**
     * Alapértelmezett beállítások törlése.
     */
    private function deleteDefaultSettings(Company $company): void{}
    
    /**
     * Repository model osztály megadása.
     */
    public function model(): string
    {
        return Company::class;
    }

    /**
     * Repository inicializálás.
     */
    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
