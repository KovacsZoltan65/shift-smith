<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkShift;
use App\Models\User;
use App\Policies\BasePolicy;

final class WorkShiftAssigmentPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_shift_assignments.viewAny';
    public const PERM_VIEW = 'work_shift_assignments.view';
    public const PERM_CREATE = 'work_shift_assignments.create';
    public const PERM_UPDATE = 'work_shift_assignments.update';
    public const PERM_UPDATE_ANY = 'work_shift_assignments.updateAny';
    public const PERM_UPDATE_SELF = 'work_shift_assignments.updateSelf';
    public const PERM_DELETE = 'work_shift_assignments.delete';
    public const PERM_DELETE_ANY = 'work_shift_assignments.deleteAny';

    protected static function entity(): string
    {
        return 'work_shift_assignments';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    public function updateSelf(User $user, WorkShift $workShift): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    public function delete(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
