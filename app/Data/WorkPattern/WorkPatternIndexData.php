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
     * @param string $type Munkarend típus
     * @param ?int $weekly_minutes Heti munkaidő percben
     * @param bool $active Aktív állapot
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public int $id,
        public int $company_id,
        public string $name,
        public string $type,
        public ?int $weekly_minutes = null,
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
            type: (string) $workPattern->type,
            weekly_minutes: $workPattern->weekly_minutes,
            active: (bool) $workPattern->active,
            created_at: optional($workPattern->created_at)?->toDateTimeString(),
        );
    }
}
