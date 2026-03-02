<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppSetting;
use App\Models\User;

final class AppSettingPolicy extends BasePolicy
{
    public const PERM_VIEW = 'app_settings.view';
    public const PERM_VIEW_ANY = 'app_settings.viewAny';
    public const PERM_CREATE = 'app_settings.create';
    public const PERM_UPDATE = 'app_settings.update';
    public const PERM_DELETE = 'app_settings.delete';
    public const PERM_DELETE_ANY = 'app_settings.deleteAny';

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
        return 'app_settings';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY)
            || $this->viewApp($user)
            || $this->viewCompany($user)
            || $this->viewUser($user);
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
        return $user->can(self::PERM_UPDATE)
            || $this->updateApp($user)
            || $this->updateCompany($user)
            || $this->updateUser($user);
    }

    public function delete(User $user): bool
    {
        return $user->can(self::PERM_DELETE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERM_DELETE_ANY);
    }

    public function viewApp(User $user): bool
    {
        return $user->can(self::PERM_VIEW_APP) || $user->can(self::PERM_VIEW_ANY);
    }

    public function updateApp(User $user): bool
    {
        return $user->can(self::PERM_UPDATE_APP) || $user->can(self::PERM_UPDATE);
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
