<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\AppSettingRepositoryInterface;
use App\Models\AppSetting;
use App\Services\CacheService;
use App\Services\Cache\CacheVersionService;
use Illuminate\Container\Container as AppContainer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Eloquent\BaseRepository;

class AppSettingRepository extends BaseRepository implements AppSettingRepositoryInterface
{
    private const CACHE_TAG = 'landlord:app_settings';
    private const FETCH_NAMESPACE = 'landlord:app_settings.fetch';
    private const SHOW_NAMESPACE = 'landlord:app_settings.show';
    private const OPTIONS_NAMESPACE = 'landlord:app_settings.options';

    public function __construct(
        AppContainer $app,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
        parent::__construct($app);
    }

    public function model(): string
    {
        return AppSetting::class;
    }

    public function fetch(array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $sortBy = (string) ($filters['sortBy'] ?? 'key');
        $sortDir = strtolower((string) ($filters['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        if (!\in_array($sortBy, AppSetting::getSortable(), true)) {
            $sortBy = 'key';
        }

        $normalized = [
            'page' => $page,
            'perPage' => $perPage,
            'q' => $this->normalizeString($filters['q'] ?? null),
            'group' => $this->normalizeString($filters['group'] ?? null),
            'type' => $this->normalizeString($filters['type'] ?? null),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ];

        $version = $this->cacheVersionService->get(self::FETCH_NAMESPACE);
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var LengthAwarePaginator<int, AppSetting> */
        return $this->cacheService->remember(
            tag: self::CACHE_TAG,
            key: $key,
            callback: function () use ($normalized, $page, $perPage): LengthAwarePaginator {
                $query = AppSetting::query()
                    ->search($normalized['q'])
                    ->when($normalized['group'] !== null, fn ($builder) => $builder->where('group', $normalized['group']))
                    ->when($normalized['type'] !== null, fn ($builder) => $builder->where('type', $normalized['type']))
                    ->orderBy($normalized['sortBy'], $normalized['sortDir'])
                    ->orderBy('id');

                $paginator = $query->paginate($perPage, ['*'], 'page', $page);
                $paginator->appends([
                    'q' => $normalized['q'],
                    'group' => $normalized['group'],
                    'type' => $normalized['type'],
                    'sortBy' => $normalized['sortBy'],
                    'sortDir' => $normalized['sortDir'],
                    'perPage' => $perPage,
                ]);

                return $paginator;
            },
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findById(int $id): AppSetting
    {
        $version = $this->cacheVersionService->get(self::SHOW_NAMESPACE);
        $key = 'v'.$version.':'.$id;

        /** @var AppSetting */
        return $this->cacheService->remember(
            tag: self::CACHE_TAG,
            key: $key,
            callback: static fn (): AppSetting => AppSetting::query()->findOrFail($id),
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findByKey(string $key): ?AppSetting
    {
        $version = $this->cacheVersionService->get(self::SHOW_NAMESPACE);

        /** @var AppSetting|null */
        return $this->cacheService->remember(
            tag: self::CACHE_TAG,
            key: 'v'.$version.':key:'.$key,
            callback: static fn (): ?AppSetting => AppSetting::query()->where('key', $key)->first(),
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function createSetting(array $attributes): AppSetting
    {
        /** @var AppSetting $setting */
        $setting = AppSetting::query()->create($attributes);

        return $setting->refresh();
    }

    public function updateSetting(int $id, array $attributes): AppSetting
    {
        $setting = AppSetting::query()->findOrFail($id);
        $setting->fill($attributes);
        $setting->save();

        return $setting->refresh();
    }

    public function deleteSetting(int $id): bool
    {
        return (bool) AppSetting::query()->whereKey($id)->delete();
    }

    public function bulkDelete(array $ids): int
    {
        return AppSetting::query()->whereIn('id', $ids)->delete();
    }

    public function valuesByKeys(array $keys): array
    {
        return AppSetting::query()
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->mapWithKeys(static fn (AppSetting $row): array => [(string) $row->key => $row->value])
            ->all();
    }

    public function groups(): array
    {
        return $this->rememberOptions('groups', static function (): array {
            return AppSetting::query()
                ->select('group')
                ->distinct()
                ->orderBy('group')
                ->pluck('group')
                ->map(static fn ($value): string => (string) $value)
                ->values()
                ->all();
        });
    }

    public function types(): array
    {
        return $this->rememberOptions('types', static function (): array {
            return AppSetting::query()
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->map(static fn ($value): string => (string) $value)
                ->values()
                ->all();
        });
    }

    /**
     * @param callable(): array<int, string> $resolver
     * @return list<string>
     */
    private function rememberOptions(string $suffix, callable $resolver): array
    {
        $version = $this->cacheVersionService->get(self::OPTIONS_NAMESPACE);
        $key = 'v'.$version.':'.$suffix;

        /** @var list<string> */
        return $this->cacheService->remember(
            tag: self::CACHE_TAG,
            key: $key,
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
