<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class EmployeeAbsencePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'absences.viewAny';
    public const PERM_CREATE = 'absences.create';
    public const PERM_UPDATE = 'absences.update';
    public const PERM_DELETE = 'absences.delete';

    protected static function entity(): string
    {
        return 'absences';
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
