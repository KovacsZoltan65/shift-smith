<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class CompanySettingPolicy extends BasePolicy
{
    public const PERM_VIEW = 'company_settings.view';
    public const PERM_VIEW_ANY = 'company_settings.viewAny';
    public const PERM_CREATE = 'company_settings.create';
    public const PERM_UPDATE = 'company_settings.update';
    public const PERM_DELETE = 'company_settings.delete';
    public const PERM_DELETE_ANY = 'company_settings.deleteAny';

    protected static function entity(): string
    {
        return 'company_settings';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function view(User $user): bool
    {
        return $user->can(self::PERM_VIEW) || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user): bool
    {
        return $user->can(self::PERM_UPDATE);
    }

    public function delete(User $user): bool
    {
        return $user->can(self::PERM_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERM_DELETE_ANY);
    }
}
