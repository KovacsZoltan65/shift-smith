<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppSetting;
use App\Models\User;

final class AppSettingPolicy extends BasePolicy
{
    public const PERM_VIEW = 'settings.app';
    public const PERM_UPDATE = 'settings.app';

    protected static function entity(): string
    {
        return 'settings';
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function view(User $user, AppSetting $appSetting): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function update(User $user, AppSetting $appSetting): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE));
    }
}
