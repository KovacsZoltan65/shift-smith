<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\BasePolicy;

final class EmployeePolicy extends BasePolicy
{
    protected static function entity(): string { return 'employees'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm('viewAny'));
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can(self::perm('view'));
    }
    
    public function create(User $user): bool
    {
        return $user->can(self::perm('create'));
    }
    
    public function update(User $user, Employee $employee): bool
    {
        return $user->can(self::perm('update'));
    }
    
    public function delete(User $user, Employee $employee): bool
    {
        return $user->can(self::perm('delete'));
    }
    
    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm('deleteAny'));
    }
}