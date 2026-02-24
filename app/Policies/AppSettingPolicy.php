<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppSetting;
use App\Models\User;

final class AppSettingPolicy extends BasePolicy
{
    public const ABILITY_VIEW_APP = 'viewApp';
    public const ABILITY_UPDATE_APP = 'updateApp';
    public const ABILITY_VIEW_COMPANY = 'viewCompany';
    public const ABILITY_UPDATE_COMPANY = 'updateCompany';
    public const ABILITY_VIEW_USER = 'viewUser';
    public const ABILITY_UPDATE_USER = 'updateUser';

    public const PERM_VIEW_APP = 'settings.viewApp';
    public const PERM_UPDATE_APP = 'settings.updateApp';
    public const PERM_VIEW_COMPANY = 'settings.viewCompany';
    public const PERM_UPDATE_COMPANY = 'settings.updateCompany';
    public const PERM_VIEW_USER = 'settings.viewUser';
    public const PERM_UPDATE_USER = 'settings.updateUser';

    protected static function entity(): string
    {
        return 'settings';
    }

    public function viewAny(User $user): bool
    {
        return $this->viewApp($user) || $this->viewCompany($user) || $this->viewUser($user);
    }

    public function view(User $user, AppSetting $appSetting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AppSetting $appSetting): bool
    {
        return $this->updateApp($user) || $this->updateCompany($user) || $this->updateUser($user);
    }

    public function viewApp(User $user): bool
    {
        return $user->can(self::PERM_VIEW_APP);
    }

    public function updateApp(User $user): bool
    {
        return $user->can(self::PERM_UPDATE_APP);
    }

    public function viewCompany(User $user): bool
    {
        return $user->can(self::PERM_VIEW_COMPANY);
    }

    public function updateCompany(User $user): bool
    {
        return $user->can(self::PERM_UPDATE_COMPANY);
    }

    public function viewUser(User $user): bool
    {
        return $user->can(self::PERM_VIEW_USER);
    }

    public function updateUser(User $user): bool
    {
        return $user->can(self::PERM_UPDATE_USER);
    }
}
