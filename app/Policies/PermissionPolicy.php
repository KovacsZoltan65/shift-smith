<?php

declare(strict_types=1);

namespace App\Policies;

//use App\Models\Permission;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Policies\BasePolicy;

final class PermissionPolicy extends BasePolicy
{
    protected static function entity(): string { return 'permissions'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm("viewAny"));
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(self::perm("view"));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm("create"));
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(self::perm("update"));
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(self::perm("delete"));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm("deleteAny"));
    }
}
