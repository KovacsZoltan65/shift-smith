<?php

declare(strict_types=1);

namespace App\Policies;

//use App\Models\Permission;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Jogosultság policy osztály
 * 
 * Jogosultságok (permissions) hozzáférés-vezérlése.
 * BasePolicy kiterjesztése permission-alapú autorizációval.
 * Spatie Permission integráció.
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
final class PermissionPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'permissions.viewAny';
    public const PERM_VIEW = 'permissions.view';
    public const PERM_CREATE = 'permissions.create';
    public const PERM_UPDATE = 'permissions.update';
    public const PERM_UPDATE_ANY = 'permissions.updateAny';
    public const PERM_UPDATE_SELF = 'permissions.updateSelf';
    public const PERM_DELETE = 'permissions.delete';
    public const PERM_DELETE_ANY = 'permissions.deleteAny';
    /**
     * Entity név lekérése
     * 
     * @return string Entity azonosító
     */
    protected static function entity(): string { return 'permissions'; }

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
     * @param Permission $permission Jogosultság rekord
     * @return bool Van-e jogosultság
     */
    public function view(User $user, Permission $permission): bool
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
     * @param Permission $permission Jogosultság rekord
     * @return bool Van-e jogosultság
     */
    public function update(User $user, Permission $permission): bool
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
     * @param Permission $permission Jogosultság rekord
     * @return bool Van-e jogosultság
     */
    public function updateSelf(User $user, Permission $permission): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    /**
     * Rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param Permission $permission Jogosultság rekord
     * @return bool Van-e jogosultság
     */
    public function delete(User $user, Permission $permission): bool
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
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
