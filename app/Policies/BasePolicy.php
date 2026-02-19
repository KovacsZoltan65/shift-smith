<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Alap policy osztály
 * 
 * Közös funkcionalitás minden policy osztályhoz.
 * Superadmin automatikus hozzáférés minden művelethez.
 * Entity-alapú jogosultság prefix generálás.
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Entity azonosító megadása
     * 
     * Minden leszármazott policy-nak implementálnia kell.
     * Használatos a jogosultság nevek prefix-éhez.
     * 
     * @return string Entity név (pl. 'companies', 'users')
     */
    abstract protected static function entity(): string;

    /**
     * Jogosultság név generálás
     * 
     * Entity prefix hozzáadása az action-höz.
     * 
     * @param string $action Művelet neve (pl. 'viewAny', 'create')
     * @return string Teljes jogosultság név (pl. 'companies.viewAny')
     */
    protected static function perm(string $action): string
    {
        if (str_contains($action, '.')) {
            return $action;
        }

        return static::entity().'.'.$action;
    }

    /**
     * Globális autorizáció ellenőrzés minden művelet előtt
     * 
     * Superadmin szerepkörrel rendelkező felhasználók automatikusan
     * hozzáférést kapnak minden művelethez.
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param string $ability Ellenőrzendő jogosultság
     * @return bool|null true = engedélyezve, null = folytatás a policy metódussal
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }
}
