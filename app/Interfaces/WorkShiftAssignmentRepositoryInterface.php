<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\WorkShiftAssignment;

/**
 * Legacy/v1 WorkShiftAssignment repository interfész.
 */
interface WorkShiftAssignmentRepositoryInterface
{
    /**
     * Műszakhoz tartozó hozzárendelések listázása tenant scope-pal.
     *
     * @param int $workShiftId Műszak azonosító
     * @param int $companyId Tenant cég azonosító
     * @return list<WorkShiftAssignment>
     */
    public function listByShift(int $workShiftId, int $companyId): array;

    /**
     * Új hozzárendelés létrehozása vagy soft-deleted rekord visszaállítása.
     *
     * @param array{
     *   company_id: int,
     *   work_shift_id: int,
     *   employee_id: int,
     *   day: string,
     *   active: bool
     * } $data
     */
    public function store(array $data): WorkShiftAssignment;

    /**
     * Hozzárendelés törlése tenant + műszak scope-pal.
     *
     * @param int $id Hozzárendelés azonosító
     * @param int $workShiftId Műszak azonosító
     * @param int $companyId Tenant cég azonosító
     * @return bool
     */
    public function destroy(int $id, int $workShiftId, int $companyId): bool;
}
