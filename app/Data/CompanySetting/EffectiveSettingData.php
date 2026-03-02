<?php

declare(strict_types=1);

namespace App\Data\CompanySetting;

use Spatie\LaravelData\Data;

class EffectiveSettingData extends Data
{
    public function __construct(
        public string $key,
        public mixed $effective_value,
        public string $source,
        public ?string $type,
        public ?string $group,
        public ?string $label,
        public ?string $description,
        public int $company_id,
        public ?int $user_id,
    ) {
    }
}
