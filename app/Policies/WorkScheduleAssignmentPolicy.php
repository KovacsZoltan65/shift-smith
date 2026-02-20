<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * WorkScheduleAssignment policy osztály.
 *
 * A schedule-alapú kiosztások jogosultságait kezeli.
 */
final class WorkScheduleAssignmentPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedule_assignments.viewAny';
    public const PERM_VIEW = 'work_schedule_assignments.view';
    public const PERM_CREATE = 'work_schedule_assignments.create';
    public const PERM_UPDATE = 'work_schedule_assignments.update';
    public const PERM_DELETE = 'work_schedule_assignments.delete';
    public const PERM_BULK_DELETE = 'work_schedule_assignments.bulkDelete';

    /**
     * Policy entitás azonosító.
     *
     * @return string
     */
    protected static function entity(): string
    {
        return 'work_schedule_assignments';
    }

    /**
     * Lista lekérés jogosultsága.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    /**
     * Egyedi rekord megtekintés jogosultsága.
     */
    public function view(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    /**
     * Létrehozás jogosultsága.
     */
    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    /**
     * Frissítés jogosultsága.
     */
    public function update(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    /**
     * Törlés jogosultsága.
     */
    public function delete(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    /**
     * Tömeges törlés jogosultsága.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can(self::perm(self::PERM_BULK_DELETE));
    }
}
