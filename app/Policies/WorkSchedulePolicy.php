<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class WorkSchedulePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedules.viewAny';
    public const PERM_VIEW = 'work_schedules.view';
    public const PERM_CREATE = 'work_schedules.create';
    public const PERM_UPDATE = 'work_schedules.update';
    public const PERM_UPDATE_ANY = 'work_schedules.updateAny';
    public const PERM_UPDATE_SELF = 'work_schedules.updateSelf';
    public const PERM_DELETE = 'work_schedules.delete';
    public const PERM_DELETE_ANY = 'work_schedules.deleteAny';

    protected static function entity(): string { return 'work_schedules'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm('viewAny'));
    }

    public function view(User $user): bool
    {
        return $user->can(self::perm('view'));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm('create'));
    }

    public function update(User $user): bool
    {
        return $user->can(self::perm('update'));
    }

    public function delete(User $user): bool
    {
        return $user->can(self::perm('delete'));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm('deleteAny'));
    }
}
