<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CompanySetting\CompanySettingData;
use App\Data\CompanySetting\CompanySettingIndexData;
use App\Interfaces\CompanySettingRepositoryInterface;
use App\Services\Cache\CacheVersionService;

class CompanySettingService
{
    public function __construct(
        private readonly CompanySettingRepositoryInterface $repository,
        private readonly EffectiveSettingsResolverService $resolver,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetch(int $companyId, array $filters): array
    {
        $paginator = $this->repository->fetch($companyId, $filters);
        $items = CompanySettingIndexData::collect($paginator->items());
        $effective = $this->resolver->getEffectiveMany(
            array_map(static fn ($row): string => (string) $row->key, $paginator->items()),
            $companyId,
            null
        );
        $effectiveByKey = [];
        foreach ($effective as $row) {
            $effectiveByKey[$row->key] = $row;
        }

        foreach ($items as $item) {
            $resolved = $effectiveByKey[$item->key] ?? null;
            $item->effective_value = $resolved?->effective_value;
            $item->effective_value_preview = CompanySettingData::preview($resolved?->effective_value);
            $item->source = $resolved?->source;
        }

        return [
            'items' => $items,
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
                'groups' => $this->repository->groups($companyId),
                'types' => $this->repository->types($companyId),
            ],
        ];
    }

    public function show(int $companyId, int $id): CompanySettingData
    {
        return CompanySettingData::fromModel($this->repository->findByIdInCompany($id, $companyId));
    }

    public function store(int $companyId, array $attributes): CompanySettingData
    {
        $setting = $this->repository->createSetting([
            ...$this->normalizePayload($attributes),
            'company_id' => $companyId,
        ]);

        $this->invalidateCache($companyId);

        return CompanySettingData::fromModel($setting);
    }

    public function update(int $companyId, int $id, array $attributes): CompanySettingData
    {
        $setting = $this->repository->updateSetting($id, $companyId, $this->normalizePayload($attributes));
        $this->invalidateCache($companyId);

        return CompanySettingData::fromModel($setting);
    }

    public function destroy(int $companyId, int $id): bool
    {
        $deleted = $this->repository->deleteSetting($id, $companyId);
        if ($deleted) {
            $this->invalidateCache($companyId);
        }

        return $deleted;
    }

    public function bulkDestroy(int $companyId, array $ids): int
    {
        $deleted = $this->repository->bulkDelete($companyId, $ids);
        if ($deleted > 0) {
            $this->invalidateCache($companyId);
        }

        return $deleted;
    }

    public function effective(int $companyId, array $keys, ?int $userId = null): array
    {
        return $this->resolver->getEffectiveMany($keys, $companyId, $userId);
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

    private function invalidateCache(int $companyId): void
    {
        $this->cacheVersionService->bump("company_settings:{$companyId}:fetch");
        $this->cacheVersionService->bump("company_settings:{$companyId}:show");
        $this->cacheVersionService->bump("company_settings:{$companyId}:options");
        $this->cacheVersionService->bump("effective_settings:{$companyId}:all");
    }
}
