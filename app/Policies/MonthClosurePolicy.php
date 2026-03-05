<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MonthClosure;
use App\Models\User;

final class MonthClosurePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'month_closures.viewAny';
    public const PERM_CREATE = 'month_closures.close';
    public const PERM_DELETE = 'month_closures.reopen';

    protected static function entity(): string
    {
        return 'month_closures';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function delete(User $user, MonthClosure $closure): bool
    {
        return $user->can(self::PERM_DELETE);
    }
}
