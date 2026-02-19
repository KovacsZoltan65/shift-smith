<?php

declare(strict_types=1);

namespace App\Data\WorkPattern;

use App\Models\WorkPattern;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * Teljes munkarend DTO create/update és részletes megjelenítés célra.
 */
class WorkPatternData extends Data
{
    /**
     * @param ?int $id Munkarend azonosító
     * @param int $company_id Cég azonosító
     * @param string $name Munkarend név
     * @param string $type Típus (fixed_weekly|rotating_shifts|custom)
     * @param ?int $cycle_length_days Ciklus hossza napokban
     * @param ?int $weekly_minutes Heti munkaidő percben
     * @param bool $active Aktív állapot
     * @param array<string,mixed>|null $meta Meta adatok
     * @param ?string $createdAt Létrehozás ideje
     * @param ?string $updatedAt Frissítés ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, IntegerType]
        public int $company_id,

        #[Required, StringType, Max(120)]
        public string $name,

        #[Required, StringType, In(['fixed_weekly', 'rotating_shifts', 'custom'])]
        public string $type,

        #[Nullable, IntegerType]
        public ?int $cycle_length_days = null,

        #[Nullable, IntegerType]
        public ?int $weekly_minutes = null,

        #[BooleanType]
        public bool $active = true,

        public ?array $meta = null,

        #[MapName('created_at')]
        public ?string $createdAt = null,

        #[MapName('updated_at')]
        public ?string $updatedAt = null,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param WorkPattern $workPattern Munkarend model
     * @return self
     */
    public static function fromModel(WorkPattern $workPattern): self
    {
        return new self(
            id: (int) $workPattern->id,
            company_id: (int) $workPattern->company_id,
            name: (string) $workPattern->name,
            type: (string) $workPattern->type,
            cycle_length_days: $workPattern->cycle_length_days,
            weekly_minutes: $workPattern->weekly_minutes,
            active: (bool) $workPattern->active,
            meta: $workPattern->meta,
            createdAt: optional($workPattern->created_at)?->toDateTimeString(),
            updatedAt: optional($workPattern->updated_at)?->toDateTimeString(),
        );
    }
}
