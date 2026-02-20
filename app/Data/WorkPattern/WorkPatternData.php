<?php

declare(strict_types=1);

namespace App\Data\WorkPattern;

use App\Models\WorkPattern;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
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
     * @param int $daily_work_minutes Napi munkaidő percben
     * @param int $break_minutes Szünet percben
     * @param ?string $core_start_time Törzsidő kezdete
     * @param ?string $core_end_time Törzsidő vége
     * @param bool $active Aktív állapot
     * @param ?string $createdAt Létrehozás ideje
     * @param ?string $updatedAt Frissítés ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, IntegerType]
        public int $company_id,

        #[Required, StringType, Max(120)]
        public string $name,

        #[Required, IntegerType, Min(1), Max(1440)]
        public int $daily_work_minutes,

        #[Required, IntegerType, Min(0), Max(1440)]
        public int $break_minutes,

        #[Nullable, StringType, DateFormat('H:i:s')]
        public ?string $core_start_time = null,

        #[Nullable, StringType, DateFormat('H:i:s')]
        public ?string $core_end_time = null,

        #[BooleanType]
        public bool $active = true,

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
            daily_work_minutes: (int) $workPattern->daily_work_minutes,
            break_minutes: (int) $workPattern->break_minutes,
            core_start_time: $workPattern->core_start_time,
            core_end_time: $workPattern->core_end_time,
            active: (bool) $workPattern->active,
            createdAt: optional($workPattern->created_at)?->toDateTimeString(),
            updatedAt: optional($workPattern->updated_at)?->toDateTimeString(),
        );
    }
}
