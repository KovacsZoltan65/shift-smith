<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EmployeeAbsence;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\SickLeaveCategory;
use App\Models\WorkShiftAssignment;
use Illuminate\Support\Collection;

interface EmployeeAbsenceRepositoryInterface
{
    public function fetchCalendarEvents(int $companyId, array $filters): Collection;

    public function findByIdInCompany(int $id, int $companyId): ?EmployeeAbsence;

    public function createForCompany(int $companyId, array $data): EmployeeAbsence;

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<int, EmployeeAbsence>
     */
    public function createManyForCompany(int $companyId, array $rows): Collection;

    public function updateInCompany(int $id, int $companyId, array $data): EmployeeAbsence;

    public function deleteInCompany(int $id, int $companyId): void;

    public function findEmployeeForCompany(int $employeeId, int $companyId): Employee;

    public function findLeaveTypeForCompany(int $leaveTypeId, int $companyId): LeaveType;

    public function findSickLeaveCategoryForCompany(int $categoryId, int $companyId): SickLeaveCategory;

    /**
     * @param list<int> $employeeIds
     */
    public function employeesBelongToCompany(int $companyId, array $employeeIds): bool;

    public function findOverlappingAbsence(
        int $companyId,
        int $employeeId,
        string $dateFrom,
        string $dateTo,
        ?int $ignoreId = null
    ): ?EmployeeAbsence;

    public function findShiftAssignmentConflict(
        int $companyId,
        int $employeeId,
        string $dateFrom,
        string $dateTo
    ): ?WorkShiftAssignment;
}
