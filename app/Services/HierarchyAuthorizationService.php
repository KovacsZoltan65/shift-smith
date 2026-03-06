<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\Settings;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\UserEmployeeRepositoryInterface;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class HierarchyAuthorizationService
{
    public function __construct(
        private readonly EmployeeSupervisorRepositoryInterface $employeeSupervisorRepository,
        private readonly UserEmployeeRepositoryInterface $userEmployeeRepository,
    ) {
    }

    public function resolveUserEmployeeId(User $user, int $companyId): ?int
    {
        return $this->userEmployeeRepository->findEmployeeIdForUserInCompany($user, $companyId);
    }

    public function canManageEmployee(User $user, Employee $targetEmployee, CarbonInterface $atDate): bool
    {
        if ((int) $targetEmployee->company_id <= 0) {
            return false;
        }

        $actorEmployeeId = $this->resolveUserEmployeeId($user, (int) $targetEmployee->company_id);
        if (! is_int($actorEmployeeId) || $actorEmployeeId <= 0) {
            return false;
        }

        if ($actorEmployeeId === (int) $targetEmployee->id) {
            return true;
        }

        $recursive = Settings::getBool('org.hierarchy.recursive_supervisor_access', false);

        return $this->isSupervisorOf(
            companyId: (int) $targetEmployee->company_id,
            supervisorEmployeeId: $actorEmployeeId,
            employeeId: (int) $targetEmployee->id,
            atDate: $atDate,
            recursive: $recursive
        );
    }

    public function isSupervisorOf(
        int $companyId,
        int $supervisorEmployeeId,
        int $employeeId,
        CarbonInterface $atDate,
        bool $recursive
    ): bool {
        if ($recursive) {
            $subtree = $this->employeeSupervisorRepository->listSubtreeEmployeeIds(
                $companyId,
                $supervisorEmployeeId,
                CarbonImmutable::instance($atDate)
            );

            return in_array($employeeId, $subtree, true);
        }

        $direct = $this->employeeSupervisorRepository->listDirectSubordinates(
            $companyId,
            $supervisorEmployeeId,
            CarbonImmutable::instance($atDate)
        );

        return in_array($employeeId, $direct, true);
    }
}
