<?php

declare(strict_types=1);

namespace App\Data\EmployeeWorkPattern;

use App\Models\EmployeeWorkPattern;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Dolgozó-munkarend hozzárendelés DTO.
 */
class EmployeeWorkPatternData extends Data
{
    /**
     * @param ?int $id Hozzárendelés azonosító
     * @param int $company_id Cég azonosító
     * @param int $employee_id Dolgozó azonosító
     * @param int $work_pattern_id Munkarend azonosító
     * @param string $date_from Érvényesség kezdete
     * @param ?string $date_to Érvényesség vége
     * @param bool $is_primary Elsődleges hozzárendelés
     * @param array<string,mixed>|null $meta Meta adatok
     * @param ?string $work_pattern_name Munkarend megnevezés
     * @param ?string $createdAt Létrehozás ideje
     * @param ?string $updatedAt Frissítés ideje
     */
    public function __construct(
        public ?int $id,

        #[Required, IntegerType]
        public int $company_id,

        #[Required, IntegerType]
        public int $employee_id,

        #[Required, IntegerType]
        public int $work_pattern_id,

        #[Required, Date]
        public string $date_from,

        #[Nullable, Date]
        public ?string $date_to = null,

        #[BooleanType]
        public bool $is_primary = true,

        public ?array $meta = null,

        public ?string $work_pattern_name = null,

        #[MapName('created_at')]
        public ?string $createdAt = null,

        #[MapName('updated_at')]
        public ?string $updatedAt = null,
    ) {}

    /**
     * DTO előállítása modelből.
     *
     * @param EmployeeWorkPattern $employeeWorkPattern Hozzárendelés model
     * @return self
     */
    public static function fromModel(EmployeeWorkPattern $employeeWorkPattern): self
    {
        $employeeWorkPattern->loadMissing('workPattern');

        return new self(
            id: (int) $employeeWorkPattern->id,
            company_id: (int) $employeeWorkPattern->company_id,
            employee_id: (int) $employeeWorkPattern->employee_id,
            work_pattern_id: (int) $employeeWorkPattern->work_pattern_id,
            date_from: (string) optional($employeeWorkPattern->date_from)?->format('Y-m-d'),
            date_to: optional($employeeWorkPattern->date_to)?->format('Y-m-d'),
            is_primary: (bool) $employeeWorkPattern->is_primary,
            meta: $employeeWorkPattern->meta,
            work_pattern_name: $employeeWorkPattern->workPattern?->name,
            createdAt: optional($employeeWorkPattern->created_at)?->toDateTimeString(),
            updatedAt: optional($employeeWorkPattern->updated_at)?->toDateTimeString(),
        );
    }
}
