<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SickLeaveCategory;
use Illuminate\Support\Collection;

interface SickLeaveCategoryRepositoryInterface
{
    public function listForSelector(int $companyId, bool $onlyActive = true): Collection;

    public function findByIdInCompany(int $id, int $companyId): ?SickLeaveCategory;
}
