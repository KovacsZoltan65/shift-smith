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

/**
 * Cég repository osztály
 * 
 * Adatbázis műveletek kezelése cégekhez.
 * Cache támogatással, verziókezeléssel és lapozással.
 * Prettus Repository pattern implementáció.
 */
class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a cégek listázásához */
    private const NS_COMPANIES_FETCH = 'companies.fetch';
    /** Cache namespace a cég selector listához */
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
     * Cégek listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Támogatja a keresést (név, email), rendezést és lapozást.
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, Company> Lapozott cég lista
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
     * Egy cég lekérése azonosító alapján
     * 
     * @param int $id Cég azonosító
     * @return Company Cég model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getCompany(int $id): Company
    {
        /** @var Company $company */
        $company = Company::findOrFail($id);
        
        return $company;
    }
    
    /**
     * Cég lekérése név alapján
     * 
     * @param string $name Cég neve
     * @return Company Cég model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getCompanyByName(string $name): Company
    {
        // Get the company by its name
        /** @var Company $company */
        $company = Company::where('name', '=', $name)->firstOrFail();

        return $company;
    }
    
    /**
     * Cégek lekérése select listához
     * 
     * Egyszerűsített cég lista (id, name) dropdown/select mezőkhöz.
     * Cache-elhető, csak aktív cégeket ad vissza.
     * Opcionálisan szűrhető csak olyan cégekre, amelyeknek van munkavállalója.
     * 
     * @param array{
     *   only_with_employees?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Cégek tömbje
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
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    /**
     * Új cég létrehozása
     * 
     * Tranzakcióban futtatva, alapértelmezett beállításokkal.
     * Létrehozás után cache invalidálás.
     * 
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null
     * } $data Cég adatok
     * @return Company Létrehozott cég
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
     * Cég adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Frissítés után cache invalidálás.
     * 
     * @param array{
     *    name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    active: boolean
     * } $data Frissítendő adatok
     * @param int $id Cég azonosító
     * @return Company Frissített cég
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
     * Több cég törlése egyszerre
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * 
     * @param list<int> $ids Cég azonosítók tömbje
     * @return int A törölt rekordok száma
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
     * Egy cég törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a kapcsolódó beállításokat és invalidálja a cache-t.
     * 
     * @param int $id Cég azonosító
     * @return bool Sikeres törlés esetén true
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
    
//    private function invalidateCompaniesSelectorCache(): void
//    {
//        // selector cache: v{version}:{hash(params)}
//        $namespace = 'selectors.companies';
//        $this->cacheVersionService->bump($namespace);
//
//        // opcionális: régi bejegyzések takarítása is
//        $this->cacheService->forgetAll('companies_select');
//    }
    
    /**
     * Cache invalidálás cég írási műveletek után
     * 
     * Növeli a verzió számokat a cég listázás és selector cache-ekhez.
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @return void
     */
    private function invalidateAfterCompanyWrite(): void
    {
        DB::afterCommit(function():void {
            // Companies lista oldal cache
            $this->cacheVersionService->bump(self::NS_COMPANIES_FETCH);

            // CompanySelector cache (mert a selector aktív cégeket listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_COMPANIES);
        });
    }
    
    /**
     * Alapértelmezett beállítások létrehozása új céghez
     * 
     * @param Company $company Cég model
     * @return void
     */
    private function createDefaultSettings(Company $company): void{}

    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param Company $company Cég model
     * @return void
     */
    private function updateDefaultSettings(Company $company): void{}

    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param Company $company Cég model
     * @return void
     */
    private function deleteDefaultSettings(Company $company): void{}
    
    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    public function model(): string
    {
        return Company::class;
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
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}