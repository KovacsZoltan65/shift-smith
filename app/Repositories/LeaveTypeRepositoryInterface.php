<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeaveTypeRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters): LengthAwarePaginator;

    public function selectorForCompany(int $companyId, array $filters): array;

    public function existsByCodeInCompany(int $companyId, string $code): bool;

    public function findByIdInCompany(int $id, int $companyId): ?LeaveType;

    public function createForCompany(int $companyId, array $data): LeaveType;

    public function updateInCompany(int $id, int $companyId, array $data): LeaveType;

    public function deleteInCompany(int $id, int $companyId): void;

    public function categories(int $companyId): array;
}
