<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\BasePolicy;

final class CompanyPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'companies.viewAny';
    public const PERM_VIEW = 'companies.view';
    public const PERM_CREATE = 'companies.create';
    public const PERM_UPDATE = 'companies.update';
    public const PERM_UPDATE_ANY = 'companies.updateAny';
    public const PERM_UPDATE_SELF = 'companies.updateSelf';
    public const PERM_DELETE = 'companies.delete';
    public const PERM_DELETE_ANY = 'companies.deleteAny';

    protected static function entity(): string { return 'companies'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }

    public function updateAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_ANY));
    }

    public function updateSelf(User $user, Company $company): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE_SELF));
    }

    public function delete(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }
}
