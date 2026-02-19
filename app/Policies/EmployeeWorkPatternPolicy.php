<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeWorkPattern;
use App\Models\User;

/**
 * Dolgozó-munkarend policy osztály.
 *
 * Dolgozók munkarend-hozzárendeléseinek hozzáférés-vezérlése.
 */
final class EmployeeWorkPatternPolicy extends BasePolicy
{
    public const PERM_ASSIGN = 'employee_work_patterns.assign';
    public const PERM_UNASSIGN = 'employee_work_patterns.unassign';
    public const PERM_VIEW = 'employee_work_patterns.view';

    /**
     * Entity név lekérése.
     */
    protected static function entity(): string
    {
        return 'employee_work_patterns';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function view(User $user, EmployeeWorkPattern $employeeWorkPattern): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_ASSIGN));
    }

    public function update(User $user, EmployeeWorkPattern $employeeWorkPattern): bool
    {
        return $user->can(self::perm(self::PERM_ASSIGN));
    }

    public function delete(User $user, EmployeeWorkPattern $employeeWorkPattern): bool
    {
        return $user->can(self::perm(self::PERM_UNASSIGN));
    }
}
