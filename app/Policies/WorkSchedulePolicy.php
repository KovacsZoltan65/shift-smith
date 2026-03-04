<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;

final class WorkSchedulePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedules.viewAny';
    public const PERM_VIEW = 'work_schedules.view';
    public const PERM_CREATE = 'work_schedules.create';
    public const PERM_UPDATE = 'work_schedules.update';
    public const PERM_DELETE = 'work_schedules.delete';
    public const PERM_DELETE_ANY = 'work_schedules.deleteAny';

    protected static function entity(): string
    {
        return 'work_schedules';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function delete(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
