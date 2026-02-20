<?php

declare(strict_types=1);

namespace App\Data\Position;

use App\Models\Position;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class PositionData extends Data
{
    public function __construct(
        public ?int $id,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $company_id,

        #[Required, StringType, Max(120)]
        public string $name,

        #[Nullable, StringType, Max(1000)]
        public ?string $description = null,

        #[BooleanType]
        public bool $active = true,

        #[MapName('created_at')]
        public ?string $createdAt = null,
    ) {}

    public static function fromModel(Position $position): self
    {
        return new self(
            id: (int) $position->id,
            company_id: (int) $position->company_id,
            name: (string) $position->name,
            description: $position->description ? (string) $position->description : null,
            active: (bool) $position->active,
            createdAt: optional($position->created_at)?->toDateTimeString(),
        );
    }
}
