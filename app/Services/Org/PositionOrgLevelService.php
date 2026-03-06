<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Employee;
use App\Models\PositionOrgLevel;
use App\Repositories\PositionOrgLevelRepositoryInterface;
use App\Services\Cache\CacheNamespaces;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PositionOrgLevelService
{
    public function __construct(
        private readonly PositionOrgLevelRepositoryInterface $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function resolveOrgLevel(int $companyId, string $positionString): string
    {
        $positionKey = PositionNormalizer::key($positionString);
        if ($positionKey === '') {
            return Employee::ORG_LEVEL_STAFF;
        }

        $map = $this->activeMapCached($companyId);

        return $map[$positionKey] ?? Employee::ORG_LEVEL_STAFF;
    }

    /**
     * @param array{q?:string|null,org_level?:string|null,active?:bool|null,page?:int,per_page?:int} $filters
     * @return LengthAwarePaginator<int, PositionOrgLevel>
     */
    public function listMappings(int $companyId, array $filters): LengthAwarePaginator
    {
        return $this->repository->fetch($companyId, $filters);
    }

    public function upsertMapping(int $companyId, string $positionLabel, string $orgLevel, bool $active = true): PositionOrgLevel
    {
        $positionKey = PositionNormalizer::key($positionLabel);

        $row = DB::transaction(fn (): PositionOrgLevel => $this->repository->upsert(
            $companyId,
            $positionKey,
            trim($positionLabel),
            $orgLevel,
            $active
        ));

        $this->invalidateCompanyMap($companyId);

        return $row;
    }

    public function updateMapping(int $companyId, int $id, string $positionLabel, string $orgLevel, bool $active): PositionOrgLevel
    {
        $positionKey = PositionNormalizer::key($positionLabel);

        $row = DB::transaction(function () use ($companyId, $id, $positionLabel, $positionKey, $orgLevel, $active): PositionOrgLevel {
            return $this->repository->updateInCompany($id, $companyId, [
                'position_label' => trim($positionLabel),
                'position_key' => $positionKey,
                'org_level' => $orgLevel,
                'active' => $active,
            ]);
        });

        $this->invalidateCompanyMap($companyId);

        return $row;
    }

    public function deleteMapping(int $companyId, int $id): bool
    {
        $deleted = DB::transaction(fn (): bool => $this->repository->deleteInCompany($id, $companyId));

        if ($deleted) {
            $this->invalidateCompanyMap($companyId);
        }

        return $deleted;
    }

    /**
     * @return array<string,string>
     */
    private function activeMapCached(int $companyId): array
    {
        $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
        $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
        $version = $this->cacheVersionService->get("{$base}:position_level_map");

        return $this->cacheService->remember(
            tag: $base,
            key: "{$base}:position_level_map:v{$version}",
            callback: fn (): array => $this->repository->activeMapByCompany($companyId),
            ttl: (int) config('cache.ttl_fetch', 300)
        );
    }

    private function invalidateCompanyMap(int $companyId): void
    {
        DB::afterCommit(function () use ($companyId): void {
            $tenantGroupId = $this->tenantContext->currentTenantGroupIdOrFail();
            $base = CacheNamespaces::tenantOrgHierarchy($tenantGroupId, $companyId);
            $this->cacheVersionService->bump("{$base}:position_level_map");
            $this->cacheVersionService->bump("{$base}:hierarchy");
        });
    }
}

