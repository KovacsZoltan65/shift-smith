<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class LeaveTypePolicy extends BasePolicy
{
    public const PERM_VIEW = 'leave_types.view';
    public const PERM_VIEW_ANY = 'leave_types.viewAny';
    public const PERM_CREATE = 'leave_types.create';
    public const PERM_UPDATE = 'leave_types.update';
    public const PERM_DELETE = 'leave_types.delete';

    protected static function entity(): string
    {
        return 'leave_types';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function view(User $user): bool
    {
        return $user->can(self::PERM_VIEW) || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user): bool
    {
        return $user->can(self::PERM_UPDATE);
    }

    public function delete(User $user): bool
    {
        return $user->can(self::PERM_DELETE);
    }
}
