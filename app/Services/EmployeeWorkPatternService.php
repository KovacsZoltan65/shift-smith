<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\EmployeeWorkPattern\EmployeeWorkPatternData;
use App\Interfaces\EmployeeWorkPatternRepositoryInterface;
use App\Models\Employee;
use App\Models\WorkPattern;
use Illuminate\Validation\ValidationException;

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
        $this->validateDateRange($data->date_from, $data->date_to);
        $this->validateCompanyConsistency($data->company_id, $data->employee_id, $data->work_pattern_id);

        if ($this->repo->hasOverlap($data->company_id, $data->employee_id, $data->date_from, $data->date_to)) {
            throw ValidationException::withMessages([
                'date_from' => 'A megadott időszak átfedésben van egy meglévő munkarenddel.',
            ]);
        }

        $row = $this->repo->assign([
            'company_id' => $data->company_id,
            'employee_id' => $data->employee_id,
            'work_pattern_id' => $data->work_pattern_id,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
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
    public function updateAssignment(int $id, int $employeeId, int $companyId, EmployeeWorkPatternData $data): EmployeeWorkPatternData
    {
        $this->validateDateRange($data->date_from, $data->date_to);
        $this->validateCompanyConsistency($companyId, $employeeId, $data->work_pattern_id);

        if ($this->repo->hasOverlap($companyId, $employeeId, $data->date_from, $data->date_to, $id)) {
            throw ValidationException::withMessages([
                'date_from' => 'A megadott időszak átfedésben van egy meglévő munkarenddel.',
            ]);
        }

        $row = $this->repo->updateAssignment($id, $employeeId, $companyId, [
            'work_pattern_id' => $data->work_pattern_id,
            'date_from' => $data->date_from,
            'date_to' => $data->date_to,
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
    public function unassign(int $id, int $employeeId, int $companyId): bool
    {
        return $this->repo->unassign($id, $employeeId, $companyId);
    }

    private function validateDateRange(string $dateFrom, ?string $dateTo): void
    {
        if ($dateTo !== null && $dateFrom > $dateTo) {
            throw ValidationException::withMessages([
                'date_to' => 'A záró dátum nem lehet korábbi, mint a kezdő dátum.',
            ]);
        }
    }

    private function validateCompanyConsistency(int $companyId, int $employeeId, int $workPatternId): void
    {
        $employee = Employee::query()->find($employeeId);
        $workPattern = WorkPattern::query()->find($workPatternId);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nem található.',
            ]);
        }

        if ($workPattern === null) {
            throw ValidationException::withMessages([
                'work_pattern_id' => 'A munkarend nem található.',
            ]);
        }

        $employeeInCompany = $employee->companies()
            ->where('companies.id', $companyId)
            ->where('companies.active', true)
            ->where('company_employee.active', true)
            ->exists();

        if (! $employeeInCompany) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nem a megadott céghez tartozik.',
            ]);
        }

        if ((int) $workPattern->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'work_pattern_id' => 'A munkarend nem a megadott céghez tartozik.',
            ]);
        }
    }
}
