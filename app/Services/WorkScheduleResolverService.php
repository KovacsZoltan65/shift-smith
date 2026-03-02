<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;

class WorkScheduleResolverService
{
    public function __construct(
        private readonly WorkScheduleAssignmentRepositoryInterface $workScheduleRepository
    ) {}

    public function resolveForCompanyAndPattern(int $companyId, int $workPatternId): int
    {
        $schedule = $this->workScheduleRepository->firstOrCreateDefaultScheduleForPattern(
            $companyId,
            $workPatternId
        );

        return (int) $schedule->id;
    }
}
