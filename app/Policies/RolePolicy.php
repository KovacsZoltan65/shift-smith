<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin\Role;
use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Szerepkör policy osztály
 * 
 * Szerepkörök (roles) hozzáférés-vezérlése.
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
final class RolePolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'employees.viewAny';
    public const PERM_VIEW = 'employees.view';
    public const PERM_CREATE = 'employees.create';
    public const PERM_UPDATE = 'employees.update';
    public const PERM_UPDATE_ANY = 'employees.updateAny';
    public const PERM_UPDATE_SELF = 'employees.updateSelf';
    public const PERM_DELETE = 'employees.delete';
    public const PERM_DELETE_ANY = 'employees.deleteAny';

    /**
     * Entity név lekérése
     * 
     * @return string Entity azonosító
     */
    protected static function entity(): string { return 'roles'; }

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
     * @param Role $role Szerepkör rekord
     * @return bool Van-e jogosultság
     */
    public function view(User $user, Role $role): bool
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
     * @param Role $role Szerepkör rekord
     * @return bool Van-e jogosultság
     */
    public function update(User $user, Role $role): bool
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
     * @param Role $role Szerepkör rekord
     * @return bool Van-e jogosultság
     */
    public function updateSelf(User $user, Role $role): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    /**
     * Rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param Role $role Szerepkör rekord
     * @return bool Van-e jogosultság
     */
    public function delete(User $user, Role $role): bool
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
