<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class SickLeaveCategoryPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'sick_leave_categories.viewAny';

    protected static function entity(): string
    {
        return 'sick_leave_categories';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }
}
