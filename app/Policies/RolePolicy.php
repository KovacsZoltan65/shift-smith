<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin\Role;
use App\Models\User;
use App\Policies\BasePolicy;

final class RolePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'employees.viewAny';
    public const PERM_VIEW = 'employees.view';
    public const PERM_CREATE = 'employees.create';
    public const PERM_UPDATE = 'employees.update';
    public const PERM_UPDATE_ANY = 'employees.updateAny';
    public const PERM_UPDATE_SELF = 'employees.updateSelf';
    public const PERM_DELETE = 'employees.delete';
    public const PERM_DELETE_ANY = 'employees.deleteAny';

    protected static function entity(): string { return 'roles'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    public function updateSelf(User $user, Role $role): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
