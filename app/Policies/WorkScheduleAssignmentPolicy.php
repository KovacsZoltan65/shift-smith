<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkShiftAssignment;
use App\Services\HierarchyAuthorizationService;
use Carbon\CarbonImmutable;

final class WorkScheduleAssignmentPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedule_assignments.viewAny';
    public const PERM_VIEW = 'work_schedule_assignments.view';
    public const PERM_CREATE = 'work_schedule_assignments.create';
    public const PERM_UPDATE = 'work_schedule_assignments.update';
    public const PERM_DELETE = 'work_schedule_assignments.delete';
    public const PERM_DELETE_ANY = 'work_schedule_assignments.deleteAny';

    protected static function entity(): string
    {
        return 'work_schedule_assignments';
    }

    public function before(User $user, string $ability): ?bool
    {
        if (\in_array($ability, ['update', 'delete'], true)) {
            return null;
        }

        return parent::before($user, $ability);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, WorkShiftAssignment $assignment): bool
    {
        if (! $user->can(self::perm(self::PERM_VIEW))) {
            return false;
        }

        if ($assignment->employee === null) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $authorization->canManageEmployee($user, $assignment->employee, CarbonImmutable::parse((string) $assignment->date));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, WorkShiftAssignment $assignment): bool
    {
        if (! $user->can(self::perm(self::PERM_UPDATE))) {
            return false;
        }

        if ($assignment->employee === null) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $this->isDateEditable((string) $assignment->date->format('Y-m-d'))
            && $authorization->canManageEmployee($user, $assignment->employee, CarbonImmutable::parse((string) $assignment->date));
    }

    public function delete(User $user, WorkShiftAssignment $assignment): bool
    {
        if (! $user->can(self::perm(self::PERM_DELETE))) {
            return false;
        }

        if ($assignment->employee === null) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $this->isDateEditable((string) $assignment->date->format('Y-m-d'))
            && $authorization->canManageEmployee($user, $assignment->employee, CarbonImmutable::parse((string) $assignment->date));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }

    private function isDateEditable(string $date): bool
    {
        return CarbonImmutable::parse($date)->startOfDay()
            ->greaterThanOrEqualTo(CarbonImmutable::today());
    }
}
