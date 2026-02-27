<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserEmployeePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'user_employees.viewAny';
    public const PERM_CREATE = 'user_employees.create';
    public const PERM_DELETE = 'user_employees.delete';

    protected static function entity(): string
    {
        return 'user_employees';
    }

    public function viewAny(User $user): bool
    {
        return $this->hasManagerRole($user) && $user->can(self::PERM_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->hasManagerRole($user) && $user->can(self::PERM_CREATE);
    }

    public function delete(User $user): bool
    {
        return $this->hasManagerRole($user) && $user->can(self::PERM_DELETE);
    }

    private function hasManagerRole(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }
}
