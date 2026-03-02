<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Employee\EmployeeLeaveProfileDTO;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Models\EmployeeProfile;

final class EmployeeProfileRepository implements EmployeeProfileRepositoryInterface
{
    public function findByEmployeeInCompany(int $companyId, int $employeeId): ?EmployeeLeaveProfileDTO
    {
        /** @var EmployeeProfile|null $profile */
        $profile = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->first();

        return $profile instanceof EmployeeProfile ? $this->toDto($profile) : null;
    }

    public function upsertForEmployeeInCompany(int $companyId, int $employeeId, array $attributes): EmployeeLeaveProfileDTO
    {
        /** @var EmployeeProfile $profile */
        $profile = EmployeeProfile::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
            ],
            [
                'birth_date' => $attributes['birth_date'],
                'children_count' => max(0, (int) $attributes['children_count']),
                'disabled_children_count' => max(0, (int) $attributes['disabled_children_count']),
                'is_disabled' => (bool) $attributes['is_disabled'],
            ],
        );

        return $this->toDto($profile->refresh());
    }

    private function toDto(EmployeeProfile $profile): EmployeeLeaveProfileDTO
    {
        return new EmployeeLeaveProfileDTO(
            employee_id: (int) $profile->employee_id,
            company_id: (int) $profile->company_id,
            birth_date: $profile->birth_date?->toDateString(),
            children_count: max(0, (int) $profile->children_count),
            disabled_children_count: max(0, (int) $profile->disabled_children_count),
            is_disabled: (bool) $profile->is_disabled,
        );
    }
}
