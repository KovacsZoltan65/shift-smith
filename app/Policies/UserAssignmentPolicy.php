<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserAssignmentPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'user_assignments.viewAny';
    public const PERM_UPDATE = 'user_assignments.update';

    protected static function entity(): string
    {
        return 'user_assignments';
    }

    public function viewAny(User $user): bool
    {
        return $this->hasManagementRole($user) && $user->hasPermissionTo(self::PERM_VIEW_ANY);
    }

    public function update(User $user): bool
    {
        return $this->hasManagementRole($user) && $user->hasPermissionTo(self::PERM_UPDATE);
    }

    private function hasManagementRole(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }
}
