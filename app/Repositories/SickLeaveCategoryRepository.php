<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SickLeaveCategory;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Support\Collection;

class SickLeaveCategoryRepository implements SickLeaveCategoryRepositoryInterface
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function listForSelector(int $companyId, bool $onlyActive = true): Collection
    {
        $normalized = [
            'companyId' => $companyId,
            'onlyActive' => $onlyActive,
        ];

        $version = $this->cacheVersionService->get($this->versionNamespace($companyId, 'selector'));
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var Collection<int, SickLeaveCategory> */
        return $this->cacheService->remember(
            tag: $this->cacheTag($companyId),
            key: $key,
            callback: static function () use ($companyId, $onlyActive): Collection {
                return SickLeaveCategory::query()
                    ->inCompany($companyId)
                    ->when($onlyActive, fn ($query) => $query->where('active', true))
                    ->orderByDesc('active')
                    ->orderBy('order_index')
                    ->orderBy('name')
                    ->get(['id', 'company_id', 'name', 'code', 'active', 'order_index']);
            },
            ttl: (int) config('cache.ttl_fetch', 300),
        );
    }

    public function findByIdInCompany(int $id, int $companyId): ?SickLeaveCategory
    {
        $version = $this->cacheVersionService->get($this->versionNamespace($companyId, 'show'));

        /** @var SickLeaveCategory|null $category */
        $category = $this->cacheService->remember(
            tag: $this->cacheTag($companyId),
            key: 'v'.$version.':'.$id,
            callback: static fn (): ?SickLeaveCategory => SickLeaveCategory::query()
                ->inCompany($companyId)
                ->find($id),
            ttl: (int) config('cache.ttl_fetch', 300),
        );

        return $category;
    }

    private function cacheTag(int $companyId): string
    {
        return "sick_leave_categories:company:{$companyId}";
    }

    private function versionNamespace(int $companyId, string $segment): string
    {
        return "sick_leave_categories:company:{$companyId}:{$segment}";
    }
}
