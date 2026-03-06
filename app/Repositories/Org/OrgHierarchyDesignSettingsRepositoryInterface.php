<?php

declare(strict_types=1);

namespace App\Repositories\Org;

interface OrgHierarchyDesignSettingsRepositoryInterface
{
    public function isUserAvailableInCompany(int $companyId, int $userId): bool;

    public function upsertUserSetting(
        int $userId,
        int $companyId,
        string $key,
        mixed $value,
        int $updatedBy,
        string $type = 'string'
    ): void;
}

