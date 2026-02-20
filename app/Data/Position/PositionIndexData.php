<?php

declare(strict_types=1);

namespace App\Data\Position;

use App\Models\Position;
use Spatie\LaravelData\Data;

class PositionIndexData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public string $name,
        public ?string $description = null,
        public bool $active = true,
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
