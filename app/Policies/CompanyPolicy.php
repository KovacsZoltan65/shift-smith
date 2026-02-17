<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Cég policy osztály
 * 
 * Autorizációs szabályok cégek CRUD műveleteihez.
 * Spatie Permission integráció jogosultság ellenőrzésekkel.
 * Superadmin automatikus hozzáférés (BasePolicy).
 */
final class CompanyPolicy extends BasePolicy
{
    /** Cégek listázás jogosultság */
    public const PERM_VIEW_ANY = 'companies.viewAny';
    /** Cég megtekintés jogosultság */
    public const PERM_VIEW = 'companies.view';
    /** Cég létrehozás jogosultság */
    public const PERM_CREATE = 'companies.create';
    /** Cég frissítés jogosultság */
    public const PERM_UPDATE = 'companies.update';
    /** Bármely cég frissítés jogosultság */
    public const PERM_UPDATE_ANY = 'companies.updateAny';
    /** Saját cég frissítés jogosultság */
    public const PERM_UPDATE_SELF = 'companies.updateSelf';
    /** Cég törlés jogosultság */
    public const PERM_DELETE = 'companies.delete';
    /** Bármely cég törlés jogosultság */
    public const PERM_DELETE_ANY = 'companies.deleteAny';

    /**
     * Entity azonosító a jogosultság prefix-hez
     * 
     * @return string Entity név
     */
    protected static function entity(): string { return 'companies'; }

    /**
     * Cégek listázás engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    /**
     * Cég megtekintés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function view(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    /**
     * Cég létrehozás engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    /**
     * Cég frissítés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function update(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    /**
     * Bármely cég frissítés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    /**
     * Saját cég frissítés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param Company $company Cég model
     * @return bool Engedélyezett-e
     */
    public function updateSelf(User $user, Company $company): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    /**
     * Cég törlés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function delete(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    /**
     * Bármely cég törlés engedélyezése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @return bool Engedélyezett-e
     */
    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
