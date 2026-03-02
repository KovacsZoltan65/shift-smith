<?php

declare(strict_types=1);

namespace App\Data\Settings;

use Spatie\LaravelData\Data;

class EffectiveSettingDTO extends Data
{
    public function __construct(
        public string $key,
        public mixed $value,
        public string $source,
        public mixed $default_value,
        public ?string $type,
        public ?string $group,
        public ?string $label,
        public ?string $description,
        public ?int $company_id,
        public ?int $user_id,
    ) {
    }
}
