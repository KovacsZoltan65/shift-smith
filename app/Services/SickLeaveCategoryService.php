<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SickLeaveCategory;
use App\Repositories\SickLeaveCategoryRepositoryInterface;

class SickLeaveCategoryService
{
    public function __construct(
        private readonly SickLeaveCategoryRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<array{id:int,name:string,code:string,active:bool}>
     */
    public function selector(int $companyId, bool $onlyActive = true): array
    {
        return $this->repository->listForSelector($companyId, $onlyActive)
            ->map(static fn (SickLeaveCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'code' => (string) $category->code,
                'active' => (bool) $category->active,
            ])
            ->values()
            ->all();
    }

    public function findForCompany(int $companyId, int $id): ?SickLeaveCategory
    {
        return $this->repository->findByIdInCompany($id, $companyId);
    }
}
