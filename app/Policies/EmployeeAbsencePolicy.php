<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeAbsence;
use App\Models\User;
use App\Services\HierarchyAuthorizationService;
use Carbon\CarbonImmutable;

final class EmployeeAbsencePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'absences.viewAny';
    public const PERM_CREATE = 'absences.create';
    public const PERM_UPDATE = 'absences.update';
    public const PERM_DELETE = 'absences.delete';

    protected static function entity(): string
    {
        return 'absences';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function view(User $user, EmployeeAbsence $absence): bool
    {
        if (! $user->can(self::PERM_VIEW_ANY)) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);
        $atDate = CarbonImmutable::parse((string) $absence->date_from);

        return $absence->employee !== null
            && $authorization->canManageEmployee($user, $absence->employee, $atDate);
    }

    public function update(User $user, EmployeeAbsence $absence): bool
    {
        if (! $user->can(self::PERM_UPDATE)) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);
        $atDate = CarbonImmutable::parse((string) $absence->date_from);

        return $absence->employee !== null
            && $authorization->canManageEmployee($user, $absence->employee, $atDate);
    }

    public function delete(User $user, EmployeeAbsence $absence): bool
    {
        if (! $user->can(self::PERM_DELETE)) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);
        $atDate = CarbonImmutable::parse((string) $absence->date_from);

        return $absence->employee !== null
            && $authorization->canManageEmployee($user, $absence->employee, $atDate);
    }
}
