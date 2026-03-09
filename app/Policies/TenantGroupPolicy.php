<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantGroup;
use App\Models\User;

/**
 * Policy a kizárólag HQ-ban kezelhető TenantGroup adminisztrációhoz.
 *
 * A TenantGroup jogosultságok szándékosan elkülönülnek a company-scoped jogosultságoktól,
 * mert ez a modul a landlord rétegben működik.
 */
final class TenantGroupPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'tenant-groups.viewAny';
    public const PERM_VIEW = 'tenant-groups.view';
    public const PERM_CREATE = 'tenant-groups.create';
    public const PERM_UPDATE = 'tenant-groups.update';
    public const PERM_DELETE = 'tenant-groups.delete';

    protected static function entity(): string
    {
        return 'tenant-groups';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function view(User $user, ?TenantGroup $tenantGroup = null): bool
    {
        return $user->can(self::PERM_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user, ?TenantGroup $tenantGroup = null): bool
    {
        return $user->can(self::PERM_UPDATE);
    }

    public function delete(User $user, ?TenantGroup $tenantGroup = null): bool
    {
        return $user->can(self::PERM_DELETE);
    }
}
