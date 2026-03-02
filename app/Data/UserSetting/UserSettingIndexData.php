<?php

declare(strict_types=1);

namespace App\Data\UserSetting;

use App\Models\UserSetting;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class UserSettingIndexData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public int $user_id,
        public string $key,
        public string $group,
        public string $type,
        public mixed $value,
        public ?string $value_preview,
        public ?CarbonInterface $updated_at,
    ) {
    }

    public static function fromModel(UserSetting $setting): self
    {
        return new self(
            id: (int) $setting->id,
            company_id: (int) $setting->company_id,
            user_id: (int) $setting->user_id,
            key: (string) $setting->key,
            group: (string) $setting->group,
            type: (string) $setting->type,
            value: $setting->value,
            value_preview: UserSettingData::preview($setting->value),
            updated_at: $setting->updated_at,
        );
    }
}
