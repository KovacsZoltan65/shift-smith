<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkPattern;

/**
 * Munkarend policy osztály.
 *
 * Munkarendek hozzáférés-vezérlése permission alapokon.
 */
final class WorkPatternPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_patterns.viewAny';
    public const PERM_VIEW = 'work_patterns.view';
    public const PERM_CREATE = 'work_patterns.create';
    public const PERM_UPDATE = 'work_patterns.update';
    public const PERM_DELETE = 'work_patterns.delete';
    public const PERM_DELETE_ANY = 'work_patterns.deleteAny';

    /**
     * Entity név lekérése.
     */
    protected static function entity(): string
    {
        return 'work_patterns';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, WorkPattern $workPattern): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, WorkPattern $workPattern): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function delete(User $user, WorkPattern $workPattern): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
