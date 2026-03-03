<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SickLeaveCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SickLeaveCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters): LengthAwarePaginator;

    public function listActiveForCompany(int $companyId): Collection;

    public function listForSelector(int $companyId, bool $onlyActive = true): Collection;

    public function findByIdInCompany(int $id, int $companyId): ?SickLeaveCategory;

    public function existsCodeInCompany(int $companyId, string $code, ?int $ignoreId = null): bool;

    public function createForCompany(int $companyId, array $data): SickLeaveCategory;

    public function updateInCompany(int $id, int $companyId, array $data): SickLeaveCategory;

    public function deleteInCompany(int $id, int $companyId): void;
}
