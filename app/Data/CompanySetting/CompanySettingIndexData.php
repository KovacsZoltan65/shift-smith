<?php

declare(strict_types=1);

namespace App\Data\CompanySetting;

use App\Models\CompanySetting;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class CompanySettingIndexData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public string $key,
        public string $group,
        public string $type,
        public mixed $value,
        public ?string $value_preview,
        public ?CarbonInterface $updated_at,
        public mixed $effective_value = null,
        public ?string $effective_value_preview = null,
        public ?string $source = null,
    ) {
    }

    public static function fromModel(CompanySetting $setting): self
    {
        return new self(
            id: (int) $setting->id,
            company_id: (int) $setting->company_id,
            key: (string) $setting->key,
            group: (string) $setting->group,
            type: (string) $setting->type,
            value: $setting->value,
            value_preview: CompanySettingData::preview($setting->value),
            updated_at: $setting->updated_at,
        );
    }
}
