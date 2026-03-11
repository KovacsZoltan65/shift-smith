<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class HqCompanyPolicy extends BasePolicy
{
    public const PERM_VIEW = 'hq.companies.view';
    public const PERM_CREATE = 'hq.companies.create';
    public const PERM_UPDATE = 'hq.companies.update';

    protected static function entity(): string
    {
        return 'hq.companies';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user): bool
    {
        return $user->can(self::PERM_UPDATE);
    }
}
