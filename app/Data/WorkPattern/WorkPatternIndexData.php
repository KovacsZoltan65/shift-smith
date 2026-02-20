<?php

declare(strict_types=1);

namespace App\Data\WorkPattern;

use App\Models\WorkPattern;
use Spatie\LaravelData\Data;

/**
 * Minimal munkarend DTO táblázatos listázáshoz.
 */
class WorkPatternIndexData extends Data
{
    /**
     * @param int $id Munkarend azonosító
     * @param int $company_id Cég azonosító
     * @param string $name Munkarend név
     * @param int $daily_work_minutes Napi munkaidő percben
     * @param int $break_minutes Szünet percben
     * @param ?string $core_start_time Törzsidő kezdete
     * @param ?string $core_end_time Törzsidő vége
     * @param int $employees_count Hozzárendelt dolgozók száma
     * @param bool $active Aktív állapot
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public int $company_id,
        public string $name,
        public int $daily_work_minutes,
        public int $break_minutes,
        public ?string $core_start_time = null,
        public ?string $core_end_time = null,
        public int $employees_count = 0,
        public bool $active = true,
        public ?string $created_at = null,
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
            employees_count: (int) ($workPattern->employees_count ?? 0),
            active: (bool) $workPattern->active,
            created_at: optional($workPattern->created_at)?->toDateTimeString(),
        );
    }
}
