<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

final class PositionPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'positions.viewAny';
    public const PERM_VIEW = 'positions.view';
    public const PERM_CREATE = 'positions.create';
    public const PERM_UPDATE = 'positions.update';
    public const PERM_DELETE = 'positions.delete';
    public const PERM_DELETE_ANY = 'positions.deleteAny';

    protected static function entity(): string
    {
        return 'positions';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, Position $position): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, Position $position): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
