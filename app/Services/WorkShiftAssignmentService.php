<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Models\WorkShiftAssignment;

/**
 * Legacy/v1 WorkShiftAssignment service.
 */
class WorkShiftAssignmentService
{
    /**
     * @param WorkShiftAssignmentRepositoryInterface $repo
     */
    public function __construct(
        private readonly WorkShiftAssignmentRepositoryInterface $repo
    ) {}

    /**
     * Műszakhoz tartozó hozzárendelések listázása.
     *
     * @param int $workShiftId Műszak azonosító
     * @param int $companyId Tenant cég azonosító
     * @return list<WorkShiftAssignment>
     */
    public function listByShift(int $workShiftId, int $companyId): array
    {
        return $this->repo->listByShift($workShiftId, $companyId);
    }

    /**
     * @param array{
     *   company_id: int,
     *   work_shift_id: int,
     *   employee_id: int,
     *   day: string,
     *   active: bool
     * } $payload
     * @return WorkShiftAssignment
     */
    public function store(array $payload): WorkShiftAssignment
    {
        return $this->repo->store($payload);
    }

    /**
     * Hozzárendelés törlése tenant scope-pal.
     *
     * @param int $id Hozzárendelés azonosító
     * @param int $workShiftId Műszak azonosító
     * @param int $companyId Tenant cég azonosító
     * @return bool
     */
    public function destroy(int $id, int $workShiftId, int $companyId): bool
    {
        return $this->repo->destroy($id, $workShiftId, $companyId);
    }
}
