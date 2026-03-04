<?php

declare(strict_types=1);

namespace App\Data\WorkSchedule;

use App\Models\WorkSchedule;
use Spatie\LaravelData\Data;

class WorkScheduleIndexData extends Data
{
    public function __construct(
        public int $id,
        public int $company_id,
        public string $name,
        public string $date_from,
        public string $date_to,
        public string $status,
        public int $assignments_count = 0,
        public ?string $created_at = null,
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
            assignments_count: (int) ($workSchedule->assignments_count ?? 0),
            created_at: optional($workSchedule->created_at)?->toDateTimeString(),
        );
    }
}
