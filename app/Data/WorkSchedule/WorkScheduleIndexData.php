<?php

declare(strict_types=1);

namespace App\Data\WorkSchedule;

use App\Models\WorkSchedule;
use Spatie\LaravelData\Data;

/**
 * Minimal munkabeosztás DTO táblázatos listázáshoz.
 */
class WorkScheduleIndexData extends Data
{
    /**
     * @param int $id Beosztás azonosító
     * @param int $company_id Cég azonosító
     * @param string $name Beosztás neve
     * @param string $date_from Kezdő dátum (Y-m-d)
     * @param string $date_to Záró dátum (Y-m-d)
     * @param string $status Státusz
     * @param ?string $notes Megjegyzés
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public int $company_id,
        public string $name,
        public string $date_from,
        public string $date_to,
        public string $status,
        public ?string $notes,
        public ?string $created_at,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param WorkSchedule $workSchedule Munkabeosztás model
     * @return self
     */
    public static function fromModel(WorkSchedule $workSchedule): self
    {
        return new self(
            id: (int) $workSchedule->id,
            company_id: (int) $workSchedule->company_id,
            name: (string) $workSchedule->name,
            date_from: (string) optional($workSchedule->date_from)?->format('Y-m-d'),
            date_to: (string) optional($workSchedule->date_to)?->format('Y-m-d'),
            status: (string) $workSchedule->status,
            notes: $workSchedule->notes,
            created_at: optional($workSchedule->created_at)?->toDateTimeString(),
        );
    }
}
