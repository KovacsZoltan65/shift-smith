<?php

declare(strict_types=1);

namespace App\Data\Settings;

use Spatie\LaravelData\Data;

class SettingItemData extends Data
{
    /**
     * @param array<int|string,mixed>|null $default_value
     * @param array<int|string,mixed>|null $app_value
     * @param array<int|string,mixed>|null $company_value
     * @param array<int|string,mixed>|null $user_value
     * @param array<int|string,mixed>|null $options
     * @param array<int|string,mixed>|null $validation
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public mixed $effective_value,
        public string $source,
        public mixed $default_value,
        public mixed $app_value,
        public mixed $company_value,
        public mixed $user_value,
        public bool $overridden_at_level,
        public bool $inherited,
        public ?string $description,
        public ?array $options,
        public ?array $validation,
        public int $order_index,
    ) {}
}

