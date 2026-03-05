<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class OrgHierarchyPolicy
{
    public const PERM_VIEW_ANY = 'org_hierarchy.viewAny';

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(self::PERM_VIEW_ANY);
    }
}
