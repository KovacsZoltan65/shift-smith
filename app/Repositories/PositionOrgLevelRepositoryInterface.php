<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PositionOrgLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PositionOrgLevelRepositoryInterface
{
    /**
     * @param array{q?:string|null,org_level?:string|null,active?:bool|null,page?:int,per_page?:int} $filters
     * @return LengthAwarePaginator<int, PositionOrgLevel>
     */
    public function fetch(int $companyId, array $filters): LengthAwarePaginator;

    public function findByIdInCompany(int $id, int $companyId): ?PositionOrgLevel;

    /**
     * @return array<string, string>
     */
    public function activeMapByCompany(int $companyId): array;

    public function upsert(int $companyId, string $positionKey, string $positionLabel, string $orgLevel, bool $active): PositionOrgLevel;

    public function updateInCompany(int $id, int $companyId, array $payload): PositionOrgLevel;

    public function deleteInCompany(int $id, int $companyId): bool;
}

