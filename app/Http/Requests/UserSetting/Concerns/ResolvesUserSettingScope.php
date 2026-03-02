<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSetting\Concerns;

use App\Services\UserSettingService;
use App\Support\CurrentCompanyContext;
use App\Policies\UserSettingPolicy;

trait ResolvesUserSettingScope
{
    public function currentCompanyId(): int
    {
        return app(CurrentCompanyContext::class)->resolve($this);
    }

    public function targetUserId(): int
    {
        $authUserId = (int) $this->user()->id;
        $inputUserId = $this->input('user_id');

        if (! is_numeric($inputUserId) || (int) $inputUserId === $authUserId) {
            return $authUserId;
        }

        abort_unless(
            $this->user()?->can(UserSettingPolicy::PERM_MANAGE_OTHERS),
            403,
            'Nincs jogosultság más felhasználó beállításainak kezelésére.'
        );

        $targetUserId = (int) $inputUserId;
        app(UserSettingService::class)->assertUserAvailableInCompany($this->currentCompanyId(), $targetUserId);

        return $targetUserId;
    }
}
