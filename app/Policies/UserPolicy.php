<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Felhasználó policy osztály
 * 
 * Autorizációs szabályok felhasználók CRUD műveleteihez.
 * Spatie Permission integráció jogosultság ellenőrzésekkel.
 * Saját fiók speciális kezelés (view, update).
 * Superadmin automatikus hozzáférés (BasePolicy).
 */
final class UserPolicy extends BasePolicy
{
    use HandlesAuthorization;

    /** Felhasználók listázás jogosultság */
    public const PERM_VIEW_ANY = 'users.viewAny';
    /** Felhasználó megtekintés jogosultság */
    public const PERM_VIEW = 'users.view';
    /** Felhasználó létrehozás jogosultság */
    public const PERM_CREATE = 'users.create';
    /** Felhasználó frissítés jogosultság */
    public const PERM_UPDATE = 'users.update';
    /** Felhasználó szerepkör hozzárendelés jogosultság */
    public const PERM_ASSIGN_ROLES = 'users.assignRoles';
    /** Bármely felhasználó frissítés jogosultság */
    public const PERM_UPDATE_ANY = 'users.updateAny';
    /** Saját fiók frissítés jogosultság */
    public const PERM_UPDATE_SELF = 'users.updateSelf';
    /** Felhasználó törlés jogosultság */
    public const PERM_DELETE = 'users.delete';
    /** Bármely felhasználó törlés jogosultság */
    public const PERM_DELETE_ANY = 'users.deleteAny';

    /**
     * Entity azonosító a jogosultság prefix-hez
     * 
     * @return string Entity név
     */
    protected static function entity(): string { return 'users'; }

    /**
     * Felhasználók listázás engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    /**
     * Felhasználó megtekintés engedélyezése
     * 
     * Saját fiók: PERM_VIEW jogosultság
     * Más felhasználó: PERM_VIEW_ANY jogosultság
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param User $model Megtekintendő felhasználó
     * @return bool Engedélyezett-e
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id
            ? $user->can(self::PERM_VIEW)
            : $user->can(self::PERM_VIEW_ANY);
    }

    /**
     * Felhasználó létrehozás engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    /**
     * Felhasználó frissítés engedélyezése
     * 
     * Saját fiók: PERM_UPDATE_SELF vagy PERM_UPDATE_ANY jogosultság
     * Más felhasználó: PERM_UPDATE_ANY jogosultság
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param User $model Frissítendő felhasználó
     * @return bool Engedélyezett-e
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return $user->can(self::PERM_UPDATE)
                || $user->can(self::PERM_UPDATE_SELF)
                || $user->can(self::PERM_UPDATE_ANY);
        }

        return $user->can(self::PERM_UPDATE) || $user->can(self::PERM_UPDATE_ANY);
    }

    public function assignRoles(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return $user->can(self::PERM_ASSIGN_ROLES) || $user->can(self::PERM_UPDATE);
        }

        return $user->can(self::PERM_ASSIGN_ROLES);
    }

    /**
     * Felhasználó törlés engedélyezése
     * 
     * Saját fiók törlése tiltva.
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param User $model Törlendő felhasználó
     * @return bool Engedélyezett-e
     */
    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $user->can(self::PERM_DELETE);
    }
}
