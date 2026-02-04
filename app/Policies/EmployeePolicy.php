<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    // Seeder mintád szerint: "{$entity}.{$action}"
    public const PERM_VIEW            = 'employees.view';
    public const PERM_VIEW_ANY        = 'employees.viewAny';
    public const PERM_CREATE          = 'employees.create';
    public const PERM_UPDATE          = 'employees.update';
    public const PERM_DELETE          = 'employees.delete';
    public const PERM_DELETE_ANY      = 'employees.deleteAny';
    public const PERM_RESTORE         = 'employees.restore';
    public const PERM_RESTORE_ANY     = 'employees.restoreAny';
    public const PERM_FORCE_DELETE    = 'employees.forceDelete';
    public const PERM_FORCE_DELETE_ANY= 'employees.forceDeleteAny';

    public function before(User $user, string $ability): ?bool
    {
        // superadmin mindent
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can(self::PERM_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(self::PERM_UPDATE);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can(self::PERM_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERM_DELETE_ANY);
    }
}
