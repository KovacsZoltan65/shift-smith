<?php

declare(strict_types=1);

namespace App\Data\WorkSchedule;

use App\Models\WorkSchedule;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * Teljes munkabeosztás DTO create/update és részletes megjelenítés célra.
 */
class WorkScheduleData extends Data
{
    /**
     * @param ?int $id Beosztás azonosító
     * @param int $company_id Cég azonosító
     * @param string $name Beosztás neve
     * @param string $date_from Kezdő dátum (Y-m-d)
     * @param string $date_to Záró dátum (Y-m-d)
     * @param string $status Státusz (draft|published)
     * @param ?string $notes Megjegyzés
     * @param ?string $created_at Létrehozás ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $company_id,

        #[Required, StringType, Max(150)]
        public string $name,

        #[Required, Date]
        public string $date_from,

        #[Required, Date, AfterOrEqual('date_from')]
        public string $date_to,

        #[Required, StringType, In(['draft', 'published'])]
        public string $status,

        #[Nullable, StringType]
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
