<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\PositionRepositoryInterface;
use App\Models\Position;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Override;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class PositionRepository extends BaseRepository implements PositionRepositoryInterface
{
    private const NS_POSITIONS_FETCH = 'positions.fetch';
    private const NS_SELECTORS_POSITIONS = 'selectors.positions';

    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {
        parent::__construct($app);
    }

    public function fetch(Request $request): LengthAwarePaginator
    {
        $needCache = (bool) config('cache.enable_positions', false);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(max(1, (int) $request->integer('per_page', 10)), 100);
        $companyId = (int) $request->integer('company_id');
        $termRaw = trim((string) $request->input('search', ''));
        $term = $termRaw === '' ? null : mb_strtolower($termRaw, 'UTF-8');
        $field = in_array((string) $request->input('field', ''), Position::SORTABLE, true)
            ? (string) $request->input('field')
            : null;
        $direction = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $queryCallback = function () use ($companyId, $term, $field, $direction, $perPage, $page): LengthAwarePaginator {
            $query = Position::query()
                ->where('company_id', $companyId)
                ->when($term, fn ($q) => $q->whereRaw('LOWER(name) like ?', ["%{$term}%"]))
                ->when($field, fn ($q) => $q->orderBy($field, $direction))
                ->when(!$field, fn ($q) => $q->orderByDesc('id'));

            return $query->paginate($perPage, ['*'], 'page', $page);
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_POSITIONS_FETCH);
        $hash = hash('sha256', json_encode([
            'page' => $page,
            'per_page' => $perPage,
            'company_id' => $companyId,
            'search' => $term,
            'field' => $field,
            'order' => $direction,
        ], JSON_THROW_ON_ERROR));

        return $this->cacheService->remember(
            tag: "company:{$companyId}:positions",
            key: "v{$version}:{$hash}",
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 60)
        );
    }

    public function getPosition(int $id, int $companyId): Position
    {
        /** @var Position $position */
        $position = Position::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);
        return $position;
    }

    public function store(array $data): Position
    {
        return DB::transaction(function () use ($data): Position {
            /** @var Position $position */
            $position = Position::query()->create($data);
            $this->invalidateAfterWrite((int) $position->company_id);
            return $position;
        });
    }

    public function update(array $data, mixed $id): Position
    {
        return DB::transaction(function () use ($data, $id): Position {
            $companyId = (int) $data['company_id'];
            /** @var Position $position */
            $position = Position::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);
            $position->fill($data);
            $position->save();
            $position->refresh();
            $this->invalidateAfterWrite($companyId);
            return $position;
        });
    }

    public function bulkDelete(array $ids, int $companyId): int
    {
        return DB::transaction(function () use ($ids, $companyId): int {
            $deleted = (int) Position::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->delete();
            if ($deleted > 0) {
                $this->invalidateAfterWrite($companyId);
            }
            return $deleted;
        });
    }

    public function destroy(int $id, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $companyId): bool {
            /** @var Position $position */
            $position = Position::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($id);
            $deleted = (bool) $position->delete();
            if ($deleted) {
                $this->invalidateAfterWrite($companyId);
            }
            return $deleted;
        });
    }

    public function getToSelect(int $companyId, bool $onlyActive = true): array
    {
        $needCache = (bool) config('cache.enable_position_to_select', false);

        $queryCallback = function () use ($companyId, $onlyActive): array {
            return Position::query()
                ->where('company_id', $companyId)
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(static fn (Position $position): array => [
                    'id' => (int) $position->id,
                    'name' => (string) $position->name,
                ])
                ->values()
                ->all();
        };

        if (!$needCache) {
            return $queryCallback();
        }

        $version = $this->cacheVersionService->get(self::NS_SELECTORS_POSITIONS . ".company_{$companyId}");
        $hash = hash('sha256', json_encode(['company_id' => $companyId, 'only_active' => $onlyActive], JSON_THROW_ON_ERROR));
        $key = "v{$version}:{$hash}";

        return $this->cacheService->remember(
            tag: "company:{$companyId}:positions:selector",
            key: $key,
            callback: $queryCallback,
            ttl: (int) config('cache.ttl_fetch', 1800)
        );
    }

    private function invalidateAfterWrite(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $this->cacheVersionService->bump(self::NS_POSITIONS_FETCH);
            $this->cacheVersionService->bump(self::NS_SELECTORS_POSITIONS . ".company_{$companyId}");
        });
    }

    #[Override]
    public function model(): string
    {
        return Position::class;
    }

    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
