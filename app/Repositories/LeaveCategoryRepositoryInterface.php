<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LeaveCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LeaveCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters): LengthAwarePaginator;

    public function listActiveForCompany(int $companyId): Collection;

    public function listForSelector(int $companyId, bool $onlyActive = true): Collection;

    public function findByIdInCompany(int $id, int $companyId): ?LeaveCategory;

    public function existsCodeInCompany(int $companyId, string $code, ?int $ignoreId = null): bool;

    public function existsActiveCodeInCompany(int $companyId, string $code): bool;

    public function createForCompany(int $companyId, array $data): LeaveCategory;

    public function updateInCompany(int $id, int $companyId, array $data): LeaveCategory;

    public function deleteInCompany(int $id, int $companyId): void;
}
