<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EmployeeAbsence;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Support\Collection;

interface EmployeeAbsenceRepositoryInterface
{
    public function fetchCalendarEvents(int $companyId, array $filters): Collection;

    public function findByIdInCompany(int $id, int $companyId): ?EmployeeAbsence;

    public function createForCompany(int $companyId, array $data): EmployeeAbsence;

    public function updateInCompany(int $id, int $companyId, array $data): EmployeeAbsence;

    public function deleteInCompany(int $id, int $companyId): void;

    public function findEmployeeForCompany(int $employeeId, int $companyId): Employee;

    public function findLeaveTypeForCompany(int $leaveTypeId, int $companyId): LeaveType;
}
