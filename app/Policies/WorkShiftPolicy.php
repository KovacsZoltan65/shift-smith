<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WorkShift;
use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Műszak policy osztály
 * 
 * Műszakok (work shifts) hozzáférés-vezérlése.
 * BasePolicy kiterjesztése permission-alapú autorizációval.
 * 
 * Támogatott műveletek:
 * - viewAny: Lista megtekintése
 * - view: Egyedi rekord megtekintése
 * - create: Új rekord létrehozása
 * - update: Rekord módosítása
 * - updateAny: Bármely rekord módosítása
 * - updateSelf: Saját rekord módosítása
 * - delete: Rekord törlése
 * - deleteAny: Bármely rekord törlése
 */
final class WorkShiftPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_shifts.view';
    public const PERM_VIEW = 'work_shifts.view';
    public const PERM_CREATE = 'work_shifts.create';
    public const PERM_UPDATE = 'work_shifts.update';
    public const PERM_UPDATE_ANY = 'work_shifts.update';
    public const PERM_UPDATE_SELF = 'work_shifts.updateSelf';
    public const PERM_DELETE = 'work_shifts.delete';
    public const PERM_DELETE_ANY = 'work_shifts.deleteAny';

    /**
     * Entity név lekérése
     * 
     * @return string Entity azonosító
     */
    protected static function entity(): string { return 'work_shifts'; }

    /**
     * Lista megtekintése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    /**
     * Egyedi rekord megtekintése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function view(User $user, ?WorkShift $workShift = null): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    /**
     * Új rekord létrehozása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    /**
     * Rekord módosítása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function update(User $user, ?WorkShift $workShift = null): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    /**
     * Bármely rekord módosítása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    /**
     * Saját rekord módosítása engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function updateSelf(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    /**
     * Rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function delete(User $user, ?WorkShift $workShift = null): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    /**
     * Bármely rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Van-e jogosultság
     */
    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY))
            || $user->can(self::perm(self::PERM_DELETE));
    }

    public function bulkDelete(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
