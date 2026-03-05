<?php

declare(strict_types=1);

namespace App\Repositories\Org;

use App\Interfaces\UserSettingRepositoryInterface;

final class OrgHierarchyDesignSettingsRepository implements OrgHierarchyDesignSettingsRepositoryInterface
{
    public function __construct(
        private readonly UserSettingRepositoryInterface $userSettingRepository,
    ) {
    }

    public function isUserAvailableInCompany(int $companyId, int $userId): bool
    {
        return $this->userSettingRepository->isUserAvailableInCompany($companyId, $userId);
    }

    public function upsertUserSetting(
        int $userId,
        int $companyId,
        string $key,
        mixed $value,
        int $updatedBy,
        string $type = 'string'
    ): void {
        $this->userSettingRepository->upsertForUserCompanyKey(
            userId: $userId,
            companyId: $companyId,
            key: $key,
            payload: [
                'value' => $value,
                'type' => $type,
                'group' => 'ui.hierarchy',
                'label' => $key,
                'description' => null,
                'updated_by' => $updatedBy,
            ],
        );
    }
}

