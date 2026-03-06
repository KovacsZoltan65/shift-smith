<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PositionOrgLevel;
use App\Models\User;

final class PositionOrgLevelPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'org_position_levels.viewAny';
    public const PERM_CREATE = 'org_position_levels.create';
    public const PERM_UPDATE = 'org_position_levels.update';
    public const PERM_DELETE = 'org_position_levels.delete';

    protected static function entity(): string
    {
        return 'org_position_levels';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user, PositionOrgLevel $mapping): bool
    {
        return $user->can(self::PERM_UPDATE);
    }

    public function delete(User $user, PositionOrgLevel $mapping): bool
    {
        return $user->can(self::PERM_DELETE);
    }
}

