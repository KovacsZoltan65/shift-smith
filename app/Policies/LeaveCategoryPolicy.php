<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class LeaveCategoryPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'leave_categories.viewAny';
    public const PERM_CREATE = 'leave_categories.create';
    public const PERM_UPDATE = 'leave_categories.update';
    public const PERM_DELETE = 'leave_categories.delete';

    protected static function entity(): string
    {
        return 'leave_categories';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
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
