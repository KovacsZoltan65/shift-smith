<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserSettingPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'user_settings.viewAny';
    public const PERM_VIEW = 'user_settings.view';
    public const PERM_CREATE = 'user_settings.create';
    public const PERM_UPDATE = 'user_settings.update';
    public const PERM_DELETE = 'user_settings.delete';
    public const PERM_DELETE_ANY = 'user_settings.deleteAny';
    public const PERM_MANAGE_OTHERS = 'user_settings.manageOthers';

    protected static function entity(): string
    {
        return 'user_settings';
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

    public function manageOthers(User $user): bool
    {
        return $user->can(self::PERM_MANAGE_OTHERS);
    }
}
