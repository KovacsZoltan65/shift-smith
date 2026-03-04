<?php

declare(strict_types=1);

namespace App\Data\WorkSchedule;

use App\Models\WorkSchedule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class WorkScheduleData extends Data
{
    public function __construct(
        public ?int $id,

        #[Required, IntegerType]
        public int $company_id,

        #[Required, StringType, Max(150)]
        public string $name,

        #[Required, Date]
        public string $date_from,

        #[Required, Date]
        public string $date_to,

        #[Required, StringType, In('draft', 'published')]
        public string $status = 'draft',

        #[MapName('created_at')]
        public ?string $createdAt = null,

        #[MapName('updated_at')]
        public ?string $updatedAt = null,
    ) {}

    public static function fromModel(WorkSchedule $workSchedule): self
    {
        return new self(
            id: (int) $workSchedule->id,
            company_id: (int) $workSchedule->company_id,
            name: (string) $workSchedule->name,
            date_from: (string) $workSchedule->date_from?->format('Y-m-d'),
            date_to: (string) $workSchedule->date_to?->format('Y-m-d'),
            status: (string) $workSchedule->status,
            createdAt: optional($workSchedule->created_at)?->toDateTimeString(),
            updatedAt: optional($workSchedule->updated_at)?->toDateTimeString(),
        );
    }
}
