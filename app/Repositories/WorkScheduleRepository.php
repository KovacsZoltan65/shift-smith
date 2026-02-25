<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Models\WorkSchedule;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\TenantContext;
use App\Traits\Functions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * Munkabeosztás repository osztály
 * 
 * Adatbázis műveletek kezelése munkabeosztásokhoz.
 * Cache támogatással, verziókezeléssel, lapozással és összetett szűrésekkel.
 * Támogatja a cég scope-ot, státusz és dátum szűrést.
 */
final class WorkScheduleRepository implements WorkScheduleRepositoryInterface
{
    use Functions;

    protected CacheService $cacheService;
    protected string $tag;

    private readonly CacheVersionService $cacheVersionService;
    private readonly TenantContext $tenantContext;

    public function __construct(
        CacheService $cacheService,
        CacheVersionService $cacheVersionService,
        TenantContext $tenantContext
    ) {
        $this->cacheService = $cacheService;
        $this->tag = WorkSchedule::getTag();
        $this->cacheVersionService = $cacheVersionService;
        $this->tenantContext = $tenantContext;
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
    public function fetch(Request $request, int $companyId): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_work_schedules', false);
        abort_if($companyId <= 0, 403, 'No company selected');

        $page = (int) $request->integer('page', 1);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $rawTerm = \trim((string) $request->input('search', ''));
        $term = $rawTerm === '' ? null : \mb_strtolower($rawTerm, 'UTF-8');

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

        $appendQuery = $request->only(['search', 'field', 'order', 'per_page', 'status', 'date_from', 'date_to']);

        $queryCallback = function () use ($term, $companyId, $status, $dateFrom, $dateTo, $field, $direction, $perPage, $page, $appendQuery): LengthAwarePaginator {
            $q = WorkSchedule::query()
                ->where('company_id', $companyId)
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

        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $namespace = CacheNamespaces::tenantWorkSchedules($tenantGroupId);
        $version = $this->cacheVersionService->get($namespace);
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
    public function findOrFailScoped(int $id, int $companyId): WorkSchedule
    {
        abort_if($companyId <= 0, 403, 'No company selected');

        /** @var WorkSchedule $workSchedule */
        $workSchedule = WorkSchedule::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $workSchedule;
    }

    /**
     * Új munkabeosztás létrehozása
     * 
     * Tranzakcióban futtatva, cache invalidálással.
     * 
     * @param array{
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data Munkabeosztás adatok
     * @return WorkSchedule Létrehozott munkabeosztás
     */
    #[Override]
    public function store(array $data, int $companyId): WorkSchedule
    {
        abort_if($companyId <= 0, 403, 'No company selected');
        unset($data['company_id']);

        return DB::transaction(function () use ($data, $companyId): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()->create([
                ...$data,
                'company_id' => $companyId,
            ]);

            $this->invalidateAfterWrite($companyId);

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
     *   name: string,
     *   date_from: string,
     *   date_to: string,
     *   status: string
     * } $data Frissítendő adatok
     * @param int $id Munkabeosztás azonosító
     * @return WorkSchedule Frissített munkabeosztás
     */
    #[Override]
    public function update(array $data, int $id, int $companyId): WorkSchedule
    {
        abort_if($companyId <= 0, 403, 'No company selected');
        unset($data['company_id']);

        return DB::transaction(function () use ($data, $id, $companyId): WorkSchedule {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            $workSchedule->fill([
                ...$data,
                'company_id' => $companyId,
            ]);
            $workSchedule->save();
            $workSchedule->refresh();

            $this->invalidateAfterWrite($companyId);

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
    public function bulkDelete(array $ids, int $companyId): int
    {
        abort_if($companyId <= 0, 403, 'No company selected');

        return DB::transaction(function () use ($ids, $companyId): int {
            $publishedExists = WorkSchedule::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->where('status', 'published')
                ->exists();

            if ($publishedExists) {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleted = WorkSchedule::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->delete();

            $this->invalidateAfterWrite($companyId);

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
    public function destroy(int $id, int $companyId): bool
    {
        abort_if($companyId <= 0, 403, 'No company selected');

        return DB::transaction(function () use ($id, $companyId): bool {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = WorkSchedule::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($workSchedule->status === 'published') {
                throw new \RuntimeException('Publikált beosztás nem törölhető.');
            }

            $deleted = (bool) $workSchedule->delete();

            $this->invalidateAfterWrite($companyId);

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
            $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
            $namespace = CacheNamespaces::tenantWorkSchedules($tenantGroupId);
            $this->cacheVersionService->bump($namespace);
            $this->cacheVersionService->bump("company:{$companyId}:work_schedules");
        });
    }

}
