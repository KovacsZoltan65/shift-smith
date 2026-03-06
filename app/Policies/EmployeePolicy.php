<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\BasePolicy;
use App\Services\HierarchyAuthorizationService;
use Carbon\CarbonImmutable;

/**
 * Dolgozó policy osztály
 * 
 * Dolgozók (employees) hozzáférés-vezérlése.
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
final class EmployeePolicy extends BasePolicy
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
    protected static function entity(): string { return 'employees'; }

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
     * @param Employee $employee Dolgozó rekord
     * @return bool Van-e jogosultság
     */
    public function view(User $user, Employee $employee): bool
    {
        if (! $user->can(self::perm(self::PERM_VIEW))) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $authorization->canManageEmployee($user, $employee, CarbonImmutable::today());
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
     * @param Employee $employee Dolgozó rekord
     * @return bool Van-e jogosultság
     */
    public function update(User $user, Employee $employee): bool
    {
        if (! $user->can(self::perm(self::PERM_UPDATE))) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $authorization->canManageEmployee($user, $employee, CarbonImmutable::today());
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
     * @param Employee $employee Dolgozó rekord
     * @return bool Van-e jogosultság
     */
    public function updateSelf(User $user, Employee $employee): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }
    
    /**
     * Rekord törlése engedély ellenőrzése
     * 
     * @param User $user Bejelentkezett felhasználó
     * @param Employee $employee Dolgozó rekord
     * @return bool Van-e jogosultság
     */
    public function delete(User $user, Employee $employee): bool
    {
        if (! $user->can(self::perm(self::PERM_DELETE))) {
            return false;
        }

        /** @var HierarchyAuthorizationService $authorization */
        $authorization = app(HierarchyAuthorizationService::class);

        return $authorization->canManageEmployee($user, $employee, CarbonImmutable::today());
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
