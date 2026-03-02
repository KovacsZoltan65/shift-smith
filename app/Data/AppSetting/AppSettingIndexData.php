<?php

declare(strict_types=1);

namespace App\Data\AppSetting;

use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class AppSettingIndexData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public ?string $label,
        public string $group,
        public string $type,
        public mixed $value,
        public ?string $value_preview,
        public ?CarbonInterface $updated_at,
    ) {
    }

    public static function fromModel(AppSetting $setting): self
    {
        return new self(
            id: (int) $setting->id,
            key: (string) $setting->key,
            label: $setting->label !== null ? (string) $setting->label : null,
            group: (string) $setting->group,
            type: (string) $setting->type,
            value: $setting->value,
            value_preview: AppSettingData::preview($setting->value),
            updated_at: $setting->updated_at,
        );
    }
}
