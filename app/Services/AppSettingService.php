<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AppSetting\AppSettingData;
use App\Data\AppSetting\AppSettingIndexData;
use App\Interfaces\AppSettingRepositoryInterface;
use App\Services\Cache\CacheVersionService;

class AppSettingService
{
    private const FETCH_NAMESPACE = 'landlord:app_settings.fetch';
    private const SHOW_NAMESPACE = 'landlord:app_settings.show';
    private const OPTIONS_NAMESPACE = 'landlord:app_settings.options';

    public function __construct(
        private readonly AppSettingRepositoryInterface $repository,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    /**
     * @param array{
     *   q?: string|null,
     *   group?: string|null,
     *   type?: string|null,
     *   sortBy?: string|null,
     *   sortDir?: string|null,
     *   page?: int|null,
     *   perPage?: int|null
     * } $filters
     * @return array{
     *   items: array<int, AppSettingIndexData>,
     *   meta: array<string, int>,
     *   filters: array<string, mixed>,
     *   options: array{groups:list<string>,types:list<string>}
     * }
     */
    public function fetch(array $filters): array
    {
        $paginator = $this->repository->fetch($filters);

        return [
            'items' => AppSettingIndexData::collect($paginator->items()),
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
                'sortBy' => $filters['sortBy'] ?? 'key',
                'sortDir' => $filters['sortDir'] ?? 'asc',
                'page' => (int) ($filters['page'] ?? 1),
                'perPage' => (int) ($filters['perPage'] ?? 10),
            ],
            'options' => [
                'groups' => $this->repository->groups(),
                'types' => $this->repository->types(),
            ],
        ];
    }

    public function show(int $id): AppSettingData
    {
        return AppSettingData::fromModel($this->repository->findById($id));
    }

    /**
     * @param array{
     *   key: string,
     *   value?: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * } $attributes
     */
    public function store(array $attributes): AppSettingData
    {
        $setting = $this->repository->createSetting($this->normalizePayload($attributes));
        $this->invalidateCache();

        return AppSettingData::fromModel($setting);
    }

    /**
     * @param array{
     *   key: string,
     *   value?: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * } $attributes
     */
    public function update(int $id, array $attributes): AppSettingData
    {
        $setting = $this->repository->updateSetting($id, $this->normalizePayload($attributes));
        $this->invalidateCache();

        return AppSettingData::fromModel($setting);
    }

    public function destroy(int $id): bool
    {
        $deleted = $this->repository->deleteSetting($id);

        if ($deleted) {
            $this->invalidateCache();
        }

        return $deleted;
    }

    /**
     * @param list<int> $ids
     */
    public function bulkDestroy(array $ids): int
    {
        $deleted = $this->repository->bulkDelete($ids);

        if ($deleted > 0) {
            $this->invalidateCache();
        }

        return $deleted;
    }

    /**
     * @param array{
     *   key: string,
     *   value?: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * } $attributes
     * @return array<string, mixed>
     */
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

    private function invalidateCache(): void
    {
        $this->cacheVersionService->bump(self::FETCH_NAMESPACE);
        $this->cacheVersionService->bump(self::SHOW_NAMESPACE);
        $this->cacheVersionService->bump(self::OPTIONS_NAMESPACE);
    }
}
