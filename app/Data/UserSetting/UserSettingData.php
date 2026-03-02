<?php

declare(strict_types=1);

namespace App\Data\UserSetting;

use App\Models\UserSetting;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class UserSettingData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public int $user_id,
        public string $key,
        public mixed $value,
        public string $type,
        public string $group,
        public ?string $label,
        public ?string $description,
        public ?string $value_preview,
        public ?CarbonInterface $created_at,
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
            value: $setting->value,
            type: (string) $setting->type,
            group: (string) $setting->group,
            label: $setting->label !== null ? (string) $setting->label : null,
            description: $setting->description !== null ? (string) $setting->description : null,
            value_preview: self::preview($setting->value),
            created_at: $setting->created_at,
            updated_at: $setting->updated_at,
        );
    }

    public static function preview(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return mb_strimwidth((string) $value, 0, 80, '...');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : mb_strimwidth($encoded, 0, 80, '...');
    }
}
