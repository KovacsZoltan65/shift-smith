<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\Company;
use App\Models\WorkShift;
use App\Services\CacheService;
use App\Traits\Functions;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use App\Services\Cache\CacheVersionService;
use Override;

/**
 * Műszak repository osztály
 * 
 * Adatbázis műveletek kezelése műszakokhoz.
 * Cache támogatással, verziókezeléssel és lapozással.
 */
class WorkShiftRepository extends BaseRepository implements WorkShiftRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    /** Cache namespace a műszak selector listához */
    private const NS_SELECTORS_WORK_SHIFT = 'selectors.work_shifts';
    
    public function __construct(
        AppContainer $app, 
        CacheService $cacheService, 
        CacheVersionService $cacheVersionService
    )
    {
        parent::__construct($app);
        
        $this->cacheService = $cacheService;
        $this->tag = WorkShift::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }
    
    /**
     * Műszakok listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Támogatja a keresést, rendezést és lapozást.
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, WorkShift> Lapozott műszak lista
     */
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_shifts', false);
        
        $page = (int) $request->integer('page', 1);
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;
        
        $companyId = (int) $request->integer('company_id', 0);

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');
        
        $sortable = WorkShift::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? $request->input('field')
            : null;
        
        //$direction = strtolower($request->input('order', '')) === 'desc' ? 'desc' : 'asc';
        
        $orderRaw = (string) $request->input('order', 'desc');
        $direction = strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';

        // a paginátor query-stringje (URL szinkronhoz hasznos)
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id']);
        
        $queryCallback = function() use($companyId, $term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = WorkShift::query()
                ->when($companyId > 0, fn ($qq) => $qq->where('company_id', $companyId))
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('start_time', 'like', "%{$term}%")
                            ->orWhere('end_time', 'like', "%{$term}%");
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));
            
            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);
            
            return $paginator;
        };
        
        if(!$needCache) {
            /** @var LengthAwarePaginator<int, WorkShift> $work_shifts */
            $work_shifts = $queryCallback();
            
            return $work_shifts;
        }
        
        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,      // lowercased/null
            'field' => $field,      // whitelistelt/null
            'order' => $direction,  // asc/desc
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get("company:{$companyId}:work_shifts");
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkShift> $work_shifts */
        $work_shifts = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $work_shifts;
    }
    
    /**
     * Műszak lekérése azonosító alapján
     * 
     * @param int $id Műszak azonosító
     * @return WorkShift Műszak model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    #[Override]
    public function getWorkShift(int $id): WorkShift
    {
        /** @var WorkShift $work_shift */
        $work_shift = WorkShift::findOrFail($id);
        
        return $work_shift;
    }

    /**
     * Műszak lekérése név alapján
     * 
     * @param string $name Műszak neve
     * @return WorkShift Műszak model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    #[Override]
    public function getWorkShiftByName(string $name): WorkShift
    {
        // Get the work_shift by its name
        /** @var WorkShift $work_shift */
        $work_shift = WorkShift::where('name', '=', $name)->firstOrFail();

        return $work_shift;
    }
    
    /**
     * Új műszak létrehozása
     * 
     * Tranzakcióban futtatva, alapértelmezett beállításokkal.
     * Létrehozás után cache invalidálás.
     * 
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data Műszak adatok
     * @return WorkShift Létrehozott műszak
     */
    #[Override]
    public function store(array $data): WorkShift
    {
        return DB::transaction(function() use($data): WorkShift {
            /** @var WorkShift $work_shift */
            $work_shift = WorkShift::query()->create($data);
            
            $this->createDefaultSettings($work_shift);
            
            // Cache ürítése
            $this->invalidateAfterWorkShiftWrite((int) $work_shift->company_id);
            
            return $work_shift;
        });
    }
    
    /**
     * Műszak adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Frissítés után cache invalidálás.
     * 
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data Frissítendő adatok
     * @param int $id Műszak azonosító
     * @return WorkShift Frissített műszak
     */
    #[Override]
    public function update(array $data, $id): WorkShift
    {
        return DB::transaction(function() use($data, $id) {
            /** @var WorkShift $work_shift */
            $work_shift = WorkShift::query()->lockForUpdate()->findOrFail($id);
            
            $work_shift->fill($data);
            $work_shift->save();
            $work_shift->refresh();
            
            $this->updateDefaultSettings($work_shift);

            // Cache ürítése
            $this->invalidateAfterWorkShiftWrite((int) $work_shift->company_id);
            
            return $work_shift;
        });
    }
    
    /**
     * Több műszak törlése egyszerre
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * 
     * @param list<int> $ids Műszak azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    #[Override]
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function() use($ids): int {
            $companyIds = WorkShift::query()
                ->whereIn('id', $ids)
                ->distinct()
                ->pluck('company_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $deleted = WorkShift::query()->whereIn('id', $ids)->delete();

            foreach ($companyIds as $companyId) {
                $this->invalidateAfterWorkShiftWrite($companyId);
            }
            
            return $deleted;
        });
    }

    /**
     * Egy műszak törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Törli a kapcsolódó beállításokat és invalidálja a cache-t.
     * 
     * @param int $id Műszak azonosító
     * @return bool Sikeres törlés esetén true
     */
    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($id) {
            /** @var WorkShift $work_shift */
            $work_shift = WorkShift::query()->lockForUpdate()->findOrFail($id);
            
            $deleted = (bool) $work_shift->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($work_shift);
            
            // Cache ürítése
            $this->invalidateAfterWorkShiftWrite((int) $work_shift->company_id);

            return $deleted;
        });
    }

    /**
     * Műszakok lekérése select listához
     * 
     * Egyszerűsített műszak lista (id, name) dropdown/select mezőkhöz.
     * Cache-elhető, csak aktív műszakokat ad vissza.
     * 
     * @param array{
     *   only_with_employees?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Műszakok tömbje
     */
    #[Override]
    public function getToSelect(array $params): array
    {
        $needCache = (bool) config('cache.enable_work_shiftToSelect', false);

        // normalize
        $params['only_with_employees'] = !empty($params['only_with_employees']);
        ksort($params);

        $onlyWithEmployees = (bool) $params['only_with_employees'];

        $queryCallback = function () use ($params): array {
            $companyId = (int) ($params['company_id'] ?? 0);
            /** @var array<int, array{id: int, name: string}> $out */
            $out = WorkShift::active()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                // Note: WorkShift modellnek nincs employees kapcsolata
                // ->when($onlyWithEmployees, fn ($q) => $q->whereHas('employees'))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (WorkShift $ws): array => ['id' => (int) $ws->id, 'name' => (string) $ws->name])
                ->values()
                ->all();

            return $out;
        };

        if (!$needCache) {
            return $queryCallback();
        }
        
        $version = $this->cacheVersionService->get(self::NS_SELECTORS_WORK_SHIFT);
        $hash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: 'work_shift_select',
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    /**
     * Cache invalidálás műszak írási műveletek után
     * 
     * Növeli a verzió számokat a műszak listázás és selector cache-ekhez.
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @return void
     */
    private function invalidateAfterWorkShiftWrite(int $companyId): void
    {
        DB::afterCommit(function() use ($companyId): void {
            // WorkShift lista oldal cache
            $this->cacheVersionService->bump("company:{$companyId}:work_shifts");

            // WorkShiftSelector cache (mert a selector aktív cégeket listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_SHIFT);
        });
    }
    
    /**
     * Alapértelmezett beállítások létrehozása új műszakhoz
     * 
     * @param WorkShift $work_shift Műszak model
     * @return void
     */
    private function createDefaultSettings(WorkShift $work_shift): void{}

    /**
     * Alapértelmezett beállítások frissítése
     * 
     * @param WorkShift $work_shift Műszak model
     * @return void
     */
    private function updateDefaultSettings(WorkShift $work_shift): void{}

    /**
     * Alapértelmezett beállítások törlése
     * 
     * @param WorkShift $work_shift Műszak model
     * @return void
     */
    private function deleteDefaultSettings(WorkShift $work_shift): void{}

    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    #[Override]
    public function model(): string
    {
        return WorkShift::class;
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
