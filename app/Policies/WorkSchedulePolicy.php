<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class WorkSchedulePolicy extends BasePolicy
{
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
