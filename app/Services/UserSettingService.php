<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\UserSetting\UserSettingData;
use App\Data\UserSetting\UserSettingIndexData;
use App\Interfaces\UserSettingRepositoryInterface;
use App\Services\CacheService;
use App\Services\Cache\CacheVersionService;
use Illuminate\Auth\Access\AuthorizationException;

class UserSettingService
{
    public function __construct(
        private readonly UserSettingRepositoryInterface $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetch(int $companyId, int $userId, array $filters): array
    {
        $normalized = [
            'companyId' => $companyId,
            'userId' => $userId,
            'q' => $filters['q'] ?? null,
            'group' => $filters['group'] ?? null,
            'type' => $filters['type'] ?? null,
            'sortBy' => $filters['sortBy'] ?? 'key',
            'sortDir' => $filters['sortDir'] ?? 'asc',
            'page' => (int) ($filters['page'] ?? 1),
            'perPage' => (int) ($filters['perPage'] ?? 10),
        ];
        $version = $this->cacheVersionService->get("user_settings:{$companyId}:{$userId}:fetch");
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        $paginator = $this->cacheService->remember(
            tag: "user_settings:{$companyId}:{$userId}",
            key: $key,
            callback: fn () => $this->repository->fetch($companyId, $userId, $filters),
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return [
            'items' => UserSettingIndexData::collect($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'q' => $filters['q'] ?? null,
                'group' => $filters['group'] ?? null,
                'type' => $filters['type'] ?? null,
                'user_id' => $userId,
                'sortBy' => $filters['sortBy'] ?? 'key',
                'sortDir' => $filters['sortDir'] ?? 'asc',
                'page' => (int) ($filters['page'] ?? 1),
                'perPage' => (int) ($filters['perPage'] ?? 10),
            ],
            'options' => [
                'groups' => $this->rememberOptions($companyId, $userId, 'groups', fn () => $this->repository->groups($companyId, $userId)),
                'types' => $this->rememberOptions($companyId, $userId, 'types', fn () => $this->repository->types($companyId, $userId)),
            ],
        ];
    }

    public function show(int $companyId, int $userId, int $id): UserSettingData
    {
        $version = $this->cacheVersionService->get("user_settings:{$companyId}:{$userId}:show");

        return $this->cacheService->remember(
            tag: "user_settings:{$companyId}:{$userId}",
            key: 'v'.$version.':'.$id,
            callback: fn () => UserSettingData::fromModel($this->repository->findByIdInScope($id, $companyId, $userId)),
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function store(int $companyId, int $userId, array $attributes): UserSettingData
    {
        $setting = $this->repository->createSetting([
            ...$this->normalizePayload($attributes),
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        $this->invalidateCache($companyId, $userId);

        return UserSettingData::fromModel($setting);
    }

    public function update(int $companyId, int $userId, int $id, array $attributes): UserSettingData
    {
        $setting = $this->repository->updateSetting($id, $companyId, $userId, $this->normalizePayload($attributes));
        $this->invalidateCache($companyId, $userId);

        return UserSettingData::fromModel($setting);
    }

    public function destroy(int $companyId, int $userId, int $id): bool
    {
        $deleted = $this->repository->deleteSetting($id, $companyId, $userId);

        if ($deleted) {
            $this->invalidateCache($companyId, $userId);
        }

        return $deleted;
    }

    public function bulkDestroy(int $companyId, int $userId, array $ids): int
    {
        $deleted = $this->repository->bulkDelete($companyId, $userId, $ids);

        if ($deleted > 0) {
            $this->invalidateCache($companyId, $userId);
        }

        return $deleted;
    }

    public function assertUserAvailableInCompany(int $companyId, int $userId): void
    {
        if ($this->repository->isUserAvailableInCompany($companyId, $userId)) {
            return;
        }

        throw new AuthorizationException('A felhasználó nem érhető el a kiválasztott company scope-ban.');
    }

    private function normalizePayload(array $attributes): array
    {
        return [
            'key' => trim((string) $attributes['key']),
            'value' => $attributes['value'] ?? null,
            'type' => (string) $attributes['type'],
            'group' => trim((string) $attributes['group']),
            'label' => isset($attributes['label']) && is_string($attributes['label']) && trim($attributes['label']) !== ''
                ? trim($attributes['label'])
                : null,
            'description' => isset($attributes['description']) && is_string($attributes['description']) && trim($attributes['description']) !== ''
                ? trim($attributes['description'])
                : null,
        ];
    }

    private function invalidateCache(int $companyId, int $userId): void
    {
        $this->cacheVersionService->bump("user_settings:{$companyId}:{$userId}:fetch");
        $this->cacheVersionService->bump("user_settings:{$companyId}:{$userId}:show");
        $this->cacheVersionService->bump("user_settings:{$companyId}:{$userId}:options");
        $this->cacheVersionService->bump("effective_settings:{$companyId}:all");
    }

    /**
     * @param callable(): list<string> $resolver
     * @return list<string>
     */
    private function rememberOptions(int $companyId, int $userId, string $suffix, callable $resolver): array
    {
        $version = $this->cacheVersionService->get("user_settings:{$companyId}:{$userId}:options");

        /** @var list<string> */
        return $this->cacheService->remember(
            tag: "user_settings:{$companyId}:{$userId}",
            key: 'v'.$version.':'.$suffix,
            callback: $resolver,
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }
}
