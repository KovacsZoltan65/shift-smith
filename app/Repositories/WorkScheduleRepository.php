<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
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
 * Munkabeosztás repository osztály
 * 
 * Adatbázis műveletek kezelése munkabeosztásokhoz.
 * Cache támogatással, verziókezeléssel, lapozással és összetett szűrésekkel.
 * Támogatja a cég scope-ot, státusz és dátum szűrést.
 */
class WorkScheduleRepository extends BaseRepository implements WorkScheduleRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;

    private readonly CacheVersionService $cacheVersionService;

    public function __construct(
        AppContainer $app,
        CacheService $cacheService,
        CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);

        $this->cacheService = $cacheService;
        $this->tag = WorkSchedule::getTag();
        $this->cacheVersionService = $cacheVersionService;
    }

    /**
     * Munkabeosztások listázása lapozással, szűréssel és rendezéssel
     * 
     * Cache-elhető lekérdezés verziókezeléssel.
     * Támogatja a keresést, cég scope-ot, státusz és dátum szűrést.
     * User company_id automatikusan felülírja a query company_id-t.
     * 
     * @param Request $request HTTP kérés (search, company_id, status, date_from, date_to, field, order, per_page, page paraméterekkel)
     * @return LengthAwarePaginator<int, WorkSchedule> Lapozott munkabeosztás lista
     */
    #[Override]
    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_schedules', false);

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

        // company scope: ha a userhez van company_id, az felülírja a query-t
        $userCompanyId = (int) ($request->user()->company_id ?? 0);
        $companyIdRaw = $request->input('company_id');
        $companyId = $userCompanyId > 0
            ? $userCompanyId
            : (($companyIdRaw === null || $companyIdRaw === '') ? null : (int) $companyIdRaw);

        $statusRaw = \trim((string) $request->input('status', ''));
        $status = $statusRaw === '' ? null : $statusRaw;

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $sortable = WorkSchedule::getSortable();
        $field = \in_array($request->input('field', ''), $sortable, true)
            ? (string) $request->input('field')
            : null;

        $orderRaw = (string) $request->input('order', 'desc');
        $direction = \strtolower($orderRaw) === 'asc' ? 'asc' : 'desc';

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'company_id', 'status', 'date_from', 'date_to']);

        $queryCallback = function () use ($term, $companyId, $status, $dateFrom, $dateTo, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = WorkSchedule::query()
                ->when($companyId, fn ($qq) => $qq->where('company_id', $companyId))
                ->when($status, fn ($qq) => $qq->where('status', $status))
                ->when($dateFrom, fn ($qq) => $qq->whereDate('date_from', '>=', $dateFrom))
                ->when($dateTo, fn ($qq) => $qq->whereDate('date_to', '<=', $dateTo))
                ->when($term, function ($qq) use ($term) {
                    $qq->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                          ->orWhereRaw('LOWER(status) like ?', ["%{$term}%"]);
                    });
                })
                ->when($field, fn ($qq) => $qq->orderBy($field, $direction))
                ->when(!$field, fn ($qq) => $qq->orderByDesc('id'));

            $paginator = $q->paginate($perPage, ['*'], 'page', $page);
            $paginator->appends($appendQuery);

            return $paginator;
        };

        if (!$needCache) {
            /** @var LengthAwarePaginator<int, WorkSchedule> $out */
            $out = $queryCallback();
            return $out;
        }

        $paramsForKey = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $term,
            'company_id' => $companyId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'field' => $field,
            'order' => $direction,
        ];
        ksort($paramsForKey);

        $version = $this->cacheVersionService->get('company:'.(int) ($companyId ?? 0).':work_schedules');
        $hash = hash('sha256', json_encode($paramsForKey, JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        /** @var LengthAwarePaginator<int, WorkSchedule> $out */
        $out = $this->cacheService->remember(
            tag: $this->tag,
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );

        return $out;
    }

    /**
     * Munkabeosztás lekérése azonosító alapján
     * 
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Munkabeosztás model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    #[Override]
    public function getWorkSchedule(int $id): WorkSchedule
    {
        /** @var WorkSchedule $workSchedule */
        $workSchedule = WorkSchedule::findOrFail($id);

        return $workSchedule;
    }

    /**
     * Új munkabeosztás létrehozása
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * 
     * @param array{
     *   company_id: int,
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data Munkabeosztás adatok
     * @return WorkSchedule Létrehozott munkabeosztás
     */
    #[Override]
    public function store(array $data): WorkSchedule
    {
        return DB::transaction(function () use ($data): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->create($data);

            $this->invalidateAfterWrite((int) $workSchedule->company_id);

            return $workSchedule;
        });
    }

    /**
     * Munkabeosztás adatainak frissítése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Frissítés után cache invalidálás.
     * 
     * @param array{
     *   company_id: int,
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data Frissítendő adatok
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Frissített munkabeosztás
     */
    #[Override]
    public function update(array $data, $id): WorkSchedule
    {
        return DB::transaction(function () use ($data, $id): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->lockForUpdate()->findOrFail($id);

            $workSchedule->fill($data);
            $workSchedule->save();
            $workSchedule->refresh();

            $this->invalidateAfterWrite((int) $workSchedule->company_id);

            return $workSchedule;
        });
    }

    /**
     * Több munkabeosztás törlése egyszerre
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * Publikált beosztások nem törölhetők (RuntimeException).
     * 
     * @param list<int> $ids Munkabeosztás azonosítók tömbje
     * @return int A törölt rekordok száma
     * @throws \RuntimeException Ha publikált beosztást próbálunk törölni
     */
    #[Override]
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $companyIds = WorkSchedule::query()
                ->whereIn('id', $ids)
                ->distinct()
                ->pluck('company_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $publishedExists = WorkSchedule::query()
                ->whereIn('id', $ids)
                ->where('status', 'published')
                ->exists();

            if ($publishedExists) {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleted = WorkSchedule::query()->whereIn('id', $ids)->delete();

            foreach ($companyIds as $companyId) {
                $this->invalidateAfterWrite($companyId);
            }

            return (int) $deleted;
        });
    }

    /**
     * Egy munkabeosztás törlése
     * 
     * Tranzakcióban futtatva, pesszimista zárolással.
     * Publikált beosztások nem törölhetők (RuntimeException).
     * 
     * @param int $id Munkabeosztás azonosító
     * @return bool Sikeres törlés esetén true
     * @throws \RuntimeException Ha publikált beosztást próbálunk törölni
     */
    #[Override]
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->lockForUpdate()->findOrFail($id);

            if ($workSchedule->status === 'published') {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleted = (bool) $workSchedule->delete();

            $this->invalidateAfterWrite((int) $workSchedule->company_id);

            return $deleted;
        });
    }

    /**
     * Cache invalidálás munkabeosztás írási műveletek után
     * 
     * Növeli a verzió számot a munkabeosztás listázás cache-hez.
     * DB commit után fut, így biztosítva a konzisztenciát.
     * 
     * @return void
     */
    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump("company:{$companyId}:work_schedules");
        });
    }

    /**
     * Repository model osztály megadása
     * 
     * @return string Model osztály neve
     */
    #[Override]
    public function model(): string
    {
        return WorkSchedule::class;
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
