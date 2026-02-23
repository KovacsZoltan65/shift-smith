<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Munkabeosztás policy osztály
 * 
 * Munkabeosztások (work schedules) hozzáférés-vezérlése.
 * BasePolicy kiterjesztése permission-alapú autorizációval.
 * 
 * Támogatott műveletek:
 * - viewAny: Lista megtekintése
 * - view: Egyedi rekord megtekintése
 * - create: Új rekord létrehozása
 * - update: Rekord módosítása
 * - delete: Rekord törlése
 * - deleteAny: Bármely rekord törlése
 */
final class WorkSchedulePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedules.viewAny';
    public const PERM_VIEW = 'work_schedules.view';
    public const PERM_CREATE = 'work_schedules.create';
    public const PERM_UPDATE = 'work_schedules.update';
    public const PERM_UPDATE_ANY = 'work_schedules.updateAny';
    public const PERM_UPDATE_SELF = 'work_schedules.updateSelf';
    public const PERM_DELETE = 'work_schedules.delete';
    public const PERM_DELETE_ANY = 'work_schedules.deleteAny';
    public const PERM_AUTOPLAN = 'work_schedules.autoplan';

    /**
     * Entity név lekérése
     * 
     * @return string Entity azonosító
     */
    protected static function entity(): string { return 'work_schedules'; }

    /**
     * Lista megtekintése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::perm('viewAny'));
    }

    /**
     * Egyedi rekord megtekintése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function view(User $user): bool
    {
        return $user->can(self::perm('view'));
    }

    /**
     * Új rekord létrehozása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function create(User $user): bool
    {
        return $user->can(self::perm('create'));
    }

    /**
     * Rekord módosítása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function update(User $user): bool
    {
        return $user->can(self::perm('update'));
    }

    /**
     * Rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function delete(User $user): bool
    {
        return $user->can(self::perm('delete'));
    }

    /**
     * Bármely rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm('deleteAny'));
    }

    public function autoplan(User $user): bool
    {
        return $user->can(self::perm(self::PERM_AUTOPLAN));
    }
}
