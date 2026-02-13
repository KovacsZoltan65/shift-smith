<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkShiftRepositoryInterface;
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

class WorkShiftRepository extends BaseRepository implements WorkShiftRepositoryInterface
{
    use Functions;
    
    protected CacheService $cacheService;
    protected string $tag;
    
    private readonly CacheVersionService $cacheVersionService;
    
    private const NS_WORK_SHIFT_FETCH = 'work_shifts.fetch';
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
     * 
     * @param Request $request
     * @return LengthAwarePaginator<int, WorkShift>
     */
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_shifts', false);
        
        $page = (int) $request->integer('page', 1);
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;
        
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
        $appendQuery = $request->only(['search', 'field', 'order', 'per_page']);
        
        $queryCallback = function() use($term, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = WorkShift::query()
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
            /** @var LengthAwarePaginator<int, WorkShift> $work_shifts */
            $work_shifts = $queryCallback();
            
            return $work_shifts;
        }
        
        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,      // lowercased/null
            'field' => $field,      // whitelistelt/null
            'order' => $direction,  // asc/desc
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get(self::NS_WORK_SHIFT_FETCH);
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
    
    #[Override]
    public function getWorkShift(int $id): WorkShift
    {
        /** @var WorkShift $work_shift */
        $work_shift = WorkShift::findOrFail($id);
        
        return $work_shift;
    }

    #[Override]
    public function getWorkShiftByName(string $name): WorkShift
    {
        // Get the work_shift by its name
        /** @var WorkShift $work_shift */
        $work_shift = WorkShift::where('name', '=', $name)->firstOrFail();

        return $work_shift;
    }
    
    /**
     * Summary of store
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data
     * @return WorkShift
     */
    #[Override]
    public function store(array $data): WorkShift
    {
        return DB::transaction(function() use($data): WorkShift {
            /** @var WorkShift $work_shift */
            $work_shift = WorkShift::query()->create($data);
            
            $this->createDefaultSettings($work_shift);
            
            // Cache ürítése
            $this->invalidateAfterWorkShiftWrite();
            
            return $work_shift;
        });
    }
    
    /**
     * Summary of update
     * @param array{
     *    company_id: int,
     *    name: string,
     *    start_time: string,
     *    end_time: string,
     *    active: boolean
     * } $data
     * @param int $id
     * @return WorkShift
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
            $this->invalidateAfterWorkShiftWrite();
            
            return $work_shift;
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
            $deleted = WorkShift::query()->whereIn('id', $ids)->delete();
            
            $this->invalidateAfterWorkShiftWrite();
            
            return $deleted;
        });
    }

    /**
     * @param int $id
     * @return bool
     */
    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($int) {
            /** @var WorkShift $work_shift */
            $work_shift = WorkShift::query()->lockForUpdate()->findOrFail($id);
            
            $deleted = (bool) $work_shift->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($work_shift);
            
            // Cache ürítése
            $this->invalidateAfterWorkShiftWrite();

            return $deleted;
        });
    }

    /**
     * @param array{
     *   only_with_companies?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    #[Override]
    public function getToSelect(array $params)
    {
        $needCache = (bool) config('cache.enable_work_shiftToSelect', false);

        // normalize
        $params['only_with_companies'] = !empty($params['only_with_companies']);
        ksort($params);

        $onlyWithCompanies = (bool) $params['only_with_companies'];

        $queryCallback = function () use ($onlyWithCompanies): array {
            /** @var array<int, array{id: int, name: string}> $out */
            $out = Company::active()
                ->when($onlyWithCompanies, fn ($q) => $q->whereHas('companies'))
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

    private function invalidateAfterWorkShiftWrite(): void
    {
        DB::afterCommit(function():void {
            // WorkShift lista oldal cache
            $this->cacheVersionService->bump(self::NS_WORK_SHIFT_FETCH);

            // WorkShiftSelector cache (mert a selector aktív cégeket listáz)
            $this->cacheVersionService->bump(self::NS_SELECTORS_WORK_SHIFT);
        });
    }
    
    private function createDefaultSettings(WorkShift $work_shift): void{}

    private function updateDefaultSettings(WorkShift $work_shift): void{}

    private function deleteDefaultSettings(WorkShift $work_shift): void{}

    #[Override]
    public function model(): string
    {
        return WorkShift::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }
}