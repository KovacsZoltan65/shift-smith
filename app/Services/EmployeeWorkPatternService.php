<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\EmployeeWorkPattern\EmployeeWorkPatternData;
use App\Interfaces\EmployeeWorkPatternRepositoryInterface;

/**
 * Dolgozó-munkarend hozzárendelés szolgáltatás osztály.
 *
 * Üzleti logikai réteg a munkarend hozzárendelések kezeléséhez.
 */
class EmployeeWorkPatternService
{
    /**
     * @param EmployeeWorkPatternRepositoryInterface $repo Hozzárendelés repository
     */
    public function __construct(
        private readonly EmployeeWorkPatternRepositoryInterface $repo
    ) {}

    /**
     * Dolgozó munkarend hozzárendeléseinek listázása.
     *
     * @param int $employeeId Dolgozó azonosító
     * @param int $companyId Cég azonosító
     * @return array<int, EmployeeWorkPatternData> DTO lista
     */
    public function listByEmployee(int $employeeId, int $companyId): array
    {
        $rows = $this->repo->listByEmployee($employeeId, $companyId);
        return array_map(
            fn ($row): EmployeeWorkPatternData => EmployeeWorkPatternData::fromModel($row),
            $rows
        );
    }

    /**
     * Munkarend hozzárendelése dolgozóhoz.
     *
     * @param EmployeeWorkPatternData $data Hozzárendelés DTO
     * @return EmployeeWorkPatternData Létrehozott hozzárendelés DTO
     */
    public function assign(EmployeeWorkPatternData $data): EmployeeWorkPatternData
    {
        $row = $this->repo->assign([
            'company_id' => $data->company_id,
            'employee_id' => $data->employee_id,
            'work_pattern_id' => $data->work_pattern_id,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'is_primary' => $data->is_primary,
            'meta' => $data->meta,
        ]);

        return EmployeeWorkPatternData::fromModel($row);
    }

    /**
     * Hozzárendelés frissítése.
     *
     * @param int $id Hozzárendelés azonosító
     * @param int $employeeId Dolgozó azonosító
     * @param EmployeeWorkPatternData $data Frissítendő DTO
     * @return EmployeeWorkPatternData Frissített DTO
     */
    public function updateAssignment(int $id, int $employeeId, EmployeeWorkPatternData $data): EmployeeWorkPatternData
    {
        $row = $this->repo->updateAssignment($id, $employeeId, [
            'work_pattern_id' => $data->work_pattern_id,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
            'is_primary' => $data->is_primary,
            'meta' => $data->meta,
        ]);

        return EmployeeWorkPatternData::fromModel($row);
    }

    /**
     * Hozzárendelés törlése.
     *
     * @param int $id Hozzárendelés azonosító
     * @param int $employeeId Dolgozó azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function unassign(int $id, int $employeeId): bool
    {
        return $this->repo->unassign($id, $employeeId);
    }
}
