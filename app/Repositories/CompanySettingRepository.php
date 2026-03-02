<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\CompanySettingRepositoryInterface;
use App\Models\CompanySetting;
use App\Services\CacheService;
use App\Services\Cache\CacheVersionService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Eloquent\BaseRepository;

class CompanySettingRepository extends BaseRepository implements CompanySettingRepositoryInterface
{
    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
        parent::__construct($app);
    }

    public function model(): string
    {
        return CompanySetting::class;
    }

    public function fetch(int $companyId, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $sortBy = (string) ($filters['sortBy'] ?? 'key');
        if (!\in_array($sortBy, CompanySetting::getSortable(), true)) {
            $sortBy = 'key';
        }

        $sortDir = strtolower((string) ($filters['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $normalized = [
            'companyId' => $companyId,
            'page' => $page,
            'perPage' => $perPage,
            'q' => $this->normalizeString($filters['q'] ?? null),
            'group' => $this->normalizeString($filters['group'] ?? null),
            'type' => $this->normalizeString($filters['type'] ?? null),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        $version = $this->cacheVersionService->get("company_settings:{$companyId}:fetch");
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var LengthAwarePaginator<int, CompanySetting> */
        return $this->cacheService->remember(
            tag: "company_settings:{$companyId}",
            key: $key,
            callback: function () use ($companyId, $normalized, $page, $perPage): LengthAwarePaginator {
                $query = CompanySetting::query()
                    ->inCompany($companyId)
                    ->search($normalized['q'])
                    ->when($normalized['group'] !== null, fn ($builder) => $builder->where('group', $normalized['group']))
                    ->when($normalized['type'] !== null, fn ($builder) => $builder->where('type', $normalized['type']))
                    ->orderBy($normalized['sortBy'], $normalized['sortDir'])
                    ->orderBy('id');

                return $query->paginate($perPage, ['*'], 'page', $page);
            },
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findByIdInCompany(int $id, int $companyId): CompanySetting
    {
        $version = $this->cacheVersionService->get("company_settings:{$companyId}:show");

        /** @var CompanySetting */
        return $this->cacheService->remember(
            tag: "company_settings:{$companyId}",
            key: 'v'.$version.':'.$id,
            callback: static fn () => CompanySetting::query()->inCompany($companyId)->findOrFail($id),
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findByKeyInCompany(string $key, int $companyId): ?CompanySetting
    {
        return CompanySetting::query()
            ->inCompany($companyId)
            ->where('key', $key)
            ->first();
    }

    public function createSetting(array $attributes): CompanySetting
    {
        /** @var CompanySetting $setting */
        $setting = CompanySetting::query()->create($attributes);

        return $setting->refresh();
    }

    public function updateSetting(int $id, int $companyId, array $attributes): CompanySetting
    {
        $setting = $this->findByIdInCompany($id, $companyId);
        $setting->fill($attributes);
        $setting->save();

        return $setting->refresh();
    }

    public function deleteSetting(int $id, int $companyId): bool
    {
        return (bool) CompanySetting::query()
            ->inCompany($companyId)
            ->whereKey($id)
            ->delete();
    }

    public function bulkDelete(int $companyId, array $ids): int
    {
        return CompanySetting::query()
            ->inCompany($companyId)
            ->whereIn('id', $ids)
            ->delete();
    }

    public function groups(int $companyId): array
    {
        return $this->rememberOptions($companyId, 'groups', static function () use ($companyId): array {
            return CompanySetting::query()
                ->inCompany($companyId)
                ->select('group')
                ->distinct()
                ->orderBy('group')
                ->pluck('group')
                ->map(static fn ($value): string => (string) $value)
                ->values()
                ->all();
        });
    }

    public function types(int $companyId): array
    {
        return $this->rememberOptions($companyId, 'types', static function () use ($companyId): array {
            return CompanySetting::query()
                ->inCompany($companyId)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->map(static fn ($value): string => (string) $value)
                ->values()
                ->all();
        });
    }

    public function valuesByKeys(int $companyId, array $keys): array
    {
        return CompanySetting::query()
            ->inCompany($companyId)
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->mapWithKeys(static fn (CompanySetting $row): array => [(string) $row->key => $row->value])
            ->all();
    }

    /**
     * @param callable(): array<int, string> $resolver
     * @return list<string>
     */
    private function rememberOptions(int $companyId, string $suffix, callable $resolver): array
    {
        $version = $this->cacheVersionService->get("company_settings:{$companyId}:options");

        /** @var list<string> */
        return $this->cacheService->remember(
            tag: "company_settings:{$companyId}",
            key: 'v'.$version.':'.$suffix,
            callback: $resolver,
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
