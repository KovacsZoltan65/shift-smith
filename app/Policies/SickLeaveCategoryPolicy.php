<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class SickLeaveCategoryPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'sick_leave_categories.viewAny';
    public const PERM_CREATE = 'sick_leave_categories.create';
    public const PERM_UPDATE = 'sick_leave_categories.update';
    public const PERM_DELETE = 'sick_leave_categories.delete';

    protected static function entity(): string
    {
        return 'sick_leave_categories';
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
